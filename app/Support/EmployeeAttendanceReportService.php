<?php

namespace App\Support;

use App\Models\EmployeeAttendance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class EmployeeAttendanceReportService
{
    public static function normalizeFilters(array $filters): array
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'company_id' => (int) ($filters['company_id'] ?? 0),
            'from' => $from,
            'to' => $to,
            'employee_id' => filled($filters['employee_id'] ?? null) ? (int) $filters['employee_id'] : null,
            'department_id' => filled($filters['department_id'] ?? null) ? (int) $filters['department_id'] : null,
            'status' => filled($filters['status'] ?? null) ? (string) $filters['status'] : null,
        ];
    }

    public static function rows(array $filters): Collection
    {
        $filters = static::normalizeFilters($filters);

        if ($filters['company_id'] <= 0) {
            return collect();
        }

        return DB::table('employee_attendances as a')
            ->leftJoin('employees as e', 'e.id', '=', 'a.employee_id')
            ->leftJoin('hr_departments as d', 'd.id', '=', 'e.hr_department_id')
            ->leftJoin('hr_job_positions as p', 'p.id', '=', 'e.hr_job_position_id')
            ->leftJoin('hr_work_schedules as s', 's.id', '=', 'a.hr_work_schedule_id')
            ->where('a.company_id', $filters['company_id'])
            ->whereDate('a.attendance_date', '>=', $filters['from'])
            ->whereDate('a.attendance_date', '<=', $filters['to'])
            ->when($filters['employee_id'], fn ($query, $value) => $query->where('a.employee_id', $value))
            ->when($filters['department_id'], fn ($query, $value) => $query->where('e.hr_department_id', $value))
            ->when($filters['status'], fn ($query, $value) => $query->where('a.status', $value))
            ->orderBy('a.attendance_date')
            ->orderBy('e.name')
            ->select([
                'a.id',
                'a.attendance_date',
                'a.status',
                'a.employee_id',
                'e.employee_number',
                'e.name as employee_name',
                'd.name as department_name',
                'p.name as position_name',
                's.name as schedule_name',
                'a.expected_start_at',
                'a.expected_end_at',
                'a.clock_in_at',
                'a.clock_out_at',
                'a.expected_hours',
                'a.worked_hours',
                'a.worked_minutes',
                'a.late_minutes',
                'a.early_leave_minutes',
                'a.overtime_minutes',
                'a.source',
                'a.notes',
            ])
            ->get();
    }

    public static function summary(Collection $rows): array
    {
        $employeeIds = $rows
            ->pluck('employee_id')
            ->filter()
            ->unique();

        $lateStatuses = ['late', 'late_early_leave'];
        $earlyStatuses = ['early_leave', 'late_early_leave'];

        return [
            'records' => $rows->count(),
            'employees' => $employeeIds->count(),
            'worked_minutes' => (int) $rows->sum(fn ($row) => (int) ($row->worked_minutes ?? 0)),
            'worked_hours' => round(((int) $rows->sum(fn ($row) => (int) ($row->worked_minutes ?? 0))) / 60, 2),
            'late_count' => $rows->filter(fn ($row) => in_array((string) $row->status, $lateStatuses, true))->count(),
            'late_minutes' => (int) $rows->sum(fn ($row) => (int) ($row->late_minutes ?? 0)),
            'absence_count' => $rows->where('status', 'absence')->count(),
            'early_leave_count' => $rows->filter(fn ($row) => in_array((string) $row->status, $earlyStatuses, true) || (int) ($row->early_leave_minutes ?? 0) > 0)->count(),
            'early_leave_minutes' => (int) $rows->sum(fn ($row) => (int) ($row->early_leave_minutes ?? 0)),
            'overtime_minutes' => (int) $rows->sum(fn ($row) => (int) ($row->overtime_minutes ?? 0)),
            'overtime_hours' => round(((int) $rows->sum(fn ($row) => (int) ($row->overtime_minutes ?? 0))) / 60, 2),
            'rest_day_worked_count' => $rows->where('status', 'rest_day_worked')->count(),
        ];
    }

    public static function data(array $filters): array
    {
        $filters = static::normalizeFilters($filters);
        $rows = static::rows($filters);

        return [
            'filters' => $filters,
            'rows' => $rows,
            'summary' => static::summary($rows),
            'statusOptions' => EmployeeAttendance::statusOptions(),
            'generatedAt' => now(),
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return EmployeeAttendance::statusOptions()[$status] ?? ($status ?: '-');
    }

    public static function minutesToHours(int|float|null $minutes): string
    {
        return number_format(((float) ($minutes ?? 0)) / 60, 2);
    }

    public static function writeExcel(string $path, array $filters): void
    {
        $data = static::data($filters);
        $summary = $data['summary'];
        $rows = $data['rows'];

        $writer = new Writer();
        $writer->openToFile($path);

        $writer->addRow(Row::fromValues(['Bexia ERP - Reporte de asistencia']));
        $writer->addRow(Row::fromValues(['Desde', $data['filters']['from'], 'Hasta', $data['filters']['to'], 'Generado', $data['generatedAt']->format('Y-m-d H:i:s')]));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(['Resumen']));
        $writer->addRow(Row::fromValues(['Registros', $summary['records']]));
        $writer->addRow(Row::fromValues(['Empleados', $summary['employees']]));
        $writer->addRow(Row::fromValues(['Horas trabajadas', $summary['worked_hours']]));
        $writer->addRow(Row::fromValues(['Retardos', $summary['late_count']]));
        $writer->addRow(Row::fromValues(['Minutos de retardo', $summary['late_minutes']]));
        $writer->addRow(Row::fromValues(['Faltas', $summary['absence_count']]));
        $writer->addRow(Row::fromValues(['Salidas tempranas', $summary['early_leave_count']]));
        $writer->addRow(Row::fromValues(['Minutos salida temprana', $summary['early_leave_minutes']]));
        $writer->addRow(Row::fromValues(['Horas extra', $summary['overtime_hours']]));
        $writer->addRow(Row::fromValues(['Descansos trabajados', $summary['rest_day_worked_count']]));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues([
            'Fecha',
            'Empleado',
            'Numero empleado',
            'Departamento',
            'Puesto',
            'Horario',
            'Estado',
            'Entrada esperada',
            'Entrada real',
            'Salida esperada',
            'Salida real',
            'Horas esperadas',
            'Horas trabajadas',
            'Minutos retardo',
            'Minutos salida temprana',
            'Minutos extra',
            'Origen',
            'Notas',
        ]));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues([
                static::dateOnly($row->attendance_date),
                $row->employee_name,
                $row->employee_number,
                $row->department_name,
                $row->position_name,
                $row->schedule_name,
                static::statusLabel($row->status),
                static::timeOnly($row->expected_start_at),
                static::timeOnly($row->clock_in_at),
                static::timeOnly($row->expected_end_at),
                static::timeOnly($row->clock_out_at),
                (float) ($row->expected_hours ?? 0),
                (float) ($row->worked_hours ?? 0),
                (int) ($row->late_minutes ?? 0),
                (int) ($row->early_leave_minutes ?? 0),
                (int) ($row->overtime_minutes ?? 0),
                $row->source,
                $row->notes,
            ]));
        }

        $writer->close();
    }

    public static function dateOnly(mixed $value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public static function timeOnly(mixed $value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
