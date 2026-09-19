<?php

namespace App\Support;

use App\Models\EmployeeAttendance;
use App\Models\EmployeeIncident;
use App\Models\HrIncidentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeAttendanceIncidentSync
{
    public static function sync(EmployeeAttendance $attendance, ?int $userId = null, bool $sendToApproval = true): ?EmployeeIncident
    {
        $attendance->refresh();

        $code = static::incidentCodeForAttendance($attendance);

        if (! $code) {
            return null;
        }

        $type = static::incidentType($attendance, $code);

        if (! $type) {
            throw new \RuntimeException('No existe un tipo de incidencia activo con código ' . $code . ' para esta empresa.');
        }

        return DB::transaction(function () use ($attendance, $type, $code, $userId, $sendToApproval): EmployeeIncident {
            $userId = $userId ?: auth()->id();
            $existing = static::existingIncident($attendance, $type);

            if ($existing) {
                $existing = static::refreshExistingIncident($existing, $attendance, $type, $code, $userId);

                static::sendIfNeeded($existing->fresh(), $type, $sendToApproval);

                return $existing->fresh();
            }

            $incident = EmployeeIncident::query()->create(static::incidentPayload($attendance, $type, $code, $userId));

            static::sendIfNeeded($incident->fresh(), $type, $sendToApproval);

            return $incident->fresh();
        });
    }

    public static function syncAll(EmployeeAttendance $attendance, ?int $userId = null, bool $sendToApproval = true): array
    {
        $attendance->refresh();

        $incidents = [];

        foreach (static::incidentCodesForAttendance($attendance) as $code) {
            $type = static::incidentType($attendance, $code);

            if (! $type) {
                throw new \RuntimeException('No existe un tipo de incidencia activo con código ' . $code . ' para esta empresa.');
            }

            $incidents[$code] = DB::transaction(function () use ($attendance, $type, $code, $userId, $sendToApproval): EmployeeIncident {
                $resolvedUserId = $userId ?: auth()->id();
                $existing = static::existingIncident($attendance, $type);

                if ($existing) {
                    $existing = static::refreshExistingIncident($existing, $attendance, $type, $code, $resolvedUserId);

                    static::sendIfNeeded($existing->fresh(), $type, $sendToApproval);

                    return $existing->fresh();
                }

                $incident = EmployeeIncident::query()->create(static::incidentPayload($attendance, $type, $code, $resolvedUserId));

                static::sendIfNeeded($incident->fresh(), $type, $sendToApproval);

                return $incident->fresh();
            });
        }

        return $incidents;
    }

    public static function incidentCodesForAttendance(EmployeeAttendance $attendance): array
    {
        $status = (string) $attendance->status;
        $codes = [];

        if ($status === 'absence') {
            return ['FALTA'];
        }

        if (in_array($status, ['late', 'late_early_leave'], true) || (int) $attendance->late_minutes > 0) {
            $codes[] = 'RETARDO';
        }

        if (in_array($status, ['early_leave', 'late_early_leave'], true) || (int) $attendance->early_leave_minutes > 0) {
            $codes[] = 'SALIDA_TEMPRANA';
        }

        if (static::incompleteWorkdayMinutes($attendance) > 0) {
            $codes[] = 'JORNADA_INCOMPLETA';
        }

        return array_values(array_unique($codes));
    }

    public static function incidentCodeForAttendance(EmployeeAttendance $attendance): ?string
    {
        return static::incidentCodesForAttendance($attendance)[0] ?? null;
    }

    public static function isEligible(EmployeeAttendance $attendance): bool
    {
        return static::incidentCodeForAttendance($attendance) !== null;
    }

    protected static function incidentType(EmployeeAttendance $attendance, string $code): ?HrIncidentType
    {
        return HrIncidentType::query()
            ->where('company_id', $attendance->company_id)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    protected static function existingIncident(EmployeeAttendance $attendance, HrIncidentType $type): ?EmployeeIncident
    {
        $query = EmployeeIncident::query()
            ->where('company_id', $attendance->company_id)
            ->where('employee_id', $attendance->employee_id)
            ->where('hr_incident_type_id', $type->id)
            ->whereDate('start_date', $attendance->attendance_date);

        if (Schema::hasColumn('employee_incidents', 'employee_attendance_id')) {
            $query->where(function ($query) use ($attendance): void {
                $query->where('employee_attendance_id', $attendance->id)
                    ->orWhereNull('employee_attendance_id');
            });
        }

        return $query->orderByDesc('id')->first();
    }

    protected static function incidentPayload(EmployeeAttendance $attendance, HrIncidentType $type, string $code, ?int $userId): array
    {
        $date = $attendance->attendance_date?->toDateString();

        $payload = [
            'company_id' => $attendance->company_id,
            'employee_id' => $attendance->employee_id,
            'hr_incident_type_id' => $type->id,
            'title' => static::title($attendance, $code),
            'status' => ((bool) $type->requires_approval) ? 'draft' : 'approved',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => $attendance->clock_in_at?->format('H:i:s'),
            'end_time' => $attendance->clock_out_at?->format('H:i:s'),
            'quantity' => static::quantity($attendance, $code),
            'quantity_unit' => static::quantityUnit($code),
            'affects_payroll' => (bool) $type->affects_payroll,
            'payroll_amount' => null,
            'requires_approval' => (bool) $type->requires_approval,
            'approved_by_user_id' => ((bool) $type->requires_approval) ? null : $userId,
            'approved_at' => ((bool) $type->requires_approval) ? null : now(),
            'description' => static::description($attendance, $code),
            'resolution_notes' => null,
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ];

        if (Schema::hasColumn('employee_incidents', 'employee_attendance_id')) {
            $payload['employee_attendance_id'] = $attendance->id;
        }

        return $payload;
    }

    protected static function sendIfNeeded(EmployeeIncident $incident, HrIncidentType $type, bool $sendToApproval): void
    {
        if (! $sendToApproval || ! (bool) $type->requires_approval) {
            return;
        }

        if (! class_exists(EmployeeIncidentApprovalWorkflow::class)) {
            return;
        }

        if (! in_array((string) $incident->status, ['draft', 'rejected'], true)) {
            return;
        }

        EmployeeIncidentApprovalWorkflow::sendToApproval($incident);
    }

    protected static function title(EmployeeAttendance $attendance, string $code): string
    {
        $date = $attendance->attendance_date?->format('d/m/Y') ?: 'sin fecha';

        return match ($code) {
            'RETARDO' => 'Retardo - ' . $date,
            'SALIDA_TEMPRANA' => 'Salida temprana - ' . $date,
            'JORNADA_INCOMPLETA' => 'Jornada incompleta - ' . $date,
            'FALTA' => 'Falta - ' . $date,
            default => 'Incidencia asistencia - ' . $date,
        };
    }

    protected static function quantity(EmployeeAttendance $attendance, string $code): float
    {
        return match ($code) {
            'RETARDO' => (float) max(1, (int) $attendance->late_minutes),
            'SALIDA_TEMPRANA' => (float) max(1, (int) $attendance->early_leave_minutes),
            'JORNADA_INCOMPLETA' => (float) max(1, static::incompleteWorkdayMinutes($attendance)),
            'FALTA' => 1.0,
            default => 1.0,
        };
    }

    protected static function quantityUnit(string $code): string
    {
        return match ($code) {
            'RETARDO', 'SALIDA_TEMPRANA', 'JORNADA_INCOMPLETA' => 'minutes',
            'FALTA' => 'days',
            default => 'units',
        };
    }

    protected static function description(EmployeeAttendance $attendance, string $code): string
    {
        $parts = [
            'Generada automáticamente desde asistencia #' . $attendance->id . '.',
            'Fecha: ' . ($attendance->attendance_date?->format('d/m/Y') ?: 'sin fecha') . '.',
            'Estado de asistencia: ' . $attendance->status . '.',
            'Entrada esperada: ' . ($attendance->expected_start_at?->format('H:i') ?: '-'),
            'Entrada real: ' . ($attendance->clock_in_at?->format('H:i') ?: '-'),
            'Salida esperada: ' . ($attendance->expected_end_at?->format('H:i') ?: '-'),
            'Salida real: ' . ($attendance->clock_out_at?->format('H:i') ?: '-'),
            'Retardo: ' . (int) $attendance->late_minutes . ' min.',
            'Salida temprana: ' . (int) $attendance->early_leave_minutes . ' min.',
            'Horas trabajadas: ' . number_format((float) $attendance->worked_hours, 2) . '.',
            'Horas extra: ' . (int) $attendance->overtime_minutes . ' min.',
        ];

        if ($code === 'JORNADA_INCOMPLETA') {
            $expected = static::expectedWorkMinutes($attendance);
            $worked = (int) round((float) ($attendance->worked_minutes ?? 0));
            $missing = max(0, $expected - $worked);

            $parts[] = 'Jornada esperada: ' . $expected . ' min.';
            $parts[] = 'Jornada trabajada: ' . $worked . ' min.';
            $parts[] = 'Minutos no cubiertos: ' . $missing . ' min.';
        }

        if ($code === 'FALTA') {
            $parts[] = 'La asistencia no tiene entrada ni salida en un día laborable.';
        }

        return implode(PHP_EOL, $parts) . static::attendanceGeofenceSummary($attendance);
    }
    protected static function attendanceGeofenceSummary($attendance): string
    {
        $lines = [];

        $formatSide = function (string $label, string $prefix) use ($attendance): ?string {
            $status = $attendance->{$prefix . '_location_status'} ?? null;
            $distance = $attendance->{$prefix . '_distance_meters'} ?? null;
            $locationId = $attendance->{$prefix . '_hr_attendance_location_id'} ?? null;
            $accuracy = $attendance->{$prefix . '_accuracy_meters'} ?? null;

            if (! $status && $distance === null && ! $locationId) {
                return null;
            }

            $statusLabel = match ((string) $status) {
                'inside' => 'Dentro de geocerca',
                'outside' => 'Fuera de geocerca',
                'no_location' => 'Sin ubicación GPS',
                'no_geofence' => 'Sin geocerca aplicable',
                default => $status ? (string) $status : 'Sin estado',
            };

            $locationName = null;
            $radius = null;

            if ($locationId) {
                $location = \App\Models\HrAttendanceLocation::query()->find($locationId);

                if ($location) {
                    $locationName = $location->name;
                    $radius = $location->radius_meters;
                }
            }

            $parts = [];
            $parts[] = $label . ': ' . $statusLabel;

            if ($locationName) {
                $parts[] = 'Geocerca: ' . $locationName;
            }

            if ($distance !== null) {
                $parts[] = 'Distancia: ' . (int) $distance . ' m';
            }

            if ($radius !== null) {
                $parts[] = 'Radio permitido: ' . (int) $radius . ' m';

                if ($distance !== null && (int) $distance > (int) $radius) {
                    $parts[] = 'Excedente: ' . ((int) $distance - (int) $radius) . ' m';
                }
            }

            if ($accuracy !== null) {
                $parts[] = 'Precisión GPS: ' . (int) $accuracy . ' m';
            }

            return implode('. ', $parts) . '.';
        };

        foreach ([
            ['Entrada', 'clock_in'],
            ['Salida', 'clock_out'],
        ] as [$label, $prefix]) {
            $line = $formatSide($label, $prefix);

            if ($line) {
                $lines[] = $line;
            }
        }

        if ($lines === []) {
            return '';
        }

        return PHP_EOL . PHP_EOL . 'Detalle geocerca / ubicación:' . PHP_EOL . implode(PHP_EOL, $lines);
    }


    protected static function expectedWorkMinutes(EmployeeAttendance $attendance): int
    {
        if ((float) ($attendance->expected_hours ?? 0) > 0) {
            return (int) round(((float) $attendance->expected_hours) * 60);
        }

        try {
            $employee = $attendance->employee ?? \App\Models\Employee::query()->find($attendance->employee_id);

            if (! $employee || ! $employee->hr_work_schedule_id) {
                return 0;
            }

            $schedule = DB::table('hr_work_schedules')
                ->where('id', $employee->hr_work_schedule_id)
                ->first();

            if (! $schedule) {
                return 0;
            }

            if (isset($schedule->hours_per_day) && is_numeric($schedule->hours_per_day) && (float) $schedule->hours_per_day > 0) {
                return (int) round(((float) $schedule->hours_per_day) * 60);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return 0;
    }

    protected static function incompleteWorkdayMinutes(EmployeeAttendance $attendance): int
    {
        $expected = static::expectedWorkMinutes($attendance);

        if ($expected <= 0 || ! $attendance->clock_in_at || ! $attendance->clock_out_at) {
            return 0;
        }

        $worked = (int) round((float) ($attendance->worked_minutes ?? 0));

        return max(0, $expected - $worked);
    }


    protected static function refreshExistingIncident(EmployeeIncident $incident, EmployeeAttendance $attendance, HrIncidentType $type, string $code, ?int $userId): EmployeeIncident
    {
        if (! in_array((string) $incident->status, ['draft', 'pending', 'rejected'], true)) {
            return $incident;
        }

        $payload = [
            'title' => static::title($attendance, $code),
            'quantity' => static::quantity($attendance, $code),
            'quantity_unit' => static::quantityUnit($code),
            'description' => static::description($attendance, $code),
            'affects_payroll' => (bool) $type->affects_payroll,
            'requires_approval' => (bool) $type->requires_approval,
            'updated_by_user_id' => $userId,
        ];

        if (Schema::hasColumn('employee_incidents', 'employee_attendance_id')) {
            $payload['employee_attendance_id'] = $attendance->id;
        }

        $incident->forceFill($payload)->save();

        $incident = $incident->fresh();

        static::refreshApprovalRequestAmount($incident);

        return $incident;
    }


    protected static function refreshApprovalRequestAmount(EmployeeIncident $incident): void
    {
        if (! Schema::hasTable('approval_requests')) {
            return;
        }

        if (! Schema::hasColumn('approval_requests', 'amount_total')) {
            return;
        }

        DB::table('approval_requests')
            ->where('document_type', 'employee_incident')
            ->where('approvable_type', EmployeeIncident::class)
            ->where('approvable_id', $incident->id)
            ->whereIn('status', ['draft', 'pending', 'rejected'])
            ->update([
                'amount_total' => $incident->quantity,
                'updated_at' => now(),
            ]);
    }


}
