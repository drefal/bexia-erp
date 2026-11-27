<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\User;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use InvalidArgumentException;

final class AttendanceAccessResolutionService
{
    public static function resolve(
        int $companyId,
        int $decisionId,
        int $expectedVersion,
        string $action,
        string $reason
    ): array {

        $actor = auth()->user();

        if (!$actor instanceof User) {
            throw new AuthorizationException(
                'AUTHENTICATION_REQUIRED'
            );
        }

        if (
            $companyId <= 0
            || $decisionId <= 0
            || $expectedVersion <= 0
            || !in_array(
                $action,
                ['authorize_now', 'reject_access'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'INVALID_RESOLUTION_REQUEST'
            );
        }

        $reason = trim($reason);

        if (
            mb_strlen($reason) < 5
            || mb_strlen($reason) > 1000
        ) {
            throw new InvalidArgumentException(
                'INVALID_REASON_LENGTH'
            );
        }

        return DB::transaction(
            function () use (
                $actor,
                $companyId,
                $decisionId,
                $expectedVersion,
                $action,
                $reason
            ): array {

                $initial = DB::table('hr_access_decisions')
                    ->where('company_id', $companyId)
                    ->where('id', $decisionId)
                    ->first();

                if (!$initial) {
                    throw new AuthorizationException(
                        'DECISION_NOT_AVAILABLE'
                    );
                }

                $employeeId = (int) $initial->employee_id;

                $identity = AttendanceAccessContextResolver
                    ::resolve(
                        $actor,
                        $companyId,
                        $employeeId,
                        (int) $initial->branch_id
                    );

                if (
                    !$identity
                    || !in_array(
                        AttendanceAccessAuthorization::VIEW,
                        $identity['permissions'],
                        true
                    )
                ) {
                    throw new AuthorizationException(
                        'ACCESS_PERMISSION_REQUIRED'
                    );
                }

                // Same lock order as QR entry evaluation:
                // employee, attendance, decision.

                Employee::query()
                    ->where('company_id', $companyId)
                    ->whereKey($employeeId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $attendance = EmployeeAttendance::query()
                    ->where('company_id', $companyId)
                    ->where('employee_id', $employeeId)
                    ->whereKey(
                        (int) $initial->employee_attendance_id
                    )
                    ->lockForUpdate()
                    ->first();

                $decision = DB::table('hr_access_decisions')
                    ->where('company_id', $companyId)
                    ->where('employee_id', $employeeId)
                    ->where('id', $decisionId)
                    ->lockForUpdate()
                    ->first();

                if (
                    !$decision
                    || !$attendance
                    || !$attendance->clock_in_at
                    || $attendance->clock_out_at
                    || (int) $decision->employee_attendance_id
                        !== (int) $attendance->id
                    || (int) $decision->branch_id
                        !== (int) $initial->branch_id
                    || (int) $decision->attendance_terminal_id
                        !== (int) $attendance
                            ->clock_in_attendance_terminal_id
                    || $attendance->clock_in_method
                        !== 'terminal_qr'
                ) {
                    throw new RuntimeException(
                        'ENTRY_NOT_AVAILABLE_FOR_RESOLUTION'
                    );
                }

                $device = $attendance->clock_in_device_info;

                if (is_string($device)) {
                    $device = json_decode(
                        $device,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                }

                if (
                    !is_array($device)
                    || (int) ($device['physical_company_id'] ?? 0)
                        !== $companyId
                    || (int) ($device['physical_branch_id'] ?? 0)
                        !== (int) $decision->branch_id
                ) {
                    throw new RuntimeException(
                        'INVALID_PHYSICAL_ENTRY_SCOPE'
                    );
                }

                $entryAt = new DateTimeImmutable(
                    $decision->entry_at
                );

                if (
                    $entryAt->format('U.u')
                        !== $attendance->clock_in_at->format('U.u')
                ) {
                    throw new RuntimeException(
                        'ENTRY_TIMESTAMP_MISMATCH'
                    );
                }

                if (
                    $decision->status !== 'hold'
                    || !$decision->requires_authorization
                    || $decision->resolved_at !== null
                ) {
                    throw new RuntimeException(
                        'DECISION_ALREADY_RESOLVED_OR_NOT_PENDING'
                    );
                }

                if (
                    (int) $decision->version
                        !== $expectedVersion
                ) {
                    throw new RuntimeException(
                        'DECISION_VERSION_CHANGED'
                    );
                }

                $timezone = new DateTimeZone(
                    'America/Mexico_City'
                );

                $now = new DateTimeImmutable(
                    'now',
                    $timezone
                );

                $entryLocal = $entryAt->setTimezone(
                    $timezone
                );

                // First implementation: current-day entries.
                // Historical entries cannot be released.

                if (
                    $entryLocal->format('Y-m-d')
                        !== $now->format('Y-m-d')
                    || $entryAt > $now
                ) {
                    throw new RuntimeException(
                        'ENTRY_OUTSIDE_RESOLUTION_PERIOD'
                    );
                }

                $branchId = (int) $decision->branch_id;

                // Recheck permissions and hierarchy after locks.

                $identity = AttendanceAccessContextResolver
                    ::resolve(
                        $actor,
                        $companyId,
                        $employeeId,
                        $branchId
                    );

                if (
                    !$identity
                    || !in_array(
                        AttendanceAccessAuthorization::VIEW,
                        $identity['permissions'],
                        true
                    )
                ) {
                    throw new AuthorizationException(
                        'ACCESS_PERMISSION_REQUIRED'
                    );
                }

                // Recheck the current policy and restrictions.
                // A manager must never bypass an HR restriction.

                $current = AttendanceAccessEntryContext::resolve(
                    $companyId,
                    $branchId,
                    $employeeId,
                    $now,
                    true
                );

                $context = array_replace(
                    $identity,
                    $current
                );

                $permission = AttendanceAccessAuthorization
                    ::evaluate(
                        $context,
                        $action
                    );

                if (!$permission['allowed']) {
                    throw new AuthorizationException(
                        $permission['reason']
                    );
                }

                if ($action === 'authorize_now') {

                    // Only ordinary schedule exceptions
                    // are eligible for this first version.

                    if (!in_array(
                        $decision->reason_code,
                        [
                            'late_requires_authorization',
                            'non_working_day',
                            'outside_working_hours',
                            'early_arrival_outside_limit',
                        ],
                        true
                    )) {
                        throw new AuthorizationException(
                            'SPECIAL_REVIEW_REQUIRED'
                        );
                    }

                    if (
                        $current['hold_on_approved_absence']
                        && !$current['absence_evaluated']
                    ) {
                        throw new AuthorizationException(
                            'ABSENCE_REVIEW_REQUIRED'
                        );
                    }
                }

                $status = $action === 'authorize_now'
                    ? 'authorized'
                    : 'denied';

                $reasonCode = $action === 'authorize_now'
                    ? 'immediate_authorization'
                    : 'immediate_rejection';

                $timestamp = $now->format(
                    'Y-m-d H:i:s.uP'
                );

                $updated = DB::table('hr_access_decisions')
                    ->where('company_id', $companyId)
                    ->where('id', $decisionId)
                    ->where('version', $expectedVersion)
                    ->where('status', 'hold')
                    ->whereNull('resolved_at')
                    ->update([
                        'status' => $status,
                        'reason_code' => $reasonCode,
                        'requires_authorization' => false,
                        'resolved_by_user_id' => $actor->id,
                        'resolved_at' => $timestamp,
                        'resolution_reason' => $reason,
                        'version' => $expectedVersion + 1,
                        'updated_at' => $timestamp,
                    ]);

                if ($updated !== 1) {
                    throw new RuntimeException(
                        'CONCURRENT_RESOLUTION'
                    );
                }

                DB::table('hr_access_events')->insert([
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'access_decision_id' => $decisionId,
                    'event_type' => $reasonCode,
                    'from_status' => 'hold',
                    'to_status' => $status,
                    'actor_user_id' => $actor->id,
                    'actor_type' => 'user',
                    'reason' => $reason,
                    'metadata' => json_encode([
                        'authorization_source' =>
                            $permission['source'],
                        'previous_version' =>
                            $expectedVersion,
                        'new_version' =>
                            $expectedVersion + 1,
                        'branch_id' => $branchId,
                    ], JSON_THROW_ON_ERROR),
                    'occurred_at' => $timestamp,
                ]);

                // Notify Security only after the
                // outermost database transaction commits.
                DB::afterCommit(
                    static function () use (
                        $companyId,
                        $branchId,
                        $decisionId,
                        $actor
                    ): void {
                        try {
                            (new AttendanceRealtimePublisher())
                                ->publishSecurityRefresh(
                                    $companyId,
                                    $branchId
                                );

                            AttendanceAccessNotifications::refreshResolved(
                                $companyId,
                                $decisionId,
                                (int) $actor->id
                            );
                        } catch (\Throwable $e) {
                            try {
                                \Illuminate\Support\Facades\Log::warning(
                                    'Attendance resolution refresh failed',
                                    [
                                        'company_id' => $companyId,
                                        'branch_id' => $branchId,
                                        'exception_type' => get_class($e),
                                    ]
                                );
                            } catch (\Throwable) {
                                // The authorization has already committed.
                            }
                        }
                    }
                );

                return [
                    'decision_id' => $decisionId,
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'status' => $status,
                    'reason_code' => $reasonCode,
                    'version' => $expectedVersion + 1,
                    'source' => $permission['source'],
                ];
            },
            3
        );
    }
}
