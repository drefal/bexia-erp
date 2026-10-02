<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\HrWorkSchedule;
use App\Models\HrWorkScheduleDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeWorkScheduleResolver
{
    /**
     * Flexible / Solo asistencia.
     *
     * Smallint max. Se usa únicamente en el snapshot de asistencia para
     * permitir salidas intermedias sin imponer un límite operativo normal.
     */
    public const FLEXIBLE_BREAK_SESSIONS = 32767;

    public static function scheduleForEmployee(Employee $employee, mixed $date): ?array
    {
        $date = CarbonImmutable::parse($date)->startOfDay();

        $schedule = self::resolveSchedule($employee);

        if (! $schedule) {
            return null;
        }

        $dayKey = strtolower($date->format('l'));

        if (Schema::hasTable('hr_work_schedule_days')) {
            $detail = HrWorkScheduleDay::query()
                ->where('hr_work_schedule_id', $schedule->id)
                ->where('day_of_week', $dayKey)
                ->first();

            if ($detail) {
                return self::buildResultFromDetail($employee, $schedule, $detail, $date);
            }
        }

        return self::buildResultFromScheduleFallback($employee, $schedule, $dayKey, $date);
    }

    public static function resolveSchedule(Employee $employee): ?HrWorkSchedule
    {
        if ($employee->relationLoaded('hrWorkSchedule') && $employee->hrWorkSchedule) {
            return $employee->hrWorkSchedule;
        }

        if ($employee->hr_work_schedule_id) {
            return HrWorkSchedule::query()->find($employee->hr_work_schedule_id);
        }

        if (Schema::hasTable('employee_contracts')) {
            $scheduleId = DB::table('employee_contracts')
                ->where('employee_id', $employee->id)
                ->where('is_current', true)
                ->whereNotNull('hr_work_schedule_id')
                ->orderByDesc('start_date')
                ->value('hr_work_schedule_id');

            if ($scheduleId) {
                return HrWorkSchedule::query()->find($scheduleId);
            }
        }

        return null;
    }

    public static function isWorkingDay(Employee $employee, mixed $date): bool
    {
        $result = self::scheduleForEmployee($employee, $date);

        return (bool) ($result['is_working_day'] ?? false);
    }

    public static function expectedStartAt(Employee $employee, mixed $date): ?CarbonImmutable
    {
        $result = self::scheduleForEmployee($employee, $date);

        return $result['start_at'] ?? null;
    }

    public static function expectedEndAt(Employee $employee, mixed $date): ?CarbonImmutable
    {
        $result = self::scheduleForEmployee($employee, $date);

        return $result['end_at'] ?? null;
    }

    protected static function buildResultFromDetail(
        Employee $employee,
        HrWorkSchedule $schedule,
        HrWorkScheduleDay $detail,
        CarbonImmutable $date,
    ): array {
        $isWorkingDay = (bool) $detail->is_working_day;

        $isFlexibleSchedule =
            (string) ($schedule->schedule_type ?? '') === 'flexible';

        $startAt =
            ! $isFlexibleSchedule
            && $isWorkingDay
            && filled($detail->start_time)
            ? self::combineDateAndTime($date, (string) $detail->start_time)
            : null;

        $endAt =
            ! $isFlexibleSchedule
            && $isWorkingDay
            && filled($detail->end_time)
            ? self::combineDateAndTime($date, (string) $detail->end_time)
            : null;

        if ($startAt && $endAt && $endAt->lessThanOrEqualTo($startAt)) {
            $endAt = $endAt->addDay();
        }

        $breakApplies =
            $isWorkingDay
            && (
                $isFlexibleSchedule
                || (bool) ($detail->break_applies ?? false)
            );

        $breakMinutes = $isFlexibleSchedule
            ? 0
            : (
                $breakApplies
                    ? max(0, (int) ($schedule->break_minutes ?? 0))
                    : 0
            );

        $breakSessionsAllowed =
            $isFlexibleSchedule && $isWorkingDay
                ? self::FLEXIBLE_BREAK_SESSIONS
                : (
                    $breakApplies
                        ? max(
                            0,
                            (int) ($detail->break_sessions_allowed ?? 0)
                        )
                        : 0
                );

        $expectedHours = $isFlexibleSchedule
            ? 0.0
            : (
                $isWorkingDay
                    ? (float) (
                        HrWorkScheduleDay::calculateExpectedHours(
                            $detail->start_time,
                            $detail->end_time,
                            $breakMinutes,
                        ) ?? 0
                    )
                    : 0.0
            );

        return [
            'employee_id' => $employee->id,
            'schedule_id' => $schedule->id,
            'schedule_name' => $schedule->name,
            'schedule_type' => (string) ($schedule->schedule_type ?? 'fixed'),
            'date' => $date->toDateString(),
            'day_of_week' => $detail->day_of_week,
            'is_working_day' => $isWorkingDay,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'start_time' => $detail->start_time,
            'end_time' => $detail->end_time,
            'break_applies' => $breakApplies,
            'break_minutes' => $breakMinutes,
            'break_sessions_allowed' => $breakSessionsAllowed,
            'expected_hours' => $expectedHours,
            'tolerance_late_minutes' => $isFlexibleSchedule
                ? 0
                : (int) ($detail->tolerance_late_minutes ?? $schedule->tolerance_late_minutes ?? 0),
            'tolerance_early_leave_minutes' => $isFlexibleSchedule
                ? 0
                : (int) ($detail->tolerance_early_leave_minutes ?? $schedule->tolerance_early_leave_minutes ?? 0),
            'source' => 'detail',
        ];
    }

    protected static function buildResultFromScheduleFallback(
        Employee $employee,
        HrWorkSchedule $schedule,
        string $dayKey,
        CarbonImmutable $date,
    ): array {
        $workDays = is_array($schedule->work_days) ? $schedule->work_days : [];
        $isWorkingDay = in_array($dayKey, $workDays, true);

        $isFlexibleSchedule =
            (string) ($schedule->schedule_type ?? '') === 'flexible';

        $startAt =
            ! $isFlexibleSchedule
            && $isWorkingDay
            && filled($schedule->start_time)
            ? self::combineDateAndTime($date, (string) $schedule->start_time)
            : null;

        $endAt =
            ! $isFlexibleSchedule
            && $isWorkingDay
            && filled($schedule->end_time)
            ? self::combineDateAndTime($date, (string) $schedule->end_time)
            : null;

        if ($startAt && $endAt && $endAt->lessThanOrEqualTo($startAt)) {
            $endAt = $endAt->addDay();
        }

        return [
            'employee_id' => $employee->id,
            'schedule_id' => $schedule->id,
            'schedule_name' => $schedule->name,
            'schedule_type' => (string) ($schedule->schedule_type ?? 'fixed'),
            'date' => $date->toDateString(),
            'day_of_week' => $dayKey,
            'is_working_day' => $isWorkingDay,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'break_applies' =>
                $isWorkingDay
                && (
                    $isFlexibleSchedule
                    || (
                        (int) ($schedule->break_minutes ?? 0) > 0
                        && (int) ($schedule->break_sessions_allowed ?? 0) > 0
                    )
                ),
            'break_minutes' => $isFlexibleSchedule
                ? 0
                : (
                    $isWorkingDay
                        ? max(0, (int) ($schedule->break_minutes ?? 0))
                        : 0
                ),
            'break_sessions_allowed' =>
                $isFlexibleSchedule && $isWorkingDay
                    ? self::FLEXIBLE_BREAK_SESSIONS
                    : (
                        $isWorkingDay
                            ? max(0, (int) ($schedule->break_sessions_allowed ?? 0))
                            : 0
                    ),
            'expected_hours' => $isFlexibleSchedule
                ? 0.0
                : (
                    $isWorkingDay
                        ? (float) ($schedule->hours_per_day ?? 0)
                        : 0.0
                ),
            'tolerance_late_minutes' => $isFlexibleSchedule
                ? 0
                : max(0, (int) ($schedule->tolerance_late_minutes ?? 0)),
            'tolerance_early_leave_minutes' => $isFlexibleSchedule
                ? 0
                : max(0, (int) ($schedule->tolerance_early_leave_minutes ?? 0)),
            'source' => 'schedule',
        ];
    }

    protected static function combineDateAndTime(CarbonImmutable $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString() . ' ' . $time);
    }
}
