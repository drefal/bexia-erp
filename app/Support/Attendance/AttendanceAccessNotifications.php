<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\Company;
use App\Models\User;
use App\Support\BexiaUserNotification;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AttendanceAccessNotifications
{
    public static function notifyPending(
        int $companyId,
        int $decisionId,
        bool $publishRefresh = true
    ): array {

        $stats = [
            'created' => 0,
            'existing' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        // This method must run after the outer commit.
        if (DB::transactionLevel() !== 0) {
            $stats['errors']++;
            return $stats;
        }

        try {

            $decision = DB::table('hr_access_decisions')
                ->where('company_id', $companyId)
                ->where('id', $decisionId)
                ->where('status', 'hold')
                ->where('requires_authorization', true)
                ->whereNull('resolved_at')
                ->first();

            if (!$decision) {
                $stats['skipped']++;
                return $stats;
            }

            $company = Company::query()
                ->whereKey($companyId)
                ->where('active', true)
                ->first();

            if (!$company) {
                $stats['skipped']++;
                return $stats;
            }

            $context = AttendanceAccessEntryContext::resolve(
                $companyId,
                (int) $decision->branch_id,
                (int) $decision->employee_id,
                new DateTimeImmutable(
                    'now',
                    new DateTimeZone('America/Mexico_City')
                )
            );

            $assignment = AttendanceAccessRecipients::resolve(
                $companyId,
                (int) $decision->branch_id,
                (int) $decision->employee_id,
                $context
            );

            foreach (
                $assignment['recipient_user_ids'] as $userId
            ) {

                $user = User::query()->find($userId);

                if (!$user) {
                    $stats['skipped']++;
                    continue;
                }

                // Revalidate actual inbox visibility.
                // Do not trust a historical assignment alone.

                $visible = collect(
                    AttendanceAccessPendingInbox::rows(
                        $user,
                        $company
                    )
                )->contains('id', $decisionId);

                if (!$visible) {
                    $stats['skipped']++;
                    continue;
                }

                try {

                    $outcome = self::persistNotice(
                        $companyId,
                        $decisionId,
                        (int) $userId
                    );

                    $stats[$outcome]++;

                } catch (Throwable $e) {

                    $stats['errors']++;

                    self::warn(
                        'Attendance access notice failed',
                        $companyId,
                        $decisionId,
                        $e
                    );
                }

                if ($publishRefresh) {

                    self::refreshUser(
                        $companyId,
                        (int) $userId
                    );
                }
            }

        } catch (Throwable $e) {

            $stats['errors']++;

            self::warn(
                'Attendance access notification failed',
                $companyId,
                $decisionId,
                $e
            );
        }
        return $stats;
    }

    private static function persistNotice(
        int $companyId,
        int $decisionId,
        int $userId
    ): string {

        return DB::transaction(function () use (
            $companyId,
            $decisionId,
            $userId
        ): string {

            $decision = DB::table('hr_access_decisions')
                ->where('company_id', $companyId)
                ->where('id', $decisionId)
                ->lockForUpdate()
                ->first();

            if (
                !$decision
                || $decision->status !== 'hold'
                || !$decision->requires_authorization
                || $decision->resolved_at !== null
            ) {
                return 'skipped';
            }

            $exists = DB::table('bexia_notifications')
                ->where('company_id', $companyId)
                ->where('user_id', $userId)
                ->where('type', 'hr_access_pending')
                ->whereRaw(
                    "metadata->>'decision_id' = ?",
                    [(string) $decisionId]
                )
                ->exists();

            if ($exists) {
                return 'existing';
            }

            BexiaUserNotification::send(
                $userId,
                'Ingreso pendiente de autorizacion',
                'Existe una solicitud urgente de acceso.',
                url(
                    '/admin/'
                    . $companyId
                    . '/aprobaciones-rrhh'
                ),
                $companyId,
                'hr_access_pending',
                [
                    'decision_id' => $decisionId,
                ]
            );
            $saved = DB::table('bexia_notifications')
                ->where('company_id', $companyId)
                ->where('user_id', $userId)
                ->where('type', 'hr_access_pending')
                ->whereRaw(
                    "metadata->>'decision_id' = ?",
                    [(string) $decisionId]
                )
                ->exists();

            if (!$saved) {
                throw new \RuntimeException(
                    'NOTICE_NOT_PERSISTED'
                );
            }

            return 'created';

        }, 1);
    }

    public static function refreshResolved(
        int $companyId,
        int $decisionId,
        int $actorId
    ): void {

        if (DB::transactionLevel() !== 0) {
            return;
        }

        try {

            $decision = DB::table('hr_access_decisions')
                ->where('company_id', $companyId)
                ->where('id', $decisionId)
                ->first();

            if (!$decision || !$decision->resolved_at) {
                return;
            }

            $ids = [$actorId];

            $snapshot = json_decode(
                $decision->evaluation_snapshot,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            foreach (
                ($snapshot['recipient_user_ids'] ?? []) as $id
            ) {
                $ids[] = (int) $id;
            }

            if ($decision->assigned_user_id) {
                $ids[] = (int) $decision->assigned_user_id;
            }

            // Include recipients of persisted notices.

            $notified = DB::table('bexia_notifications')
                ->where('company_id', $companyId)
                ->where('type', 'hr_access_pending')
                ->whereRaw(
                    "metadata->>'decision_id' = ?",
                    [(string) $decisionId]
                )
                ->pluck('user_id');

            foreach ($notified as $id) {
                $ids[] = (int) $id;
            }

            $ids = array_unique(
                array_filter(
                    $ids,
                    fn ($id) => $id > 0
                )
            );

            foreach ($ids as $userId) {
                self::refreshUser(
                    $companyId,
                    (int) $userId
                );
            }

        } catch (Throwable $e) {

            self::warn(
                'Attendance access resolution notice failed',
                $companyId,
                $decisionId,
                $e
            );
        }
    }

    private static function refreshUser(
        int $companyId,
        int $userId
    ): void {

        try {

            (new AttendanceRealtimePublisher())
                ->publishUserRefresh(
                    $companyId,
                    $userId
                );

        } catch (Throwable $e) {

            self::warn(
                'Attendance user refresh failed',
                $companyId,
                0,
                $e
            );
        }
    }

    private static function warn(
        string $message,
        int $companyId,
        int $decisionId,
        Throwable $e
    ): void {

        try {

            Log::warning($message, [
                'company_id' => $companyId,
                'decision_id' => $decisionId,
                'exception_type' => get_class($e),
            ]);

        } catch (Throwable) {
            // Notification failures must not affect attendance.
        }
    }
}
