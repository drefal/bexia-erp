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
                <th style="width:6%;">Fecha</th>
                <th style="width:15%;">Empleado</th>
                <th style="width:8%;">Depto.</th>
                <th style="width:8%;">Puesto</th>
                <th style="width:8%;">Estado</th>
                <th style="width:7%;">Entrada</th>
                <th style="width:7%;">Salida comida</th>
                <th style="width:7%;">Regreso comida</th>
                <th style="width:6%;">Comida</th>
                <th style="width:7%;">Salida</th>
                <th style="width:5%;">Horas</th>
                <th style="width:5%;">Retardo</th>
                <th style="width:5%;">Extra</th>
                <th style="width:6%;">Evidencia</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($rows as $row)
                @php
                    $mealMinutes = \App\Support\EmployeeAttendanceReportService::mealMinutes(
                        $row->meal_out_at,
                        $row->meal_in_at
                    );

                    /*
                     * PDF_REAL_PHOTO_ONLY_PRODUCTION_V5836J2E2
                     *
                     * DEV:
                     *   Las evidencias actuales son artificiales, por lo que
                     *   siempre dejamos placeholder.
                     *
                     * PROD:
                     *   Usa la foto real de entrada guardada por la terminal.
                     *   Si no existe o no puede leerse, conserva placeholder.
                     */
                    $evidenceDataUri = null;

                    if (
                        app()->environment('production')
                        && ! empty($row->clock_in_photo_path)
                    ) {
                        try {
                            $evidenceDisk =
                                \Illuminate\Support\Facades\Storage::disk('local');

                            if ($evidenceDisk->exists($row->clock_in_photo_path)) {
                                $evidenceBytes =
                                    $evidenceDisk->get($row->clock_in_photo_path);

                                $evidenceMime =
                                    $evidenceDisk->mimeType($row->clock_in_photo_path)
                                    ?: 'image/jpeg';

                                $evidenceDataUri =
                                    'data:'
                                    .$evidenceMime
                                    .';base64,'
                                    .base64_encode($evidenceBytes);
                            }
                        } catch (\Throwable) {
                            $evidenceDataUri = null;
                        }
                    }
                @endphp

                <tr>
                    <td class="nowrap">
                        {{ \App\Support\EmployeeAttendanceReportService::dateOnly($row->attendance_date) }}
                    </td>

                    <td>
                        {{ $row->employee_name ?: '-' }}

                        @if ($row->employee_number)
                            <br>
                            <span class="muted">
                                {{ $row->employee_number }}
                            </span>
                        @endif
                    </td>

                    <td>{{ $row->department_name ?: '-' }}</td>

                    <td>{{ $row->position_name ?: '-' }}</td>

                    <td>
                        {{ $statusOptions[$row->status] ?? $row->status }}
                    </td>

                    <td class="center">
                        {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->clock_in_at) }}

                        <br>

                        <span class="muted">
                            Esp.
                            {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->expected_start_at) }}
                        </span>
                    </td>

                    <td class="center">
                        {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->meal_out_at) }}
                    </td>

                    <td class="center">
                        {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->meal_in_at) }}
                    </td>

                    <td class="right">
                        {{ $mealMinutes }} min
                    </td>

                    <td class="center">
                        {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->clock_out_at) }}

                        <br>

                        <span class="muted">
                            Esp.
                            {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->expected_end_at) }}
                        </span>
                    </td>

                    <td class="right">
                        {{ number_format((float) $row->worked_hours, 2) }}
                    </td>

                    <td class="right">
                        {{ (int) $row->late_minutes }} min
                    </td>

                    <td class="right">
                        {{ (int) $row->overtime_minutes }} min
                    </td>

                    <td class="center">
                        @if ($evidenceDataUri)
                            <div class="evidence">
                                <img
                                    src="{{ $evidenceDataUri }}"
                                    alt="Evidencia de entrada"
                                    style="
                                        width: 50px;
                                        height: 42px;
                                        object-fit: cover;
                                    "
                                >
                            </div>
                        @else
                            <div class="evidence">
                                <div class="evidence-inner">
                                    EVIDENCIA<br>
                                    FOTOGRAFICA
                                </div>
                            </div>
                        @endif

                        <div class="evidence-time">
                            Entrada
                            {{ \App\Support\EmployeeAttendanceReportService::timeOnly($row->clock_in_at) }}
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td
                        colspan="14"
                        style="text-align:center; padding:16px;"
                    >
                        No hay asistencias para los filtros seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
