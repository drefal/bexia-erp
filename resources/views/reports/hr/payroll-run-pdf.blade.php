<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pre-nómina</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #111827;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 4px;
        }

        h2 {
            font-size: 12px;
            margin: 14px 0 6px;
        }

        .muted {
            color: #6b7280;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .summary td {
            border: 1px solid #d1d5db;
            padding: 6px;
        }

        .label {
            background: #f3f4f6;
            font-weight: bold;
        }

        table.detail {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
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
            font-size: 8px;
        }

        .right {
            text-align: right;
        }

        .small {
            font-size: 8px;
        }
    </style>
</head>
<body>
    <h1>Bexia ERP - Pre-nómina</h1>

    <div class="muted">
        Empresa: {{ $company?->name ?? '-' }} ·
        Generado: {{ $generatedAt->format('d/m/Y H:i') }}
    </div>

    <table class="summary">
        <tr>
            <td class="label">Pre-nómina</td>
            <td colspan="3">{{ $run->name }}</td>
            <td class="label">Estado</td>
            <td>{{ $statusOptions[$run->status] ?? $run->status }}</td>
        </tr>
        <tr>
            <td class="label">Periodicidad</td>
            <td>{{ $periodTypeOptions[$run->period_type] ?? $run->period_type }}</td>
            <td class="label">Periodo</td>
            <td>
                {{ \App\Support\PayrollRunExportService::dateOnly($run->period_start) }}
                -
                {{ \App\Support\PayrollRunExportService::dateOnly($run->period_end) }}
            </td>
            <td class="label">Pago</td>
            <td>{{ \App\Support\PayrollRunExportService::dateOnly($run->payment_date) }}</td>
        </tr>
        <tr>
            <td class="label">Empleados</td>
            <td>{{ $run->employees_count }}</td>
            <td class="label">Sueldo base</td>
            <td>{{ \App\Support\PayrollRunExportService::money($run->base_total) }}</td>
            <td class="label">Horas extra</td>
            <td>{{ \App\Support\PayrollRunExportService::money($run->overtime_total) }}</td>
        </tr>
        <tr>
            <td class="label">Percepciones</td>
            <td>{{ \App\Support\PayrollRunExportService::money($run->perceptions_total) }}</td>
            <td class="label">Bruto</td>
            <td>{{ \App\Support\PayrollRunExportService::money($run->gross_total) }}</td>
            <td class="label">Deducciones</td>
            <td>{{ \App\Support\PayrollRunExportService::money($run->deductions_total) }}</td>
        </tr>
        <tr>
            <td class="label">Neto</td>
            <td colspan="5"><strong>{{ \App\Support\PayrollRunExportService::money($run->net_total) }}</strong></td>
        </tr>
    </table>

    <h2>Detalle por empleado</h2>

    <table class="detail">
        <thead>
            <tr>
                <th>Empleado</th>
                <th>Sueldo</th>
                <th>Días</th>
                <th>Horas</th>
                <th>Retardo</th>
                <th>Faltas</th>
                <th>Extra</th>
                <th>Base</th>
                <th>Percep.</th>
                <th>Deduc.</th>
                <th>Bruto</th>
                <th>Neto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>
                        {{ $line->employee?->name ?? '-' }}
                        @if ($line->employee?->employee_number)
                            <br><span class="muted small">{{ $line->employee->employee_number }}</span>
                        @endif
                    </td>
                    <td class="right">
                        {{ \App\Support\PayrollRunExportService::money($line->base_salary) }}
                        <br><span class="muted small">{{ $line->salary_type ?: '-' }}</span>
                    </td>
                    <td class="right">{{ number_format((float) $line->period_days, 2) }}</td>
                    <td class="right">{{ number_format((float) $line->worked_hours, 2) }}</td>
                    <td class="right">{{ (int) $line->late_minutes }} min</td>
                    <td class="right">{{ number_format((float) $line->absence_days, 2) }}</td>
                    <td class="right">{{ number_format((float) $line->overtime_hours, 2) }}</td>
                    <td class="right">{{ \App\Support\PayrollRunExportService::money($line->base_amount) }}</td>
                    <td class="right">{{ \App\Support\PayrollRunExportService::money($line->incident_perceptions) }}</td>
                    <td class="right">{{ \App\Support\PayrollRunExportService::money($line->deductions_amount) }}</td>
                    <td class="right">{{ \App\Support\PayrollRunExportService::money($line->gross_amount) }}</td>
                    <td class="right"><strong>{{ \App\Support\PayrollRunExportService::money($line->net_amount) }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" style="text-align:center; padding: 14px;">
                        No hay líneas calculadas. Calcula la pre-nómina antes de exportar.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($run->notes)
        <h2>Notas</h2>
        <p>{{ $run->notes }}</p>
    @endif
</body>
</html>
