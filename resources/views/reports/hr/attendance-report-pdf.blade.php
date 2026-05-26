<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de asistencia</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 4px;
        }

        .muted {
            color: #6b7280;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0;
        }

        .summary td {
            border: 1px solid #d1d5db;
            padding: 6px;
        }

        .summary .label {
            background: #f3f4f6;
            font-weight: bold;
        }

        table.detail {
            width: 100%;
            border-collapse: collapse;
        }

        table.detail th,
        table.detail td {
            border: 1px solid #d1d5db;
            padding: 4px;
            vertical-align: top;
        }

        table.detail th {
            background: #111827;
            color: white;
            font-size: 9px;
        }

        .right {
            text-align: right;
        }
    </style>
</head>
<body>
    <h1>Bexia ERP - Reporte de asistencia</h1>
    <div class="muted">
        Periodo: {{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }}
        -
        {{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}
        · Generado: {{ $generatedAt->format('d/m/Y H:i') }}
    </div>

    <table class="summary">
        <tr>
            <td class="label">Registros</td>
            <td>{{ $summary['records'] }}</td>
            <td class="label">Empleados</td>
            <td>{{ $summary['employees'] }}</td>
            <td class="label">Horas trabajadas</td>
            <td>{{ number_format($summary['worked_hours'], 2) }}</td>
        </tr>
        <tr>
            <td class="label">Retardos</td>
            <td>{{ $summary['late_count'] }} / {{ $summary['late_minutes'] }} min</td>
            <td class="label">Faltas</td>
            <td>{{ $summary['absence_count'] }}</td>
            <td class="label">Salidas tempranas</td>
            <td>{{ $summary['early_leave_count'] }} / {{ $summary['early_leave_minutes'] }} min</td>
        </tr>
        <tr>
            <td class="label">Horas extra</td>
            <td>{{ number_format($summary['overtime_hours'], 2) }} h</td>
            <td class="label">Descansos trabajados</td>
            <td>{{ $summary['rest_day_worked_count'] }}</td>
            <td class="label">Minutos extra</td>
            <td>{{ $summary['overtime_minutes'] }}</td>
        </tr>
    </table>

    <table class="detail">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Empleado</th>
                <th>Depto.</th>
                <th>Puesto</th>
                <th>Estado</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Horas</th>
                <th>Retardo</th>
                <th>Extra</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ \App\Support\EmployeeAttendanceReportService::dateOnly($row->attendance_date) }}</td>
                    <td>
                        {{ $row->employee_name ?: '-' }}
                        @if ($row->employee_number)
                            <br><span class="muted">{{ $row->employee_number }}</span>
                        @endif
                    </td>
                    <td>{{ $row->department_name ?: '-' }}</td>
                    <td>{{ $row->position_name ?: '-' }}</td>
                    <td>{{ $statusOptions[$row->status] ?? $row->status }}</td>
                    <td>
                        {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->clock_in_at) }}
                        <br><span class="muted">Esp. {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->expected_start_at) }}</span>
                    </td>
                    <td>
                        {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->clock_out_at) }}
                        <br><span class="muted">Esp. {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->expected_end_at) }}</span>
                    </td>
                    <td class="right">{{ number_format((float) $row->worked_hours, 2) }}</td>
                    <td class="right">{{ (int) $row->late_minutes }} min</td>
                    <td class="right">{{ (int) $row->overtime_minutes }} min</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align:center; padding: 16px;">
                        No hay asistencias para los filtros seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
