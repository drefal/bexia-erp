<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use Carbon\CarbonImmutable;

class EmployeeAttendanceCalculator
{
    public static function apply(EmployeeAttendance $attendance): void
    {
        $employee = $attendance->employee ?: Employee::query()->find($attendance->employee_id);

        if (! $employee) {
            return;
        }

        if (blank($attendance->company_id)) {
            $attendance->company_id = $employee->company_id;
        }

        if (blank($attendance->attendance_date)) {
            $attendance->attendance_date = optional($attendance->clock_in_at)->toDateString() ?: today()->toDateString();
        }

        $date = CarbonImmutable::parse($attendance->attendance_date)->startOfDay();
        $schedule = EmployeeWorkScheduleResolver::scheduleForEmployee($employee, $date);

        if (! $schedule) {
            $attendance->hr_work_schedule_id = null;
            $attendance->expected_start_at = null;
            $attendance->expected_end_at = null;
            $attendance->break_minutes = 0;
            $attendance->expected_hours = 0;
            self::calculateWorkedTime($attendance);
            $attendance->late_minutes = 0;
            $attendance->early_leave_minutes = 0;
            $attendance->overtime_minutes = 0;
            $attendance->status = 'no_schedule';

            return;
        }

        $attendance->hr_work_schedule_id = $schedule['schedule_id'] ?? null;
        $attendance->expected_start_at = $schedule['start_at'] ?? null;
        $attendance->expected_end_at = $schedule['end_at'] ?? null;
        $attendance->break_minutes = (int) ($schedule['break_minutes'] ?? 0);
        $attendance->expected_hours = round((float) ($schedule['expected_hours'] ?? 0), 2);

        self::calculateWorkedTime($attendance);

        $isWorkingDay = (bool) ($schedule['is_working_day'] ?? false);

        if (! $isWorkingDay) {
            $attendance->late_minutes = 0;
            $attendance->early_leave_minutes = 0;
            $attendance->overtime_minutes = $attendance->worked_minutes;
            $attendance->status = $attendance->worked_minutes > 0 ? 'rest_day_worked' : 'rest_day';

            return;
        }

        $clockIn = $attendance->clock_in_at ? CarbonImmutable::parse($attendance->clock_in_at) : null;
        $clockOut = $attendance->clock_out_at ? CarbonImmutable::parse($attendance->clock_out_at) : null;
        $expectedStart = $attendance->expected_start_at ? CarbonImmutable::parse($attendance->expected_start_at) : null;
        $expectedEnd = $attendance->expected_end_at ? CarbonImmutable::parse($attendance->expected_end_at) : null;

        if (! $clockIn && ! $clockOut) {
            $attendance->late_minutes = 0;
            $attendance->early_leave_minutes = 0;
            $attendance->overtime_minutes = 0;
            $attendance->status = 'absence';

            return;
        }

        if (! $clockIn || ! $clockOut) {
            $attendance->late_minutes = $clockIn && $expectedStart
                ? self::positiveMinutesAfterTolerance($expectedStart, $clockIn, (int) ($schedule['tolerance_late_minutes'] ?? 0))
                : 0;

            $attendance->early_leave_minutes = 0;
            $attendance->overtime_minutes = 0;
            $attendance->status = 'incomplete';

            return;
        }

        $attendance->late_minutes = $expectedStart
            ? self::positiveMinutesAfterTolerance($expectedStart, $clockIn, (int) ($schedule['tolerance_late_minutes'] ?? 0))
            : 0;

        $attendance->early_leave_minutes = $expectedEnd
            ? self::positiveMinutesBeforeTolerance($clockOut, $expectedEnd, (int) ($schedule['tolerance_early_leave_minutes'] ?? 0))
            : 0;

        $expectedMinutes = (int) round(((float) ($attendance->expected_hours ?? 0)) * 60);
        $attendance->overtime_minutes = max(0, (int) ($attendance->worked_minutes ?? 0) - $expectedMinutes);

        if ($attendance->late_minutes > 0 && $attendance->early_leave_minutes > 0) {
            $attendance->status = 'late_early_leave';

            return;
        }

        if ($attendance->late_minutes > 0) {
            $attendance->status = 'late';

            return;
        }

        if ($attendance->early_leave_minutes > 0) {
            $attendance->status = 'early_leave';

            return;
        }

        $attendance->status = 'present';
    }

    protected static function calculateWorkedTime(EmployeeAttendance $attendance): void
    {
        $clockIn = $attendance->clock_in_at ? CarbonImmutable::parse($attendance->clock_in_at) : null;
        $clockOut = $attendance->clock_out_at ? CarbonImmutable::parse($attendance->clock_out_at) : null;

        if (! $clockIn || ! $clockOut) {
            $attendance->worked_minutes = 0;
            $attendance->worked_hours = 0;

            return;
        }

        if ($clockOut->lessThanOrEqualTo($clockIn)) {
            $clockOut = $clockOut->addDay();
        }

        $breakMinutes = max(0, (int) ($attendance->break_minutes ?? 0));
        $minutes = max(0, $clockIn->diffInMinutes($clockOut) - $breakMinutes);

        $attendance->worked_minutes = $minutes;
        $attendance->worked_hours = round($minutes / 60, 2);
    }

    protected static function positiveMinutesAfterTolerance(CarbonImmutable $expected, CarbonImmutable $actual, int $tolerance): int
    {
        $limit = $expected->addMinutes(max(0, $tolerance));

        return $actual->greaterThan($limit)
            ? $limit->diffInMinutes($actual)
            : 0;
    }

    protected static function positiveMinutesBeforeTolerance(CarbonImmutable $actual, CarbonImmutable $expected, int $tolerance): int
    {
        $limit = $expected->subMinutes(max(0, $tolerance));

        return $actual->lessThan($limit)
            ? $actual->diffInMinutes($limit)
            : 0;
    }
}
