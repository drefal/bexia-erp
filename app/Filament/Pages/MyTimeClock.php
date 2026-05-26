<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Support\EmployeeAttendanceIncidentSync;
use App\Support\EmployeeWorkScheduleResolver;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class MyTimeClock extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Mi portal';

    protected static ?string $navigationLabel = 'Mi checador';

    protected static ?string $title = 'Mi checador';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.my-time-clock';

    public ?Employee $employee = null;

    public ?EmployeeAttendance $todayAttendance = null;

    public ?array $todaySchedule = null;

    public string $todayDate = '';

    public string $currentTime = '';

    public static function shouldRegisterNavigation(): bool
    {
        return static::employeeForCurrentUserQuery()->exists();
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->refreshClockState();
    }

    public function refreshClockState(): void
    {
        $this->todayDate = now()->toDateString();
        $this->currentTime = now()->format('H:i:s');

        $this->employee = static::employeeForCurrentUserQuery()
            ->with(['hrWorkSchedule'])
            ->first();

        if (! $this->employee) {
            $this->todayAttendance = null;
            $this->todaySchedule = null;

            return;
        }

        $this->todaySchedule = EmployeeWorkScheduleResolver::scheduleForEmployee($this->employee, now());

        $this->todayAttendance = EmployeeAttendance::query()
            ->where('employee_id', $this->employee->id)
            ->whereDate('attendance_date', $this->todayDate)
            ->first();
    }

    public function clockIn(): void
    {
        $this->refreshClockState();

        if (! $this->employee) {
            Notification::make()
                ->title('No tienes empleado ligado')
                ->body('Tu usuario no está relacionado con un empleado activo.')
                ->danger()
                ->send();

            return;
        }

        if ($this->todayAttendance?->clock_in_at) {
            Notification::make()
                ->title('Entrada ya registrada')
                ->body('Ya tienes una entrada registrada para hoy.')
                ->warning()
                ->send();

            return;
        }

        $attendance = $this->todayAttendance ?: new EmployeeAttendance();

        $attendance->fill([
            'company_id' => $this->employee->company_id,
            'employee_id' => $this->employee->id,
            'attendance_date' => $this->todayDate,
            'clock_in_at' => now(),
            'source' => 'clock',
            'notes' => trim((string) (($attendance->notes ?? '') . PHP_EOL . 'Entrada registrada desde Mi checador por usuario #' . auth()->id())),
            'created_by_user_id' => $attendance->exists ? ($attendance->created_by_user_id ?: auth()->id()) : auth()->id(),
            'updated_by_user_id' => auth()->id(),
        ]);

        $attendance->save();

        $this->refreshClockState();

        Notification::make()
            ->title('Entrada registrada')
            ->body('Hora: ' . $attendance->clock_in_at?->format('H:i:s') . ' · Estado: ' . $this->statusLabel($attendance->status))
            ->success()
            ->send();
    }

    public function clockOut(): void
    {
        $this->refreshClockState();

        if (! $this->employee) {
            Notification::make()
                ->title('No tienes empleado ligado')
                ->danger()
                ->send();

            return;
        }

        if (! $this->todayAttendance?->clock_in_at) {
            Notification::make()
                ->title('Primero registra entrada')
                ->body('No puedes registrar salida sin una entrada previa.')
                ->warning()
                ->send();

            return;
        }

        if ($this->todayAttendance->clock_out_at) {
            Notification::make()
                ->title('Salida ya registrada')
                ->body('Ya tienes una salida registrada para hoy.')
                ->warning()
                ->send();

            return;
        }

        $this->todayAttendance->forceFill([
            'clock_out_at' => now(),
            'source' => $this->todayAttendance->source ?: 'clock',
            'notes' => trim((string) (($this->todayAttendance->notes ?? '') . PHP_EOL . 'Salida registrada desde Mi checador por usuario #' . auth()->id())),
            'updated_by_user_id' => auth()->id(),
        ])->save();

        $this->refreshClockState();

        Notification::make()
            ->title('Salida registrada')
            ->body('Hora: ' . $this->todayAttendance->clock_out_at?->format('H:i:s') . ' · Estado: ' . $this->statusLabel($this->todayAttendance->status))
            ->success()
            ->send();
    }

    public function generateIncident(): void
    {
        $this->refreshClockState();

        if (! $this->todayAttendance) {
            Notification::make()
                ->title('No hay asistencia de hoy')
                ->warning()
                ->send();

            return;
        }

        try {
            $incident = EmployeeAttendanceIncidentSync::sync($this->todayAttendance, auth()->id(), true);

            if (! $incident) {
                Notification::make()
                    ->title('No aplica incidencia')
                    ->body('La asistencia de hoy no corresponde a Retardo o Falta.')
                    ->warning()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Incidencia generada')
                ->body('Incidencia #' . $incident->id . ' · Estado: ' . $incident->status)
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo generar la incidencia')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->refreshClockState();
    }

    public function latestAttendances(): Collection
    {
        if (! $this->employee) {
            return collect();
        }

        return EmployeeAttendance::query()
            ->where('employee_id', $this->employee->id)
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    public function expectedStartLabel(): string
    {
        return $this->formatScheduleDateTime($this->todaySchedule['start_at'] ?? null);
    }

    public function expectedEndLabel(): string
    {
        return $this->formatScheduleDateTime($this->todaySchedule['end_at'] ?? null);
    }

    public function todayIsWorkingDay(): bool
    {
        return (bool) ($this->todaySchedule['is_working_day'] ?? false);
    }

    public function scheduleName(): string
    {
        return (string) ($this->todaySchedule['schedule_name'] ?? 'Sin horario');
    }

    public function statusLabel(?string $status): string
    {
        return EmployeeAttendance::statusOptions()[$status] ?? ($status ?: '-');
    }

    public function canClockIn(): bool
    {
        return (bool) $this->employee && blank($this->todayAttendance?->clock_in_at);
    }

    public function canClockOut(): bool
    {
        return (bool) $this->employee
            && filled($this->todayAttendance?->clock_in_at)
            && blank($this->todayAttendance?->clock_out_at);
    }

    public function canGenerateIncident(): bool
    {
        return $this->todayAttendance
            && EmployeeAttendanceIncidentSync::isEligible($this->todayAttendance);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clock_in')
                ->label('Registrar entrada')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('success')
                ->visible(fn (): bool => $this->canClockIn())
                ->action('clockIn'),

            Action::make('clock_out')
                ->label('Registrar salida')
                ->icon('heroicon-o-arrow-left-on-rectangle')
                ->color('warning')
                ->visible(fn (): bool => $this->canClockOut())
                ->requiresConfirmation()
                ->action('clockOut'),

            Action::make('generate_incident')
                ->label('Generar incidencia')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->visible(fn (): bool => $this->canGenerateIncident())
                ->requiresConfirmation()
                ->action('generateIncident'),
        ];
    }

    protected static function employeeForCurrentUserQuery()
    {
        return Employee::query()
            ->where('user_id', auth()->id())
            ->where('active', true)
            ->orderBy('id');
    }

    protected function formatScheduleDateTime(mixed $value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return CarbonImmutable::parse($value)->format('H:i');
        } catch (\Throwable) {
            return '-';
        }
    }
}
