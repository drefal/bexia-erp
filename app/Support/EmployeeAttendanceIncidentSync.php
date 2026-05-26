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
                if (
                    Schema::hasColumn('employee_incidents', 'employee_attendance_id')
                    && blank($existing->employee_attendance_id)
                ) {
                    $existing->forceFill([
                        'employee_attendance_id' => $attendance->id,
                        'updated_by_user_id' => $userId,
                    ])->save();
                }

                static::sendIfNeeded($existing->fresh(), $type, $sendToApproval);

                return $existing->fresh();
            }

            $incident = EmployeeIncident::query()->create(static::incidentPayload($attendance, $type, $code, $userId));

            static::sendIfNeeded($incident->fresh(), $type, $sendToApproval);

            return $incident->fresh();
        });
    }

    public static function incidentCodeForAttendance(EmployeeAttendance $attendance): ?string
    {
        return match ((string) $attendance->status) {
            'late', 'late_early_leave' => 'RETARDO',
            'absence' => 'FALTA',
            default => null,
        };
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
            'FALTA' => 'Falta - ' . $date,
            default => 'Incidencia asistencia - ' . $date,
        };
    }

    protected static function quantity(EmployeeAttendance $attendance, string $code): float
    {
        return match ($code) {
            'RETARDO' => (float) max(1, (int) $attendance->late_minutes),
            'FALTA' => 1.0,
            default => 1.0,
        };
    }

    protected static function quantityUnit(string $code): string
    {
        return match ($code) {
            'RETARDO' => 'minutes',
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

        if ($code === 'FALTA') {
            $parts[] = 'La asistencia no tiene entrada ni salida en un día laborable.';
        }

        return implode(PHP_EOL, $parts);
    }
}
