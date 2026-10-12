<?php

namespace App\Support;

use App\Models\PayrollRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollAttendanceIncidentGate
{
    /**
     * Incidencias automáticas provenientes de asistencia que:
     *
     * - pertenecen a empleados incluidos en la corrida;
     * - pueden afectar nómina;
     * - requieren resolución;
     * - siguen en draft/pending.
     */
    public static function pending(PayrollRun $run): Collection
    {
        if (
            ! Schema::hasTable('employee_incidents')
            || ! Schema::hasTable('hr_incident_types')
            || ! Schema::hasTable('employees')
            || ! Schema::hasColumn(
                'employee_incidents',
                'employee_attendance_id'
            )
        ) {
            return collect();
        }

        return DB::table('employee_incidents as i')
            ->join(
                'hr_incident_types as t',
                't.id',
                '=',
                'i.hr_incident_type_id'
            )
            ->join(
                'employees as e',
                'e.id',
                '=',
                'i.employee_id'
            )
            ->where('i.company_id', $run->company_id)
            ->where('e.company_id', $run->company_id)
            ->where('e.payroll_group_id', $run->payroll_group_id)
            ->whereNotNull('i.employee_attendance_id')
            ->where('i.requires_approval', true)
            ->where('i.affects_payroll', true)
            ->whereIn('i.status', ['draft', 'pending'])
            ->whereDate('i.start_date', '<=', $run->period_end)
            ->where(function ($query) use ($run): void {
                $query
                    ->whereNull('i.end_date')
                    ->orWhereDate(
                        'i.end_date',
                        '>=',
                        $run->period_start
                    );
            })
            ->select([
                'i.id',
                'i.employee_id',
                'i.employee_attendance_id',
                'i.start_date',
                'i.status',
                't.code',
                't.name as type_name',
                'e.name as employee_name',
            ])
            ->orderBy('i.start_date')
            ->orderBy('e.name')
            ->orderBy('i.id')
            ->get();
    }

    public static function assertResolved(
        PayrollRun $run,
        string $action = 'continuar con la nómina'
    ): void {
        $pending = static::pending($run);

        if ($pending->isEmpty()) {
            return;
        }

        $visible = $pending
            ->take(10)
            ->map(function ($row): string {
                $type = $row->type_name
                    ?: $row->code
                    ?: 'Incidencia';

                return trim(
                    ($row->employee_name ?: 'Empleado')
                    . ' · '
                    . $row->start_date
                    . ' · '
                    . $type
                );
            })
            ->all();

        $message =
            'No se puede '
            . $action
            . ' porque existen incidencias de asistencia pendientes de resolver:'
            . PHP_EOL
            . '- '
            . implode(PHP_EOL . '- ', $visible);

        $remaining = $pending->count() - count($visible);

        if ($remaining > 0) {
            $message .=
                PHP_EOL
                . '- ... y '
                . $remaining
                . ' incidencia(s) adicional(es).';
        }

        throw new \RuntimeException($message);
    }
}
