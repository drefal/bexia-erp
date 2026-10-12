<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo interno de nómina</title>
    <style>
        @page {
            margin: 7mm 8mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #111827;
            line-height: 1.18;
        }

        h1 {
            font-size: 16px;
            margin: 0 0 2px;
        }

        h2 {
            font-size: 11px;
            margin: 7px 0 3px;
        }

        .muted {
            color: #6b7280;
        }

        .warning {
            border: 1px solid #f59e0b;
            background: #fffbeb;
            color: #92400e;
            padding: 3px 4px;
            margin: 4px 0;
            font-size: 7.5px;
            line-height: 1.1;
            font-weight: bold;
        }

        .box {
            border: 1px solid #d1d5db;
            padding: 3px 4px;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th {
            border: 1px solid #d1d5db;
            padding: 3px 4px;
            vertical-align: top;
        }

        th {
            background: #111827;
            color: #ffffff;
            font-size: 8.5px;
            text-align: left;
        }

        .label {
            background: #f3f4f6;
            font-weight: bold;
            width: 22%;
        }

        .right {
            text-align: right;
        }

        .total {
            font-size: 12px;
            font-weight: bold;
        }

        .signature {
            margin-top: 12px;
            width: 48%;
            display: inline-block;
            text-align: center;
            vertical-align: top;
        }

        .line {
            border-top: 1px solid #111827;
            padding-top: 3px;
        }

        .small {
            font-size: 9px;
        }

        table,
        tr,
        .box,
        .warning {
            page-break-inside: avoid;
        }

    </style>
</head>
<body>
    <h1>Bexia ERP - Recibo interno de nómina</h1>

    <div style="margin: 1px 0 3px 0; font-size: 8px;">
        <strong>Modo:</strong>
        {{ $calculationModeLabel ?? 'Nómina simple' }}
    </div>
    <div class="muted">
        Generado: {{ $generatedAt->format('d/m/Y H:i') }}
    </div>


@if(($showFiscalSection ?? false) && $fiscalResult)
    <div style="
        margin-top: 4px;
        border: 1px solid #9ca3af;
        page-break-inside: avoid;
    ">
        <div style="
            padding: 2px 4px;
            background: #f3f4f6;
            font-size: 8.5px;
            font-weight: bold;
        ">
            Resumen fiscal · Cálculo sin CFDI
        </div>

        <table style="
            font-size: 8px;
            margin: 0;
        ">
            <tr>
                <td class="label">Base gravable</td>
                <td class="right">
                    {{ \App\Support\PayrollRunExportService::money($fiscalResult->taxable_income) }}
                </td>

                <td class="label">Exento</td>
                <td class="right">
                    {{ \App\Support\PayrollRunExportService::money($fiscalResult->exempt_income) }}
                </td>

                <td class="label">ISR antes subsidio</td>
                <td class="right">
                    {{ \App\Support\PayrollRunExportService::money($fiscalResult->isr_before_subsidy) }}
                </td>
            </tr>

            <tr>
                <td class="label">Subsidio causado</td>
                <td class="right">
                    {{ \App\Support\PayrollRunExportService::money($fiscalResult->subsidy_caused) }}
                </td>

                <td class="label">Subsidio aplicado</td>
                <td class="right">
                    {{ \App\Support\PayrollRunExportService::money($fiscalResult->subsidy_applied) }}
                </td>

                <td class="label">ISR retenido</td>
                <td class="right">
                    {{ \App\Support\PayrollRunExportService::money($fiscalResult->isr_withheld) }}
                </td>
            </tr>
        </table>

        <div style="
            padding: 2px 4px;
            font-size: 7.5px;
            line-height: 1.1;
        ">
            Tarifa ISR:
            <strong>{{ $fiscalResult->isr_tariff_code ?: '-' }}</strong>
            &nbsp;&nbsp;·&nbsp;&nbsp;
            Regla de subsidio:
            <strong>{{ $fiscalResult->subsidy_rule_code ?: '-' }}</strong>
        </div>
    </div>
@endif

<div class="warning">
        {{ $legend }}
    </div>

    <table>
        <tr>
            <td class="label">Empresa</td>
            <td>{{ $company?->name ?? '-' }}</td>
            <td class="label">Recibo interno</td>
            <td>#{{ $line->id }}</td>
        </tr>
        <tr>
            <td class="label">Pre-nómina</td>
            <td>{{ $run?->name ?? '-' }}</td>
            <td class="label">Estado</td>
            <td>{{ $statusOptions[$run?->status] ?? ($run?->status ?? '-') }}</td>
        </tr>
        <tr>
            <td class="label">Periodo</td>
            <td>
                {{ \App\Support\PayrollRunExportService::dateOnly($run?->period_start) }}
                -
                {{ \App\Support\PayrollRunExportService::dateOnly($run?->period_end) }}
            </td>
            <td class="label">Fecha de pago</td>
            <td>{{ \App\Support\PayrollRunExportService::dateOnly($run?->payment_date) }}</td>
        </tr>
    </table>

    <h2>Empleado</h2>
    <table>
        <tr>
            <td class="label">Nombre</td>
            <td>{{ $employee?->name ?? '-' }}</td>
            <td class="label">Número</td>
            <td>{{ $employee?->employee_number ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Departamento</td>
            <td>{{ $employee?->hrDepartment?->name ?? '-' }}</td>
            <td class="label">Puesto</td>
            <td>{{ $employee?->hrJobPosition?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Contrato</td>
            <td>{{ $contract?->contract_number ?: '-' }}</td>
            <td class="label">Tipo sueldo</td>
            <td>{{ $line->salary_type ?: '-' }}</td>
        </tr>
    </table>

    <h2>Asistencia y cálculo base</h2>
    <table>
        <tr>
            <th>Concepto</th>
            <th class="right">Cantidad</th>
            <th class="right">Importe</th>
        </tr>
        <tr>
            <td>Sueldo base configurado</td>
            <td class="right">{{ \App\Support\PayrollRunExportService::money($line->base_salary) }}</td>
            <td class="right">-</td>
        </tr>
        <tr>
            <td>Salario diario</td>
            <td class="right">{{ \App\Support\PayrollRunExportService::money($line->daily_salary) }}</td>
            <td class="right">-</td>
        </tr>
        <tr>
            <td>Tarifa por hora</td>
            <td class="right">{{ \App\Support\PayrollRunExportService::money($line->hourly_rate) }}</td>
            <td class="right">-</td>
        </tr>
        <tr>
            <td>Días del periodo</td>
            <td class="right">{{ number_format((float) $line->period_days, 2) }}</td>
            <td class="right">{{ \App\Support\PayrollRunExportService::money($line->base_amount) }}</td>
        </tr>
        <tr>
            <td>Horas trabajadas</td>
            <td class="right">{{ number_format((float) $line->worked_hours, 2) }}</td>
            <td class="right">-</td>
        </tr>
        <tr>
            <td>Retardos</td>
            <td class="right">{{ (int) $line->late_minutes }} min</td>
            <td class="right">-</td>
        </tr>
        <tr>
            <td>Salidas tempranas</td>
            <td class="right">{{ (int) $line->early_leave_minutes }} min</td>
            <td class="right">-</td>
        </tr>
        <tr>
            <td>Faltas</td>
            <td class="right">{{ number_format((float) $line->absence_days, 2) }}</td>
            <td class="right">-</td>
        </tr>
        <tr>
            <td>Horas extra</td>
            <td class="right">{{ number_format((float) $line->overtime_hours, 2) }} h / {{ (int) $line->overtime_minutes }} min</td>
            <td class="right">{{ \App\Support\PayrollRunExportService::money($line->overtime_amount) }}</td>
        </tr>
    </table>

    <h2>Resumen económico</h2>
    <table>
        <tr>
            <td class="label">Sueldo base del periodo</td>
            <td class="right">{{ \App\Support\PayrollRunExportService::money($line->base_amount) }}</td>
        </tr>
        <tr>
            <td class="label">Horas extra</td>
            <td class="right">{{ \App\Support\PayrollRunExportService::money($line->overtime_amount) }}</td>
        </tr>
        <tr>
            <td class="label">Percepciones por incidencias</td>
            <td class="right">{{ \App\Support\PayrollRunExportService::money($line->incident_perceptions) }}</td>
        </tr>
        <tr>
            <td class="label">Bruto</td>
            <td class="right">{{ \App\Support\PayrollRunExportService::money($line->gross_amount) }}</td>
        </tr>
        <tr>
            <td class="label">Deducciones por incidencias</td>
            <td class="right">{{ \App\Support\PayrollRunExportService::money($line->incident_deductions) }}</td>
        </tr>
        <tr>
            <td class="label">Deducciones totales</td>
            <td class="right">{{ \App\Support\PayrollRunExportService::money($line->deductions_amount) }}</td>
        </tr>
        <tr>
            <td class="label total">Neto a pagar</td>
            <td class="right total">{{ \App\Support\PayrollRunExportService::money($line->net_amount) }}</td>
        </tr>
    </table>


    @if ($line->concepts && $line->concepts->isNotEmpty())
        <h2>Desglose por conceptos</h2>
        <table>
            <tr>
                <th>Código</th>
                <th>Concepto</th>
                <th>Tipo</th>
                <th class="right">Cantidad</th>
                <th class="right">Tarifa</th>
                <th class="right">Importe</th>
            </tr>
            @foreach ($line->concepts as $concept)
                <tr>
                    <td>{{ $concept->code }}</td>
                    <td>{{ $concept->name }}</td>
                    <td>{{ $concept->type === 'deduction' ? 'Deducción' : ($concept->type === 'perception' ? 'Percepción' : 'Informativo') }}</td>
                    <td class="right">{{ number_format((float) $concept->quantity, 4) }} {{ $concept->unit }}</td>
                    <td class="right">{{ \App\Support\PayrollRunExportService::money($concept->rate) }}</td>
                    <td class="right">{{ \App\Support\PayrollRunExportService::money($concept->amount) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($line->approved_incidents_count > 0)
        <h2>Incidencias consideradas</h2>
        <table>
            <tr>
                <td class="label">Incidencias aprobadas</td>
                <td>{{ (int) $line->approved_incidents_count }}</td>
                <td class="label">Días descontables</td>
                <td>{{ number_format((float) $line->approved_incident_deduction_days, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Minutos descontables</td>
                <td>{{ (int) $line->approved_incident_deduction_minutes }}</td>
                <td class="label">IDs</td>
                <td>{{ implode(', ', $line->details['approved_incident_ids'] ?? []) ?: '-' }}</td>
            </tr>
        </table>
    @endif

    @if ($line->notes)
        <h2>Notas</h2>
        <div class="box">{{ $line->notes }}</div>
    @endif

    <div class="signature">
        <div class="line">Firma empleado</div>
    </div>

    <div class="signature">
        <div class="line">Firma empresa / RRHH</div>
    </div>

    <p class="muted small" style="margin-top: 24px;">
        Documento interno generado desde Bexia ERP. No contiene UUID, sello SAT, sello CFDI ni cadena original.
    </p>
</body>
</html>
