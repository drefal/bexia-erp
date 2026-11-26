<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de asistencia</title>

    <style>
        @page {
            margin: 22px 20px 78px 20px;
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
    tr.group-header {
            page-break-after: avoid;
        }

    tr.group-header td {
            background: #e5e7eb;
            color: #111827;
            font-weight: bold;
            padding: 5px 4px;
        }
    .employee-summary {
            margin-top: 3px;
            padding-top: 3px;
            border-top: 1px solid #cbd5e1;
            font-size: 6.5px;
            font-weight: normal;
            line-height: 1.65;
        }

        .employee-summary span {
            margin-right: 10px;
        }

        .employee-summary .payable {
            font-weight: bold;
        }

        .employee-summary-note {
            margin-top: 2px;
            font-size: 6px;
            font-weight: normal;
            color: #374151;
        }

        /* BEXIA_RRHH_ASIS03 */
        .asis03-status-cell {
            text-align: center;
            vertical-align: middle !important;
        }

        .asis03-status-cell img {
            width: 14px;
            height: 14px;
        }

        .asis03-footer {
            position: fixed;
            bottom: -52px;
            left: 0;
            right: 0;
            height: 46px;
            border-top: 1px solid #d1d5db;
            padding-top: 3px;
            font-size: 7px;
            color: #374151;
        }

        .asis03-legend {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .asis03-legend td {
            width: 33.333%;
            padding: 1px 3px;
            vertical-align: middle;
            font-size: 7px;
        }

        .asis03-legend img {
            width: 10px;
            height: 10px;
            vertical-align: middle;
            margin-right: 3px;
        }


        /* BEXIA_RRHH_ASIS03_FIX04 */

        table.detail thead tr.asis03-sizing th {
            height: 0 !important;
            line-height: 0 !important;
            font-size: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            background: transparent !important;
        }

        table.detail thead tr.asis03-sizing {
            height: 0 !important;
        }

        table.detail td:nth-child(2) {
            word-wrap: break-word;
            overflow-wrap: normal;
        }

</style>
</head>

<body>
    {{-- BEXIA_RRHH_ASIS03_LEGEND --}}
    <div class="asis03-footer">
        <strong>Simbolog&iacute;a de estados</strong>

        <table class="asis03-legend">
            @foreach (array_chunk(\App\Support\AttendanceReportStatusPresentation::all(), 3, true) as $legendRow)
                <tr>
                    @foreach ($legendRow as $statusKey => $statusInfo)
                        <td>
                            <img
                                src="{{ \App\Support\AttendanceReportStatusPresentation::image($statusKey) }}"
                                alt=""
                            >
                            {{ $statusInfo['label'] }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>

        <div style="font-size:6px;">
            Horarios: real / te&oacute;rico.
            Comida: minutos consumidos / permitidos.
        </div>
    </div>
    <h1>Bexia ERP - Reporte de asistencia</h1>
    @if (($pdfBlockIndex ?? 0) > 0)
        <div class="muted">
            Continuaci&oacute;n del reporte
        </div>
    @endif


    <div class="muted">
        Periodo:
        {{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }}
        -
        {{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}
        · Generado: {{ $generatedAt->format('d/m/Y H:i') }}
    </div>

    <div class="muted">
        Agrupado por:
        {{ ($filters['group_by'] ?? 'day') === 'employee' ? 'Por empleado (A-Z)' : 'Por día' }}
    </div>

    @if (($pdfBlockIndex ?? 0) === 0)
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
    @endif

    <div class="muted" style="margin-bottom:5px;">
        Horarios: real / teórico.
        Comida: minutos consumidos / permitidos.
    </div>

    <table class="detail">
        <colgroup>
            <col style="width:5.5%;">
            <col style="width:12%;">
            <col style="width:6.5%;">
            <col style="width:7%;">
            <col style="width:3.5%;">
            <col style="width:6.5%;">
            <col style="width:6%;">
            <col style="width:6.5%;">
            <col style="width:4%;">
            <col style="width:4%;">
            <col style="width:4.5%;">
            <col style="width:5%;">
            <col style="width:4.5%;">
            <col style="width:3%;">
            <col style="width:5%;">
            <col style="width:5%;">
            <col style="width:3%;">
            <col style="width:3%;">
            <col style="width:2.5%;">
            <col style="width:3%;">
        </colgroup>
        <thead>
            <tr class="asis03-sizing">
                <th style="width:5.5%;"></th>
                <th style="width:12%;"></th>
                <th style="width:6.5%;"></th>
                <th style="width:7%;"></th>
                <th style="width:3.5%;"></th>
                <th style="width:6.5%;"></th>
                <th style="width:6%;"></th>
                <th style="width:6.5%;"></th>
                <th style="width:4%;"></th>
                <th style="width:4%;"></th>
                <th style="width:4.5%;"></th>
                <th style="width:5%;"></th>
                <th style="width:4.5%;"></th>
                <th style="width:3%;"></th>
                <th style="width:5%;"></th>
                <th style="width:5%;"></th>
                <th style="width:3%;"></th>
                <th style="width:3%;"></th>
                <th style="width:2.5%;"></th>
                <th style="width:3%;"></th>
            </tr>
            <tr>
                <th colspan="10">Datos de asistencia</th>
                <th colspan="6">Incidencias</th>
                <th colspan="4">Resolución</th>
            </tr>
            <tr>
                <th style="width:5.5%;">Fecha</th>
                <th style="width:12%;">Nombre</th>
                <th style="width:6.5%;">Depto.</th>
                <th style="width:7%;">Puesto</th>
                <th class="center" style="width:3.5%;">Estado</th>
                <th style="width:6.5%;">Entrada</th>
                <th style="width:6%;">Comida (min)</th>
                <th style="width:6.5%;">Salida</th>
                <th style="width:4%;">Horas</th>
                <th style="width:4%;">Extra</th>

                <th style="width:4.5%;">Retardo</th>
                <th style="width:5%;">Comida exc.</th>
                <th style="width:4.5%;">Salida antes</th>
                <th style="width:3%;">Falta</th>
                <th style="width:5%;">Jornada inc.</th>
                <th style="width:5%;">Marcaje inc.</th>

                <th style="width:3%;">Total</th>
                <th style="width:3%;">Aprob.</th>
                <th style="width:2.5%;">Rech.</th>
                <th style="width:3%;">Pend.</th>
            </tr>
        </thead>

        <tbody>
            @php
                $previousGroupKey = null;
            @endphp

            @forelse ($rows as $row)
                @php
                    $byEmployee = ($filters['group_by'] ?? 'day') === 'employee';

                    $groupKey = $byEmployee
                        ? 'employee:' . (int) $row->employee_id
                        : 'day:' . \App\Support\EmployeeAttendanceReportService::dateOnly($row->attendance_date);
                @endphp

                @if ($previousGroupKey !== $groupKey)
                    <tr class="group-header">
                        <td colspan="20">
                            @if ($byEmployee)
                                Empleado: {{ $row->employee_name ?: 'Empleado sin nombre' }}

                                @if ($row->employee_number)
                                    | No. {{ $row->employee_number }}
                                @endif

                                @php
                                    $stats = $employeeSummaries[
                                        (int) $row->employee_id
                                    ] ?? null;
                                @endphp

                                @if ($stats)
                                    <div class="employee-summary">
                                        <span>Asistencias: <strong>{{ $stats['attendance_days'] }}</strong></span>
                                        <span>| Faltas: <strong>{{ $stats['absence_days'] }}</strong></span>
                                        <span>| Retardos: <strong>{{ $stats['late_minutes'] }} min ({{ number_format($stats['late_minutes'] / 60, 2) }} h)</strong></span>
                                        <span>| Tiempo extra: <strong>{{ $stats['extra_minutes'] }} min ({{ number_format($stats['extra_minutes'] / 60, 2) }} h)</strong></span>
                                        <span>| Permisos: <strong>{{ number_format($stats['permission_days'], 2) }} días</strong></span>
                                        <span>| Vacaciones: <strong>{{ number_format($stats['vacation_days'], 2) }} días</strong></span>
                                        <span class="payable">| Tiempo extra a pagar: {{ $stats['payable_minutes'] }} min ({{ number_format($stats['payable_minutes'] / 60, 2) }} h)</span>
                                    </div>

                                    @if (
                                        $stats['partial_permissions'] > 0
                                        || $stats['partial_vacations'] > 0
                                        || $stats['leave_estimated']
                                    )
                                        <div class="employee-summary-note">
                                            @if ($stats['partial_permissions'] > 0)
                                                Permisos parciales:
                                                {{ $stats['partial_permissions'] }}.
                                            @endif
                                            @if ($stats['partial_vacations'] > 0)
                                                Vacaciones parciales:
                                                {{ $stats['partial_vacations'] }}.
                                            @endif
                                            @if ($stats['leave_estimated'])
                                                Días estimados por solicitud que cruza el periodo.
                                            @endif
                                        </div>
                                    @endif
                                @endif
                            @else
                                Fecha: {{ \App\Support\EmployeeAttendanceReportService::dateOnly($row->attendance_date) }}
                            @endif
                            @if (
                                $loop->first
                                && ($pdfPreviousGroup ?? null) === $groupKey
                            )
                                <span class="muted">
                                    (continuaci&oacute;n)
                                </span>
                            @endif
                        </td>
                    </tr>
                @endif

                @php
                    $previousGroupKey = $groupKey;

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

                    {{-- BEXIA_RRHH_ASIS03_STATUS --}}
                    <td class="asis03-status-cell">
                        <img
                            src="{{ \App\Support\AttendanceReportStatusPresentation::image($row->status) }}"
                            alt="{{ \App\Support\AttendanceReportStatusPresentation::label($row->status) }}"
                        >
                    </td>

                    {{-- BEXIA_RRHH_ASIS02 --}}
                    <td class="center">
                        <strong>{{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->clock_in_at) }}</strong>
                        <span class="muted"> / {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->expected_start_at) }}</span>
                    </td>

                    <td class="center">
                        <span style="{{ $row->meal_excess_minutes > 0 ? 'color:#b91c1c;font-weight:bold;' : '' }}">
                            {{ $row->meal_display }}
                        </span>
                        @if ($row->meal_excess_minutes > 0)
                            <br>
                            <span style="color:#b91c1c;">
                                Exc. {{ $row->meal_excess_minutes }} min
                            </span>
                        @endif
                        @if ($row->meal_incomplete_count > 0)
                            <br>
                            <span style="color:#b45309;">
                                Incompleta
                            </span>
                        @endif
                    </td>

                    <td class="center">
                        <strong>{{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->clock_out_at) }}</strong>
                        <span class="muted"> / {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->expected_end_at) }}</span>
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
                    <td colspan="20">
                        No hay registros para los filtros seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
