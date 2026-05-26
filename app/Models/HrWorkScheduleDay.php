<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrWorkScheduleDay extends Model
{
    protected $fillable = [
        'company_id',
        'hr_work_schedule_id',
        'day_of_week',
        'day_index',
        'is_working_day',
        'start_time',
        'end_time',
        'break_minutes',
        'tolerance_late_minutes',
        'tolerance_early_leave_minutes',
        'expected_hours',
        'notes',
    ];

    protected $casts = [
        'is_working_day' => 'boolean',
        'break_minutes' => 'integer',
        'tolerance_late_minutes' => 'integer',
        'tolerance_early_leave_minutes' => 'integer',
        'expected_hours' => 'decimal:2',
    ];

    public const DAYS = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 7,
    ];

    public const DAY_LABELS = [
        'monday' => 'Lunes',
        'tuesday' => 'Martes',
        'wednesday' => 'Miércoles',
        'thursday' => 'Jueves',
        'friday' => 'Viernes',
        'saturday' => 'Sábado',
        'sunday' => 'Domingo',
    ];

    protected static function booted(): void
    {
        static::saving(function (HrWorkScheduleDay $day): void {
            $day->day_of_week = strtolower((string) $day->day_of_week);
            $day->day_index = self::DAYS[$day->day_of_week] ?? $day->day_index ?? 1;

            if (blank($day->company_id) && $day->hr_work_schedule_id) {
                $day->company_id = HrWorkSchedule::query()
                    ->whereKey($day->hr_work_schedule_id)
                    ->value('company_id');
            }

            if ($day->is_working_day && blank($day->expected_hours)) {
                $day->expected_hours = self::calculateExpectedHours(
                    $day->start_time,
                    $day->end_time,
                    (int) ($day->break_minutes ?? 0),
                );
            }

            if (! $day->is_working_day) {
                $day->expected_hours = 0;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(HrWorkSchedule::class, 'hr_work_schedule_id');
    }

    public static function calculateExpectedHours(?string $startTime, ?string $endTime, int $breakMinutes = 0): ?float
    {
        if (blank($startTime) || blank($endTime)) {
            return null;
        }

        try {
            $date = '2026-01-01 ';
            $start = CarbonImmutable::parse($date . $startTime);
            $end = CarbonImmutable::parse($date . $endTime);

            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->addDay();
            }

            $minutes = max(0, $start->diffInMinutes($end) - max(0, $breakMinutes));

            return round($minutes / 60, 2);
        } catch (\Throwable) {
            return null;
        }
    }
}
