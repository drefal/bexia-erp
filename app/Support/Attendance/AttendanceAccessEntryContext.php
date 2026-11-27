<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\Employee;
use App\Models\HrWorkSchedule;
use App\Support\EmployeeWorkScheduleResolver;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AttendanceAccessEntryContext
{
    public const TIMEZONE = 'America/Mexico_City';

    public static function resolve(
        int $companyId,
        int $branchId,
        int $employeeId,
        DateTimeImmutable $entryAt,
        bool $lock = false
    ): array {

        if (
            $companyId <= 0
            || $branchId <= 0
            || $employeeId <= 0
        ) {
            throw new RuntimeException(
                'INVALID_ACCESS_SCOPE'
            );
        }

        if (
            $lock
            && DB::transactionLevel() < 1
        ) {
            throw new RuntimeException(
                'TRANSACTION_REQUIRED'
            );
        }

        $branch = DB::table('branches')
            ->where('company_id', $companyId)
            ->where('id', $branchId)
            ->where('active', true)
            ->first();

        if (!$branch) {
            throw new RuntimeException(
                'INVALID_ACCESS_BRANCH'
            );
        }

        $query = Employee::query()
            ->where('company_id', $companyId)
            ->whereKey($employeeId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $employee = $query->first();

        if (!$employee) {
            throw new RuntimeException(
                'INVALID_ACCESS_EMPLOYEE'
            );
        }

        $at = $entryAt->setTimezone(
            new DateTimeZone(self::TIMEZONE)
        );

        $policy = AttendanceAccessPolicyResolver
            ::resolve(
                $companyId,
                $branchId,
                $lock
            );

        $context = array_replace(
            $policy,
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'employee_id' => $employeeId,
                'employee_active' =>
                    (bool) $employee->active,

                'enabled' =>
                    $policy['policy_enabled'],

                'entry_at' => $at,

                'schedule' => null,

                'hard_restriction' => false,

                'temporary_restriction' => false,

                'prior_authorization' => null,

                'access_authorization_id' => null,

                // Absence integration remains pending.
                'approved_absence' => false,

                'absence_evaluated' => false,
            ]
        );

        // Only trusted, company-scoped schedules.
        $context['schedule'] = self::schedule(
            $employee,
            $at
        );

        $dbTime = $at->format(
            'Y-m-d H:i:s.uP'
        );

        $authorizations = DB::table(
            'hr_access_authorizations'
        )
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where(function ($query) use ($branchId) {

                $query
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            })
            ->where('status', 'approved')
            ->whereNotNull('approved_by_user_id')
            ->whereNotNull('approved_at')
            ->where('approved_at', '<=', $dbTime)
            ->whereNull('revoked_at')
            ->where('valid_from', '<=', $dbTime)
            ->where('valid_until', '>=', $dbTime)
            ->orderByRaw(
                'CASE WHEN branch_id IS NULL '
                . 'THEN 1 ELSE 0 END'
            )
            ->orderByDesc('id');

        if ($lock) {
            $authorizations->lockForUpdate();
        }

        $rows = $authorizations->get();

        $hard = $rows->firstWhere(
            'type',
            'hard_deny'
        );

        $temporary = $rows->firstWhere(
            'type',
            'temporary_hold'
        );

        $allow = $rows->firstWhere(
            'type',
            'allow'
        );

        if ($hard) {

            $context['hard_restriction'] = true;

            $context['access_authorization_id'] =
                (int) $hard->id;

        } elseif ($temporary) {

            $context['temporary_restriction'] = true;

            $context['access_authorization_id'] =
                (int) $temporary->id;

        } elseif ($allow) {

            $context['access_authorization_id'] =
                (int) $allow->id;

            $context['prior_authorization'] = [
                'approved' => true,
                'revoked' => false,

                'valid_from' =>
                    new DateTimeImmutable(
                        $allow->valid_from
                    ),

                'valid_until' =>
                    new DateTimeImmutable(
                        $allow->valid_until
                    ),
            ];
        }

        return $context;
    }

    private static function schedule(
        Employee $employee,
        DateTimeImmutable $at
    ): ?array {

        $companyId = (int) $employee->company_id;

        $scheduleId = $employee->hr_work_schedule_id;

        if (!$scheduleId) {

            $scheduleId = DB::table(
                'employee_contracts'
            )
                ->where('company_id', $companyId)
                ->where('employee_id', $employee->id)
                ->where('is_current', true)
                ->whereNotNull('hr_work_schedule_id')
                ->orderByDesc('start_date')
                ->value('hr_work_schedule_id');
        }

        if (!$scheduleId) {
            return null;
        }

        $schedule = HrWorkSchedule::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereKey($scheduleId)
            ->first();

        if (!$schedule) {
            return null;
        }

        // Use the validated schedule without changing
        // the employee record or payroll resolver.

        $employee->setRelation(
            'hrWorkSchedule',
            $schedule
        );

        $today = CarbonImmutable::instance($at);

        $result = self::scheduleForDay(
            $employee,
            $schedule,
            $today
        );

        // Handle shifts crossing midnight.
        $previous = self::scheduleForDay(
            $employee,
            $schedule,
            $today->subDay()
        );

        if (
            is_array($previous)
            && ($previous['is_working_day'] ?? false)
            && ($previous['schedule_type'] ?? null)
                === 'fixed'
            && ($previous['start_at'] ?? null)
                instanceof DateTimeImmutable
            && ($previous['end_at'] ?? null)
                instanceof DateTimeImmutable
            && $previous['end_at']->format('Y-m-d')
                === $at->format('Y-m-d')
            && $at >= $previous['start_at']
            && $at < $previous['end_at']
        ) {
            return $previous;
        }

        return $result;
    }

    private static function scheduleForDay(
        Employee $employee,
        HrWorkSchedule $schedule,
        CarbonImmutable $date
    ): ?array {

        $dayKey = strtolower(
            $date->format('l')
        );

        $details = DB::table(
            'hr_work_schedule_days'
        )
            ->where(
                'hr_work_schedule_id',
                $schedule->id
            )
            ->where('day_of_week', $dayKey)
            ->get();

        if ($details->count() > 1) {
            return null;
        }

        if (
            $details->isNotEmpty()
            && (int) $details->first()->company_id
                !== (int) $employee->company_id
        ) {
            return null;
        }

        $result = EmployeeWorkScheduleResolver
            ::scheduleForEmployee(
                $employee,
                $date
            );

        if (
            !is_array($result)
            || (int) ($result['schedule_id'] ?? 0)
                !== (int) $schedule->id
        ) {
            return null;
        }

        // Reconstruct local wall-clock times explicitly
        // instead of assuming the Laravel default TZ.

        $timezone = new DateTimeZone(
            self::TIMEZONE
        );

        foreach ([
            'start_at' => 'start_time',
            'end_at' => 'end_time',
        ] as $target => $source) {

            $result[$target] = null;

            if (
                !($result['is_working_day'] ?? false)
                || ($result['schedule_type'] ?? '')
                    === 'flexible'
                || empty($result[$source])
            ) {
                continue;
            }

            $result[$target] =
                new DateTimeImmutable(
                    $date->format('Y-m-d')
                    . ' '
                    . $result[$source],
                    $timezone
                );
        }

        if (
            $result['start_at']
            && $result['end_at']
            && $result['end_at']
                <= $result['start_at']
        ) {
            $result['end_at'] =
                $result['end_at']->modify('+1 day');
        }

        return $result;
    }
}
