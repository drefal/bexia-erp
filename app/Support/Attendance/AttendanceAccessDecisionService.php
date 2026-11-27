<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\AttendanceTerminal;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AttendanceAccessDecisionService
{
    public static function record(
        EmployeeAttendance $attendance,
        AttendanceTerminal $terminal
    ): array {

        if (DB::transactionLevel() < 1) {
            throw new RuntimeException(
                'ACCESS_TRANSACTION_REQUIRED'
            );
        }

        $companyId = (int) $terminal->company_id;
        $branchId = (int) $terminal->branch_id;
        $employeeId = (int) $attendance->employee_id;
        $attendanceId = (int) $attendance->id;
        $terminalId = (int) $terminal->id;

        if (
            $companyId <= 0
            || $branchId <= 0
            || $employeeId <= 0
            || $attendanceId <= 0
            || $terminalId <= 0
            || (int) $attendance->company_id !== $companyId
        ) {
            throw new RuntimeException(
                'INVALID_DECISION_SCOPE'
            );
        }

        // Serialize all access operations for this employee.

        Employee::query()
            ->where('company_id', $companyId)
            ->whereKey($employeeId)
            ->lockForUpdate()
            ->firstOrFail();

        $attendance = EmployeeAttendance::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereKey($attendanceId)
            ->lockForUpdate()
            ->firstOrFail();

        $terminal = AttendanceTerminal::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereKey($terminalId)
            ->firstOrFail();

        if (
            !$terminal->active
            || $terminal->isBlocked()
            || data_get(
                $terminal->settings,
                'terminal_type',
                'attendance'
            ) !== 'attendance'
        ) {
            throw new RuntimeException(
                'INVALID_ENTRY_TERMINAL'
            );
        }

        if (
            !$attendance->clock_in_at
            || (int) $attendance
                ->clock_in_attendance_terminal_id !== $terminalId
            || $attendance->clock_in_method !== 'terminal_qr'
        ) {
            throw new RuntimeException(
                'INVALID_ENTRY_EVIDENCE'
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
                !== $branchId
        ) {
            throw new RuntimeException(
                'INVALID_PHYSICAL_ENTRY_SCOPE'
            );
        }

        // Never evaluate the same entry twice.

        $existing = DB::table('hr_access_decisions')
            ->where('company_id', $companyId)
            ->where('employee_attendance_id', $attendanceId)
            ->lockForUpdate()
            ->first();

        if ($existing) {

            if (
                (int) $existing->employee_id !== $employeeId
                || (int) $existing->branch_id !== $branchId
                || (int) $existing->attendance_terminal_id
                    !== $terminalId
            ) {
                throw new RuntimeException(
                    'EXISTING_DECISION_SCOPE_MISMATCH'
                );
            }

            return self::response($existing, false);
        }

        $entryAt = DateTimeImmutable::createFromInterface(
            $attendance->clock_in_at
        );

        $context = AttendanceAccessEntryContext::resolve(
            $companyId,
            $branchId,
            $employeeId,
            $entryAt,
            true
        );

        $result = AttendanceAccessEvaluator::evaluate(
            $context
        );

        // Do not issue a green result based on incomplete
        // absence information when absence control is enabled.

        if (
            $context['enabled']
            && $context['hold_on_approved_absence']
            && !$context['absence_evaluated']
            && $result['status'] === 'authorized'
            && $result['reason_code'] !== 'prior_authorization'
        ) {

            $result = [
                'status' => 'review',
                'reason_code' => 'absence_context_not_available',
                'requires_authorization' => true,
            ];
        }

        $assignment = [
            'assigned_user_id' => null,
            'assignment_source' => null,
            'recipient_user_ids' => [],
        ];

        if (
            $result['status'] === 'hold'
            && $result['requires_authorization']
            && in_array(
                $result['reason_code'],
                [
                    'late_requires_authorization',
                    'non_working_day',
                    'outside_working_hours',
                    'early_arrival_outside_limit',
                ],
                true
            )
            && (
                !$context['hold_on_approved_absence']
                || $context['absence_evaluated']
            )
        ) {
            $assignment = AttendanceAccessRecipients::resolve(
                $companyId,
                $branchId,
                $employeeId,
                $context
            );
        }

        $schedule = $context['schedule'];

        $snapshot = [
            'version' => 1,
            'recipient_user_ids' =>
                $assignment['recipient_user_ids'],
            'timezone' => AttendanceAccessEntryContext::TIMEZONE,
            'policy_id' => $context['policy_id'],
            'policy_enabled' => $context['enabled'],
            'employee_active' => $context['employee_active'],
            'hold_on_late' => $context['hold_on_late'],
            'hold_on_rest_day' => $context['hold_on_rest_day'],
            'hold_on_approved_absence' =>
                $context['hold_on_approved_absence'],
            'access_tolerance_minutes' =>
                $context['access_tolerance_minutes'],
            'early_arrival_minutes' =>
                $context['early_arrival_minutes'],
            'absence_evaluated' =>
                $context['absence_evaluated'],
            'schedule_id' =>
                $schedule['schedule_id'] ?? null,
            'schedule_type' =>
                $schedule['schedule_type'] ?? null,
            'is_working_day' =>
                $schedule['is_working_day'] ?? null,
            'schedule_start' =>
                ($schedule['start_at'] ?? null)
                    ?->format(DATE_ATOM),
            'schedule_end' =>
                ($schedule['end_at'] ?? null)
                    ?->format(DATE_ATOM),
            'schedule_tolerance_minutes' =>
                $schedule['tolerance_late_minutes'] ?? null,
            'hard_restriction' =>
                $context['hard_restriction'],
            'temporary_restriction' =>
                $context['temporary_restriction'],
        ];

        $now = now()->toIso8601String();

        $authorizationId =
            $context['access_authorization_id'];

        // Reference only an authorization actually used
        // by the resulting decision.

        if (!in_array(
            $result['reason_code'],
            [
                'prior_authorization',
                'temporary_restriction',
                'explicit_restriction',
            ],
            true
        )) {
            $authorizationId = null;
        }

        $decisionId = DB::table('hr_access_decisions')
            ->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'employee_id' => $employeeId,
                'employee_attendance_id' => $attendanceId,
                'attendance_terminal_id' => $terminalId,
                'access_policy_id' => $context['policy_id'],
                'access_authorization_id' => $authorizationId,
                'entry_at' => $entryAt->format(
                    'Y-m-d H:i:s.uP'
                ),
                'evaluated_at' => $now,
                'initial_status' => $result['status'],
                'initial_reason_code' =>
                    $result['reason_code'],
                'status' => $result['status'],
                'reason_code' => $result['reason_code'],
                'requires_authorization' =>
                    $result['requires_authorization'],
                'evaluation_snapshot' => json_encode(
                    $snapshot,
                    JSON_THROW_ON_ERROR
                ),
                'assigned_user_id' =>
                    $assignment['assigned_user_id'],
                'assignment_source' =>
                    $assignment['assignment_source'],
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('hr_access_events')->insert([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'access_decision_id' => $decisionId,
            'event_type' => 'entry_evaluated',
            'actor_type' => 'system',
            'actor_user_id' => null,
            'from_status' => null,
            'to_status' => $result['status'],
            'reason' => null,
            'metadata' => json_encode([
                'reason_code' => $result['reason_code'],
                'branch_id' => $branchId,
                'terminal_id' => $terminalId,
                'attendance_id' => $attendanceId,
                'assigned_user_id' =>
                    $assignment['assigned_user_id'],
                'assignment_source' =>
                    $assignment['assignment_source'],
            ], JSON_THROW_ON_ERROR),
            'occurred_at' => $now,
        ]);

        $decision = DB::table('hr_access_decisions')
            ->where('company_id', $companyId)
            ->where('id', $decisionId)
            ->first();

        if (
            $result['status'] === 'hold'
            && $assignment['recipient_user_ids'] !== []
        ) {

            DB::afterCommit(
                static function () use (
                    $companyId,
                    $decisionId
                ): void {

                    AttendanceAccessNotifications::notifyPending(
                        $companyId,
                        (int) $decisionId
                    );
                }
            );
        }

        return self::response($decision, true);
    }

    private static function response(
        object $decision,
        bool $created
    ): array {

        return [
            'decision_id' => (int) $decision->id,
            'status' => $decision->status,
            'reason_code' => $decision->reason_code,
            'requires_authorization' =>
                (bool) $decision->requires_authorization,
            'version' => (int) $decision->version,
            'created' => $created,
        ];
    }
}
