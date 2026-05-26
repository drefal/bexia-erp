<?php

namespace Database\Seeders;

use App\Models\HrWorkSchedule;
use App\Models\HrWorkScheduleDay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class V56514bHrWorkScheduleDaysSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('hr_work_schedules') || ! Schema::hasTable('hr_work_schedule_days')) {
            return;
        }

        $days = HrWorkScheduleDay::DAYS;

        HrWorkSchedule::query()
            ->orderBy('id')
            ->get()
            ->each(function (HrWorkSchedule $schedule) use ($days): void {
                $workDays = is_array($schedule->work_days) ? $schedule->work_days : [];

                foreach ($days as $day => $index) {
                    $isWorkingDay = in_array($day, $workDays, true);
                    $breakMinutes = $isWorkingDay
                        ? $this->inferBreakMinutes($schedule)
                        : 0;

                    HrWorkScheduleDay::query()->updateOrCreate(
                        [
                            'hr_work_schedule_id' => $schedule->id,
                            'day_of_week' => $day,
                        ],
                        [
                            'company_id' => $schedule->company_id,
                            'day_index' => $index,
                            'is_working_day' => $isWorkingDay,
                            'start_time' => $isWorkingDay ? $schedule->start_time : null,
                            'end_time' => $isWorkingDay ? $schedule->end_time : null,
                            'break_minutes' => $breakMinutes,
                            'tolerance_late_minutes' => $isWorkingDay ? 5 : 0,
                            'tolerance_early_leave_minutes' => 0,
                            'expected_hours' => $isWorkingDay
                                ? ($schedule->hours_per_day ?: HrWorkScheduleDay::calculateExpectedHours($schedule->start_time, $schedule->end_time, $breakMinutes))
                                : 0,
                            'notes' => $isWorkingDay ? null : 'Descanso',
                        ]
                    );
                }
            });
    }

    protected function inferBreakMinutes(HrWorkSchedule $schedule): int
    {
        if (blank($schedule->start_time) || blank($schedule->end_time) || blank($schedule->hours_per_day)) {
            return 0;
        }

        try {
            $date = '2026-01-01 ';
            $start = CarbonImmutable::parse($date . $schedule->start_time);
            $end = CarbonImmutable::parse($date . $schedule->end_time);

            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->addDay();
            }

            $totalMinutes = $start->diffInMinutes($end);
            $expectedMinutes = (float) $schedule->hours_per_day * 60;

            return max(0, (int) round($totalMinutes - $expectedMinutes));
        } catch (\Throwable) {
            return 0;
        }
    }
}
