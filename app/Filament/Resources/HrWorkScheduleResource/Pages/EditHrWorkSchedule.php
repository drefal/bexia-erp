<?php

namespace App\Filament\Resources\HrWorkScheduleResource\Pages;

use App\Filament\Resources\HrWorkScheduleResource;
use App\Models\HrWorkScheduleDay;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHrWorkSchedule extends EditRecord
{
    protected static string $resource = HrWorkScheduleResource::class;

    protected array $originalPolicy = [];

    protected $listeners = [
        'hr-work-schedule-header-refresh' => 'refreshHeaderSummary',
    ];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->record;

        $this->originalPolicy = [
            'schedule_type' => $record->getOriginal('schedule_type'),
            'start_time' => $record->getOriginal('start_time'),
            'end_time' => $record->getOriginal('end_time'),
            'break_minutes' => (int) ($record->getOriginal('break_minutes') ?? 0),
            'break_sessions_allowed' => (int) (
                $record->getOriginal('break_sessions_allowed') ?? 0
            ),
            'tolerance_late_minutes' => (int) (
                $record->getOriginal('tolerance_late_minutes') ?? 0
            ),
            'tolerance_early_leave_minutes' => (int) (
                $record->getOriginal('tolerance_early_leave_minutes') ?? 0
            ),
            'work_days' => $this->normalizeWorkDays(
                $record->getOriginal('work_days')
            ),
        ];

        if (($data['schedule_type'] ?? null) === 'open') {
            $data['end_time'] = null;
            $data['tolerance_early_leave_minutes'] = 0;
            $data['hours_per_day'] = 0;
            $data['hours_per_week'] = 0;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $schedule = $this->record->fresh();

        $newWorkDays = collect(
            $this->normalizeWorkDays($schedule->work_days)
        );

        $oldWorkDays = collect(
            $this->originalPolicy['work_days'] ?? []
        );

        $newBreakMinutes = max(
            0,
            (int) ($schedule->break_minutes ?? 0)
        );

        $newSessions = max(
            0,
            (int) ($schedule->break_sessions_allowed ?? 0)
        );

        $newTolIn = max(
            0,
            (int) ($schedule->tolerance_late_minutes ?? 0)
        );

        $isOpenSchedule =
            (string) ($schedule->schedule_type ?? '') === 'open';

        $newTolOut = $isOpenSchedule
            ? 0
            : max(
                0,
                (int) ($schedule->tolerance_early_leave_minutes ?? 0)
            );

        $oldStart = $this->normalizeTime(
            $this->originalPolicy['start_time'] ?? null
        );

        $oldEnd = $this->normalizeTime(
            $this->originalPolicy['end_time'] ?? null
        );

        $oldBreakMinutes = max(
            0,
            (int) ($this->originalPolicy['break_minutes'] ?? 0)
        );

        $oldSessions = max(
            0,
            (int) (
                $this->originalPolicy['break_sessions_allowed'] ?? 0
            )
        );

        $oldTolIn = max(
            0,
            (int) (
                $this->originalPolicy['tolerance_late_minutes'] ?? 0
            )
        );

        $oldTolOut = max(
            0,
            (int) (
                $this->originalPolicy['tolerance_early_leave_minutes'] ?? 0
            )
        );

        $days = HrWorkScheduleDay::query()
            ->where(
                'hr_work_schedule_id',
                $schedule->getKey()
            )
            ->get();

        foreach ($days as $day) {
            $dayName = (string) $day->day_of_week;
            $selectedNow = $newWorkDays->contains($dayName);
            $selectedBefore = $oldWorkDays->contains($dayName);

            if (! $selectedNow) {
                $day->is_working_day = false;
                $day->break_applies = false;
                $day->break_sessions_allowed = 0;
                $day->break_minutes = 0;
                $day->save();

                continue;
            }

            /*
             * Día recién activado:
             * inicializa con defaults actuales.
             */
            if (! $selectedBefore || ! (bool) $day->is_working_day) {
                $breakApplies =
                    $newBreakMinutes > 0
                    && $newSessions > 0;

                $day->forceFill([
                    'is_working_day' => true,
                    'start_time' => $schedule->start_time,
                    'end_time' => $isOpenSchedule
                        ? null
                        : $schedule->end_time,
                    'break_applies' => $breakApplies,
                    'break_sessions_allowed' => $breakApplies
                        ? $newSessions
                        : 0,
                    'break_minutes' => $breakApplies
                        ? $newBreakMinutes
                        : 0,
                    'tolerance_late_minutes' => $newTolIn,
                    'tolerance_early_leave_minutes' => $newTolOut,
                ])->save();

                continue;
            }

            /*
             * Día ya existente:
             * solo actualizamos campos que todavía coinciden con
             * el default anterior. Las excepciones se conservan.
             */
            $currentStart = $this->normalizeTime(
                $day->start_time
            );

            $currentEnd = $this->normalizeTime(
                $day->end_time
            );

            if ($currentStart === $oldStart) {
                $day->start_time = $schedule->start_time;
            }

            if ($isOpenSchedule) {
                $day->end_time = null;
            } elseif ($currentEnd === $oldEnd) {
                $day->end_time = $schedule->end_time;
            }

            $dayBreakMatchesOldDefault =
                (int) ($day->break_minutes ?? 0)
                    === $oldBreakMinutes
                && (int) ($day->break_sessions_allowed ?? 0)
                    === $oldSessions;

            if ($dayBreakMatchesOldDefault) {
                $breakApplies =
                    $newBreakMinutes > 0
                    && $newSessions > 0;

                $day->break_applies = $breakApplies;
                $day->break_minutes = $breakApplies
                    ? $newBreakMinutes
                    : 0;
                $day->break_sessions_allowed = $breakApplies
                    ? $newSessions
                    : 0;
            }

            if (
                (int) ($day->tolerance_late_minutes ?? 0)
                === $oldTolIn
            ) {
                $day->tolerance_late_minutes = $newTolIn;
            }

            if ($isOpenSchedule) {
                $day->tolerance_early_leave_minutes = 0;
            } elseif (
                (int) ($day->tolerance_early_leave_minutes ?? 0)
                === $oldTolOut
            ) {
                $day->tolerance_early_leave_minutes = $newTolOut;
            }

            $day->is_working_day = true;
            $day->save();
        }

        $this->recalculateSummary($schedule);

        $this->record->refresh();

        $this->dispatch('hr-work-schedule-days-refresh');
    }

    public function refreshHeaderSummary(): void
    {
        $this->record->refresh();

        $this->data['hours_per_day'] = number_format(
            (float) ($this->record->hours_per_day ?? 0),
            2,
            '.',
            ''
        );

        $this->data['hours_per_week'] = number_format(
            (float) ($this->record->hours_per_week ?? 0),
            2,
            '.',
            ''
        );

        $this->data['work_days'] = array_values(
            $this->normalizeWorkDays(
                $this->record->work_days
            )
        );
    }

    protected function recalculateSummary($schedule): void
    {
        $workingDays = HrWorkScheduleDay::query()
            ->where(
                'hr_work_schedule_id',
                $schedule->getKey()
            )
            ->where('is_working_day', true)
            ->orderBy('day_index')
            ->get();

        $isOpenSchedule =
            (string) ($schedule->schedule_type ?? '') === 'open';

        $weekHours = $isOpenSchedule
            ? 0.0
            : round(
                $workingDays->sum(
                    fn (HrWorkScheduleDay $day): float =>
                        (float) ($day->expected_hours ?? 0)
                ),
                2
            );

        $count = $workingDays->count();

        $averageHours = $count > 0
            ? round($weekHours / $count, 2)
            : 0;

        $workDays = $workingDays
            ->pluck('day_of_week')
            ->values()
            ->all();

        $schedule->forceFill([
            'work_days' => $workDays,
            'hours_per_day' => $averageHours,
            'hours_per_week' => $weekHours,
        ])->saveQuietly();
    }

    protected function normalizeWorkDays(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(
                array_filter($value)
            );
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return array_values(
                    array_filter($decoded)
                );
            }
        }

        return [];
    }

    protected function normalizeTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return substr((string) $value, 0, 8);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
