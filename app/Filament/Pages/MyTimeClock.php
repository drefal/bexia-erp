<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\HrAttendanceLocation;
use App\Support\Attendance\GeofenceDistance;
use App\Support\EmployeeAttendanceIncidentSync;
use App\Support\EmployeeWorkScheduleResolver;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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

    public ?float $browserLatitude = null;

    public ?float $browserLongitude = null;

    public ?int $browserAccuracy = null;

    public ?string $browserLocationError = null;

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
            ->with(['hrWorkSchedule', 'branch'])
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

    public function setBrowserLocation(?float $latitude = null, ?float $longitude = null, ?int $accuracy = null, ?string $error = null): void
    {
        $this->browserLatitude = $latitude;
        $this->browserLongitude = $longitude;
        $this->browserAccuracy = $accuracy;
        $this->browserLocationError = $error ? trim($error) : null;
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

        $locationPayload = $this->buildLocationPayload('clock_in');

        $attendance = $this->todayAttendance ?: new EmployeeAttendance();

        $payload = [
            'company_id' => $this->employee->company_id,
            'employee_id' => $this->employee->id,
            'attendance_date' => $this->todayDate,
            'clock_in_at' => now(),
            'source' => 'mobile',
            'notes' => $this->appendNote($attendance->notes ?? null, 'Entrada registrada desde Mi checador por usuario #' . auth()->id() . $this->locationNote($locationPayload)),
            'created_by_user_id' => $attendance->exists ? ($attendance->created_by_user_id ?: auth()->id()) : auth()->id(),
            'updated_by_user_id' => auth()->id(),
        ];

        $attendance->fill(array_merge($payload, $locationPayload));
        $attendance->save();

        $this->refreshClockState();

        Notification::make()
            ->title('Entrada registrada')
            ->body('Hora: ' . $attendance->clock_in_at?->format('H:i:s') . ' · Geocerca: ' . $this->locationStatusLabel($attendance->clock_in_location_status))
            ->color($this->locationNotificationColor($attendance->clock_in_location_status))
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

        $locationPayload = $this->buildLocationPayload('clock_out');

        $this->todayAttendance->forceFill(array_merge([
            'clock_out_at' => now(),
            'source' => $this->todayAttendance->source ?: 'mobile',
            'notes' => $this->appendNote($this->todayAttendance->notes ?? null, 'Salida registrada desde Mi checador por usuario #' . auth()->id() . $this->locationNote($locationPayload)),
            'updated_by_user_id' => auth()->id(),
        ], $locationPayload))->save();

        $this->refreshClockState();

        Notification::make()
            ->title('Salida registrada')
            ->body('Hora: ' . $this->todayAttendance->clock_out_at?->format('H:i:s') . ' · Geocerca: ' . $this->locationStatusLabel($this->todayAttendance->clock_out_location_status))
            ->color($this->locationNotificationColor($this->todayAttendance->clock_out_location_status))
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

    public function hasGeofenceConfigured(): bool
    {
        if (! $this->employee || ! Schema::hasTable('hr_attendance_locations')) {
            return false;
        }

        return $this->geofenceQuery()->exists();
    }

    public function locationStatusLabel(?string $status): string
    {
        return match ((string) $status) {
            'inside' => 'Dentro de geocerca',
            'outside' => 'Fuera de geocerca',
            'poor_accuracy' => 'Precisión GPS baja',
            'no_location' => 'Sin ubicación del celular',
            'no_geofence' => 'Sin geocerca configurada',
            'manual' => 'Manual',
            default => 'Sin validar',
        };
    }

    public function locationStatusColor(?string $status): string
    {
        return match ((string) $status) {
            'inside' => 'success',
            'outside', 'poor_accuracy' => 'warning',
            'no_location', 'no_geofence' => 'danger',
            default => 'gray',
        };
    }

    public function clockInLocationLabel(): string
    {
        return $this->locationStatusLabel($this->todayAttendance?->clock_in_location_status);
    }

    public function clockOutLocationLabel(): string
    {
        return $this->locationStatusLabel($this->todayAttendance?->clock_out_location_status);
    }

    public function browserLocationLabel(): string
    {
        if ($this->browserLatitude === null || $this->browserLongitude === null) {
            return $this->browserLocationError ?: 'Ubicación pendiente. Al checar se solicitará permiso de ubicación.';
        }

        $accuracy = $this->browserAccuracy !== null ? ' · precisión aprox. ' . $this->browserAccuracy . ' m' : '';

        return number_format($this->browserLatitude, 7) . ', ' . number_format($this->browserLongitude, 7) . $accuracy;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clock_in')
                ->label('Registrar entrada')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('success')
                ->visible(fn (): bool => $this->canClockIn())
                ->extraAttributes([
                    'x-on:click.prevent' => 'bexiaClockWithLocation("clockIn")',
                ]),

            Action::make('clock_out')
                ->label('Registrar salida')
                ->icon('heroicon-o-arrow-left-on-rectangle')
                ->color('warning')
                ->visible(fn (): bool => $this->canClockOut())
                ->extraAttributes([
                    'x-on:click.prevent' => 'bexiaClockWithLocation("clockOut")',
                ]),

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

    protected function geofenceQuery()
    {
        $query = HrAttendanceLocation::query()
            ->where('company_id', $this->employee->company_id)
            ->where('is_active', true)
            ->where('allow_mobile_clock_in', true);

        if ($this->employee->branch_id) {
            $query->where(function ($query): void {
                $query->where('branch_id', $this->employee->branch_id)
                    ->orWhereNull('branch_id');
            });
        }

        return $query;
    }

    protected function nearestGeofence(?float $latitude, ?float $longitude): ?array
    {
        if ($latitude === null || $longitude === null || ! $this->employee || ! Schema::hasTable('hr_attendance_locations')) {
            return null;
        }

        $locations = $this->geofenceQuery()->get();

        if ($locations->isEmpty()) {
            return null;
        }

        $nearest = null;

        foreach ($locations as $location) {
            $distance = GeofenceDistance::meters(
                $latitude,
                $longitude,
                (float) $location->latitude,
                (float) $location->longitude,
            );

            if (! $nearest || $distance < $nearest['distance_meters']) {
                $nearest = [
                    'location' => $location,
                    'distance_meters' => $distance,
                ];
            }
        }

        return $nearest;
    }

    protected function buildLocationPayload(string $direction): array
    {
        $prefix = $direction === 'clock_out' ? 'clock_out' : 'clock_in';
        $latitude = $this->browserLatitude;
        $longitude = $this->browserLongitude;
        $accuracy = $this->browserAccuracy;

        $payload = [
            $prefix . '_method' => 'mobile',
            $prefix . '_ip_address' => request()->ip(),
            $prefix . '_user_agent' => substr((string) request()->userAgent(), 0, 1000),
        ];

        if ($latitude === null || $longitude === null) {
            $payload[$prefix . '_location_status'] = 'no_location';
            $payload['mobile_review_status'] = 'pending';

            return $payload;
        }

        $payload[$prefix . '_latitude'] = $latitude;
        $payload[$prefix . '_longitude'] = $longitude;
        $payload[$prefix . '_accuracy_meters'] = $accuracy;

        $nearest = $this->nearestGeofence($latitude, $longitude);

        if (! $nearest) {
            $payload[$prefix . '_location_status'] = 'no_geofence';
            $payload['mobile_review_status'] = 'pending';

            return $payload;
        }

        /** @var HrAttendanceLocation $location */
        $location = $nearest['location'];
        $distance = (int) $nearest['distance_meters'];
        $status = GeofenceDistance::status(
            $distance,
            (int) $location->radius_meters,
            $accuracy,
            $location->accuracy_required_meters ? (int) $location->accuracy_required_meters : null,
        );

        $payload[$prefix . '_hr_attendance_location_id'] = $location->id;
        $payload[$prefix . '_distance_meters'] = $distance;
        $payload[$prefix . '_location_status'] = $status;

        $payload['mobile_review_status'] = $status === 'inside'
            ? ($this->todayAttendance?->mobile_review_status ?: 'accepted')
            : 'pending';

        return $payload;
    }

    protected function appendNote(?string $current, string $line): string
    {
        return trim(trim((string) $current) . PHP_EOL . $line);
    }

    protected function locationNote(array $payload): string
    {
        $status = (string) ($payload['clock_in_location_status'] ?? $payload['clock_out_location_status'] ?? '');
        $distance = $payload['clock_in_distance_meters'] ?? $payload['clock_out_distance_meters'] ?? null;

        $note = ' · Geocerca: ' . $this->locationStatusLabel($status);

        if ($distance !== null) {
            $note .= ' · Distancia: ' . $distance . ' m';
        }

        return $note;
    }

    protected function locationNotificationColor(?string $status): string
    {
        return match ((string) $status) {
            'inside' => 'success',
            'outside', 'poor_accuracy' => 'warning',
            default => 'danger',
        };
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
