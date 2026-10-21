<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PayrollAttendanceReviewResend
{
    private const JSON_FLAGS =
        JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES;

    public static function resend(
        int $companyId,
        int $payrollRunId,
        int $actorUserId,
        string $reason
    ): array {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw new RuntimeException(
                'Se requiere justificacion de 10 a 2000 caracteres.'
            );
        }

        return DB::transaction(function () use (
            $companyId,
            $payrollRunId,
            $actorUserId,
            $reason
        ) {
            $authz = PayrollAttendanceReviewAuthorization::class;

            if (!$authz::allowed(
                $actorUserId, $companyId, $authz::MANAGE
            )) {
                throw new RuntimeException(
                    'Sin permiso de gestion.'
                );
            }

            $batch = DB::table(
                'hr_payroll_attendance_review_batches'
            )
                ->where('company_id', $companyId)
                ->where('payroll_run_id', $payrollRunId)
                ->lockForUpdate()
                ->first();

            if (
                !$batch
                || $batch->status !== 'sent'
                || $batch->sent_at === null
            ) {
                throw new RuntimeException(
                    'El lote no esta enviado.'
                );
            }

            $preview = PayrollAttendanceReviewResendPreview::inspect(
                $companyId, $payrollRunId, $actorUserId
            );

            if (
                $preview['status'] !== 'eligible_for_reprepare'
                || (int) $preview['batch_id'] !== (int) $batch->id
                || (int) $preview['batch_version']
                    !== (int) $batch->version
            ) {
                throw new RuntimeException(
                    'Reenvio bloqueado por el estado de las revisiones.'
                );
            }

            $run = DB::table('payroll_runs')
                ->where('company_id', $companyId)
                ->where('id', $payrollRunId)
                ->first();

            if (
                !$run
                || $run->status !== 'calculated'
                || (bool) $run->is_locked
                || (string) $run->period_start
                    !== (string) $batch->period_start
                || (string) $run->period_end
                    !== (string) $batch->period_end
            ) {
                throw new RuntimeException(
                    'La prenomina o su periodo cambiaron.'
                );
            }

            $items = DB::table(
                'hr_payroll_attendance_review_items'
            )
                ->where('company_id', $companyId)
                ->where('batch_id', $batch->id)
                ->orderBy('employee_id')
                ->lockForUpdate()
                ->get();

            $reviews = DB::table(
                'hr_payroll_attendance_reviews'
            )
                ->where('company_id', $companyId)
                ->where('batch_id', $batch->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $payrollIds = DB::table('payroll_run_lines')
                ->where('company_id', $companyId)
                ->where('payroll_run_id', $payrollRunId)
                ->distinct()
                ->pluck('employee_id')
                ->map(fn ($id) => (int) $id)
                ->sort()->values()->all();

            $itemIds = $items->pluck('employee_id')
                ->map(fn ($id) => (int) $id)
                ->sort()->values()->all();

            if (
                $items->isEmpty()
                || $payrollIds !== $itemIds
                || $reviews->isEmpty()
            ) {
                throw new RuntimeException(
                    'Poblacion de prenomina inconsistente.'
                );
            }

            $newVersion = (int) $batch->version + 1;
            $now = now();
            $updatedItems = 0;
            $updatedReviews = 0;

            foreach ($reviews as $review) {
                if (
                    $review->status !== 'returned'
                    || (int) $review->version
                        !== (int) $batch->version
                    || empty(trim((string) $review->reviewer_comment))
                ) {
                    throw new RuntimeException(
                        'Revision no devuelta o sin observaciones.'
                    );
                }

                if (!$authz::allowed(
                    (int) $review->reviewer_user_id,
                    $companyId,
                    $authz::VIEW
                ) || !$authz::allowed(
                    (int) $review->reviewer_user_id,
                    $companyId,
                    $authz::APPROVE
                )) {
                    throw new RuntimeException(
                        'Responsable sin permisos vigentes.'
                    );
                }

                $groupItems = $items->filter(
                    fn ($item) =>
                        (int) $item->review_id === (int) $review->id
                )->values();

                if ($groupItems->isEmpty()) {
                    throw new RuntimeException(
                        'Revision sin empleados.'
                    );
                }

                $hashInput = [];
                $previousEvidence = [];

                foreach ($groupItems as $item) {
                    $employeeId = (int) $item->employee_id;
                    $reviewerId = (int) $review->reviewer_user_id;

                    if (
                        (int) $item->reviewer_user_id !== $reviewerId
                        || $item->assignment_issue !== null
                    ) {
                        throw new RuntimeException(
                            'Elemento con asignacion invalida.'
                        );
                    }

                    $resolution =
                        PayrollAttendanceReviewerResolver::resolve(
                            $companyId,
                            $employeeId
                        );

                    $employee = DB::table('employees')
                        ->where('company_id', $companyId)
                        ->where('id', $employeeId)
                        ->first(['hr_department_id']);

                    if (
                        !$employee
                        || (int) ($resolution['reviewer_user_id'] ?? 0)
                            !== $reviewerId
                        || $resolution['source']
                            !== $item->assignment_source
                        || ($resolution['reason'] ?? null) !== null
                        || (
                            $employee->hr_department_id === null
                                ? null
                                : (int) $employee->hr_department_id
                        ) !== (
                            $item->department_id === null
                                ? null
                                : (int) $item->department_id
                        )
                    ) {
                        throw new RuntimeException(
                            'Cambio de responsable o departamento.'
                        );
                    }

                    $oldSnapshot = json_decode(
                        (string) $item->attendance_snapshot,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );

                    if (
                        !is_array($oldSnapshot)
                        || !hash_equals(
                            (string) $item->snapshot_hash,
                            PayrollAttendanceSnapshotService::hash(
                                $oldSnapshot
                            )
                        )
                    ) {
                        throw new RuntimeException(
                            'Fotografia anterior invalida.'
                        );
                    }

                    $newSnapshot =
                        PayrollAttendanceSnapshotService::capture(
                            $companyId,
                            $employeeId,
                            $payrollRunId
                        );

                    $newHash =
                        PayrollAttendanceSnapshotService::hash(
                            $newSnapshot
                        );

                    $previousEvidence[] = [
                        'employee_id' => $employeeId,
                        'old_snapshot_hash' => $item->snapshot_hash,
                        'old_snapshot' => $oldSnapshot,
                        'new_snapshot_hash' => $newHash,
                    ];

                    $hashInput[] = [
                        'employee_id' => $employeeId,
                        'snapshot_hash' => $newHash,
                    ];

                    DB::table(
                        'hr_payroll_attendance_review_items'
                    )
                        ->where('id', $item->id)
                        ->where('batch_id', $batch->id)
                        ->update([
                            'attendance_snapshot' => json_encode(
                                $newSnapshot,
                                self::JSON_FLAGS
                            ),
                            'snapshot_hash' => $newHash,
                            'updated_at' => $now,
                        ]);

                    $updatedItems++;
                }

                $newGroupHash = hash(
                    'sha256',
                    json_encode($hashInput, self::JSON_FLAGS)
                );

                $historicalReview = [
                    'status' => $review->status,
                    'version' => (int) $review->version,
                    'snapshot_hash' => $review->snapshot_hash,
                    'reviewer_comment' => $review->reviewer_comment,
                    'sent_at' => $review->sent_at,
                    'reviewed_at' => $review->reviewed_at,
                    'reviewed_by_user_id' =>
                        $review->reviewed_by_user_id,
                ];

                // Evidencia anterior antes de reabrir la revision.
                DB::table(
                    'hr_payroll_attendance_review_events'
                )->insert([
                    'batch_id' => $batch->id,
                    'review_id' => $review->id,
                    'company_id' => $companyId,
                    'actor_user_id' => $actorUserId,
                    'action' => 'review_resent',
                    'previous_status' => 'returned',
                    'new_status' => 'pending',
                    'review_version' => $newVersion,
                    'snapshot_hash' => $newGroupHash,
                    'comment' => $reason,
                    'metadata' => json_encode([
                        'previous_review' => $historicalReview,
                        'previous_items' => $previousEvidence,
                        'new_version' => $newVersion,
                        'reason' => $reason,
                    ], self::JSON_FLAGS),
                    'created_at' => $now,
                ]);

                $affected = DB::table(
                    'hr_payroll_attendance_reviews'
                )
                    ->where('id', $review->id)
                    ->where('batch_id', $batch->id)
                    ->where('status', 'returned')
                    ->where('version', $batch->version)
                    ->update([
                        'status' => 'pending',
                        'version' => $newVersion,
                        'snapshot_hash' => $newGroupHash,
                        'reviewer_comment' => null,
                        'sent_at' => $now,
                        'reviewed_at' => null,
                        'reviewed_by_user_id' => null,
                        'updated_at' => $now,
                    ]);

                if ($affected !== 1) {
                    throw new RuntimeException(
                        'No se pudo reabrir una revision.'
                    );
                }

                $updatedReviews++;
            }

            DB::table(
                'hr_payroll_attendance_review_batches'
            )
                ->where('id', $batch->id)
                ->where('company_id', $companyId)
                ->where('status', 'sent')
                ->where('version', $batch->version)
                ->update([
                    'version' => $newVersion,
                    'sent_at' => $now,
                    'updated_at' => $now,
                ]);

            DB::table(
                'hr_payroll_attendance_review_events'
            )->insert([
                'batch_id' => $batch->id,
                'review_id' => null,
                'company_id' => $companyId,
                'actor_user_id' => $actorUserId,
                'action' => 'batch_resent',
                'previous_status' => 'sent',
                'new_status' => 'sent',
                'review_version' => $newVersion,
                'snapshot_hash' => null,
                'comment' => $reason,
                'metadata' => json_encode([
                    'old_version' => (int) $batch->version,
                    'new_version' => $newVersion,
                    'reviews_resent' => $updatedReviews,
                    'items_refreshed' => $updatedItems,
                ], self::JSON_FLAGS),
                'created_at' => $now,
            ]);

            return [
                'status' => 'resent',
                'batch_id' => (int) $batch->id,
                'version' => $newVersion,
                'reviews_resent' => $updatedReviews,
                'items_refreshed' => $updatedItems,
            ];
        }, 1);
    }
}
