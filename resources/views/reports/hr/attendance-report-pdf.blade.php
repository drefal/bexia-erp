<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de asistencia</title>

    <style>
        @page {
            margin: 22px 20px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #111827;
        }

        h1 {
            font-size: 16px;
            margin: 0 0 4px;
        }

        .muted {
            color: #6b7280;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        .summary td {
            border: 1px solid #d1d5db;
            padding: 4px;
        }

        .summary .label {
            background: #f3f4f6;
            font-weight: bold;
        }

        table.detail {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.detail th,
        table.detail td {
            border: 1px solid #d1d5db;
            padding: 3px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        table.detail th {
            background: #111827;
            color: white;
            font-size: 7px;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .evidence {
            width: 52px;
            height: 44px;
            margin: 0 auto 2px;
            border: 1px dashed #9ca3af;
            background: #f9fafb;
            text-align: center;
            color: #6b7280;
            font-size: 6px;
            line-height: 8px;
        }

        .evidence-inner {
            padding-top: 9px;
        }

        .evidence-time {
            font-size: 6px;
            color: #374151;
            text-align: center;
        }

        .nowrap {
            white-space: nowrap;
        }

        tr {
            page-break-inside: avoid;
        }
    
        /* AR6B INCIDENT TABLE */
        table.detail {
            table-layout: fixed;
            width: 100%;
            font-size: 6.5px;
        }

        table.detail th,
        table.detail td {
            padding: 3px 2px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: anywhere;
        }

        table.detail thead tr:first-child th {
            text-align: center;
            font-size: 6.5px;
        }
</style>
</head>

<body>
    <h1>Bexia ERP - Reporte de asistencia</h1>

    <div class="muted">
        Periodo:
        {{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }}
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
            <td>
                {{ $summary['late_count'] }}
                /
                {{ $summary['late_minutes'] }} min
            </td>

            <td class="label">Faltas</td>
            <td>{{ $summary['absence_count'] }}</td>

            <td class="label">Salidas tempranas</td>
            <td>
                {{ $summary['early_leave_count'] }}
                /
                {{ $summary['early_leave_minutes'] }} min
            </td>
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
                <th colspan="11">Datos de asistencia</th>
                <th colspan="6">Incidencias</th>
                <th colspan="4">Resolución</th>
            </tr>
            <tr>
                <th>Fecha</th>
                <th>Empleado</th>
                <th>Depto.</th>
                <th>Puesto</th>
                <th>Estado</th>
                <th>Entrada</th>
                <th>Comida S.</th>
                <th>Comida R.</th>
                <th>Salida</th>
                <th>Horas</th>
                <th>Extra</th>

                <th>Retardo</th>
                <th>Comida exc.</th>
                <th>Salida antes</th>
                <th>Falta</th>
                <th>Jornada inc.</th>
                <th>Marcaje inc.</th>

                <th>Total</th>
                <th>Aprob.</th>
                <th>Rech.</th>
                <th>Pend.</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($rows as $row)
                @php
                    $incident = $incidentMap[(int) $row->id] ?? [
                        'retardo_label' => '—',
                        'comida_excedida_label' => '—',
                        'salida_antes_label' => '—',
                        'falta_label' => '—',
                        'jornada_incompleta_label' => '—',
                        'marcaje_incompleto' => '—',
                        'total' => 0,
                        'approved' => 0,
                        'rejected' => 0,
                        'pending' => 0,
                    ];
                @endphp

                <tr>
                    <td>{{ \App\Support\EmployeeAttendanceReportService::dateOnly($row->attendance_date) }}</td>

                    <td>
                        <strong>{{ $row->employee_name }}</strong>
                        @if ($row->employee_number)
                            <br>
                            <span class="muted">
                                {{ $row->employee_number }}
                            </span>
                        @endif
                    </td>

                    <td>{{ $row->department_name ?: '—' }}</td>

                    <td>{{ $row->position_name ?: '—' }}</td>

                    <td>
                        {{ \App\Support\EmployeeAttendanceReportService::statusLabel($row->status) }}
                    </td>

                    <td>
                        {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->clock_in_at) ?: '—' }}
                    </td>

                    <td>
                        {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->meal_out_at) ?: '—' }}
                    </td>

                    <td>
                        {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->meal_in_at) ?: '—' }}
                    </td>

                    <td>
                        {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->clock_out_at) ?: '—' }}
                    </td>

                    <td>
                        {{ number_format((float) ($row->worked_hours ?? 0), 2) }}
                    </td>

                    {{-- PDF_EXTRA_AR6C --}}
                    <td>
                        {{ (int) ($row->overtime_minutes ?? 0) }} min
                    </td>

                    <td>{{ $incident['retardo_label'] }}</td>
                    <td>{{ $incident['comida_excedida_label'] }}</td>
                    <td>{{ $incident['salida_antes_label'] }}</td>
                    <td>{{ $incident['falta_label'] }}</td>
                    <td>{{ $incident['jornada_incompleta_label'] }}</td>
                    <td>{{ $incident['marcaje_incompleto'] }}</td>

                    <td>{{ (int) $incident['total'] }}</td>
                    <td>{{ (int) $incident['approved'] }}</td>
                    <td>{{ (int) $incident['rejected'] }}</td>
                    <td>{{ (int) $incident['pending'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="21">
                        No hay registros para los filtros seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
