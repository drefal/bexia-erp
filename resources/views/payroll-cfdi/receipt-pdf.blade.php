@php
    $money = fn ($value) => '$' . number_format((float) $value, 2);
    $num = fn ($value) => number_format((float) $value, 2);
    $safe = fn ($value, $fallback = '-') => filled($value ?? null) ? (string) $value : $fallback;

    $gross = (float) ($totals['gross_amount'] ?? 0);
    $deductionsTotal = (float) ($totals['deductions_amount'] ?? 0);
    $net = (float) ($totals['net_amount'] ?? 0);

    $employeeNumber = $employee['employee_number'] ?? $employee['code'] ?? $employee['number'] ?? $receipt->employee_id ?? '-';
    $employeeName = $employee['fiscal_name'] ?? $employee['name'] ?? 'Empleado';
    $issuerName = $issuer['business_name'] ?? $issuer['name'] ?? $company?->business_name ?? $company?->name ?? 'Empresa';

    $salaryDaily = $contract['daily_salary'] ?? $totals['daily_salary'] ?? null;
    $salaryIntegrated = $contract['integrated_daily_salary'] ?? $totals['integrated_daily_salary'] ?? null;

    $hireDate = $contract['hire_date'] ?? $employee['hire_date'] ?? null;
    $department = $contract['department'] ?? $employee['department'] ?? null;
    $position = $contract['position'] ?? $employee['position'] ?? null;

    $contractType = $contract['sat_contract_type_code'] ?? $contract['contract_type'] ?? null;
    $regimeType = $contract['sat_regime_type_code'] ?? $contract['regime_type'] ?? null;
    $periodicity = $totals['payment_periodicity'] ?? $contract['payment_periodicity'] ?? $payrollRun->pay_frequency ?? null;

    $rfcIssuer = $issuer['tax_id'] ?? $company?->tax_id ?? null;
    $regimenIssuer = $issuer['tax_regime'] ?? $company?->tax_regime ?? null;
    $cpIssuer = $issuer['fiscal_postal_code'] ?? $company?->fiscal_postal_code ?? null;

    $paymentDate = $totals['payment_date'] ?? null;
    $periodStart = $totals['period_start'] ?? null;
    $periodEnd = $totals['period_end'] ?? null;
    $daysPaid = $totals['payable_days'] ?? $totals['period_days'] ?? null;

    $periodText = trim($safe($periodStart, '') . ' al ' . $safe($periodEnd, ''));
    $periodText = $periodText === 'al' ? '-' : $periodText;

    $contractTypeCatalog = [
        '01' => 'Contrato de trabajo por tiempo indeterminado',
        '02' => 'Contrato de trabajo por obra determinada',
        '03' => 'Contrato de trabajo por tiempo determinado',
        '04' => 'Contrato de trabajo por temporada',
        '05' => 'Contrato de trabajo sujeto a prueba',
        '06' => 'Contrato de trabajo con capacitación inicial',
        '07' => 'Modalidad de contratación por pago de hora laborada',
        '08' => 'Modalidad de trabajo por comisión laboral',
        '09' => 'Modalidades de contratación donde no existe relación de trabajo',
        '10' => 'Jubilación, pensión, retiro',
        '99' => 'Otro contrato',
    ];

    $payrollRegimeCatalog = [
        '02' => 'Sueldos',
        '03' => 'Jubilados',
        '04' => 'Pensionados',
        '05' => 'Asimilados miembros sociedades cooperativas producción',
        '06' => 'Asimilados integrantes sociedades asociaciones civiles',
        '07' => 'Asimilados miembros consejos',
        '08' => 'Asimilados comisionistas',
        '09' => 'Asimilados honorarios',
        '10' => 'Asimilados acciones',
        '11' => 'Asimilados otros',
        '12' => 'Jubilados o pensionados',
        '13' => 'Indemnización o separación',
        '99' => 'Otro régimen',
    ];

    $fiscalRegimeCatalog = [
        '605' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
        '606' => 'Arrendamiento',
        '607' => 'Régimen de Enajenación o Adquisición de Bienes',
        '608' => 'Demás ingresos',
        '611' => 'Ingresos por Dividendos',
        '612' => 'Personas Físicas con Actividades Empresariales y Profesionales',
        '614' => 'Ingresos por intereses',
        '615' => 'Régimen de los ingresos por obtención de premios',
        '616' => 'Sin obligaciones fiscales',
        '621' => 'Incorporación Fiscal',
        '625' => 'Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
        '626' => 'Régimen Simplificado de Confianza',
        '02' => 'Sueldos',
    ];

    $contractTypeCode = (string) ($contractType ?? '');
    $contractTypeText = $contractTypeCatalog[$contractTypeCode] ?? $safe($contractType);

    $fiscalRegimeReceptorCode = (string) (
        $employee['sat_tax_regime_code']
        ?? $employee['tax_regime']
        ?? $employee['fiscal_regime']
        ?? $regimeType
        ?? ''
    );

    $fiscalRegimeReceptorText = $fiscalRegimeCatalog[$fiscalRegimeReceptorCode]
        ?? $payrollRegimeCatalog[$fiscalRegimeReceptorCode]
        ?? $safe($fiscalRegimeReceptorCode);

    $conceptKey = function ($line) {
        return $line['sat_key'] ?? $line['sat_code'] ?? $line['sat_type'] ?? $line['clave_sat'] ?? $line['code'] ?? '-';
    };

    $isInternalOnly = (bool) ($isInternalOnly ?? false);
    $isExternalStamped = (bool) ($isExternalStamped ?? false);
    $isCfdiNotRequired = (bool) ($isCfdiNotRequired ?? false);
    $alternateStatusLabel = $alternateStatusLabel ?? null;
    $watermarkText = $watermarkText ?? ($isDemo ? 'DEMO - NO FISCAL' : null);
    $bannerText = $bannerText ?? null;
    $bannerClass = $bannerClass ?? 'demo-banner';
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo nomina {{ $receipt->folio }}</title>
    <style>
        @page { margin: 18px 18px 28px 18px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.6px;
            color: #111827;
            line-height: 1.18;
        }

        .watermark {
            position: fixed;
            top: 310px;
            left: 30px;
            right: 30px;
            text-align: center;
            font-size: 54px;
            font-weight: bold;
            color: rgba(180, 83, 9, 0.10);
            transform: rotate(-16deg);
            z-index: -1;
        }

        .box {
            border: 1px solid #202020;
            margin-bottom: 4px;
        }

        .box-title {
            background: #efefef;
            border-bottom: 1px solid #202020;
            font-weight: bold;
            text-align: center;
            padding: 2px 4px;
            font-size: 10px;
        }

        .pad {
            padding: 4px 6px;
        }

        .title-cell {
            background: #000;
            color: #fff;
            font-weight: bold;
            text-align: center;
            font-size: 14px;
            padding: 4px 6px;
        }

        .small {
            font-size: 8.6px;
        }

        .muted {
            color: #444;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th {
            vertical-align: top;
        }

        .kv td {
            padding: 2px 4px;
        }

        .kv .k {
            font-weight: bold;
            white-space: nowrap;
        }

        .kv .v {
            width: 100%;
        }

        .info-grid td {
            padding: 3px 5px;
            vertical-align: top;
        }

        .label {
            font-weight: bold;
            white-space: nowrap;
        }

        .value {
            font-weight: normal;
        }

        .tight td, .tight th {
            padding: 2px 4px;
            border: 1px solid #202020;
        }

        .tight th {
            background: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }

        .two-col {
            width: 100%;
        }

        .two-col > tbody > tr > td {
            width: 50%;
            vertical-align: top;
        }

        .demo-banner {
            background: #fff3cd;
            border: 1px solid #92400e;
            color: #92400e;
            font-weight: bold;
            text-align: center;
            padding: 5px 8px;
            margin-bottom: 5px;
        }

        .total-row td {
            font-weight: bold;
            background: #fafafa;
        }

        .summary td {
            padding: 3px 5px;
            border-bottom: 1px solid #d1d5db;
        }

        .summary .grand td {
            font-size: 12px;
            font-weight: bold;
            border-top: 2px solid #111827;
            border-bottom: 2px solid #111827;
        }

        .uuid-box {
            border: 1px solid #202020;
            padding: 6px;
            font-size: 8.8px;
            word-break: break-all;
            min-height: 24px;
        }

        .path-text {
            font-size: 8px;
            word-break: break-all;
            line-height: 1.1;
        }

        .footer {
            position: fixed;
            bottom: 8px;
            left: 18px;
            right: 18px;
            text-align: center;
            font-size: 8px;
            color: #555;
            border-top: 1px solid #ccc;
            padding-top: 4px;
        }

        .logo {
            max-width: 90px;
            max-height: 48px;
            object-fit: contain;
        }

        .nowrap {
            white-space: nowrap;
        }
    </style>
</head>
<body>
@if (filled($watermarkText ?? null))
    <div class="watermark">{{ $watermarkText }}</div>
@endif

@if (filled($bannerText ?? null))
    <div class="{{ $bannerClass ?? 'demo-banner' }}">{{ $bannerText }}</div>
@endif

<table class="box">
    <tr>
        <td style="width: 58%; padding: 0;">
            <table>
                <tr>
                    <td style="width: 95px; padding: 5px 4px 3px 6px;">
                        @if ($logoDataUri)
                            <img class="logo" src="{{ $logoDataUri }}" alt="Logo">
                        @endif
                    </td>
                    <td style="padding: 4px 6px 4px 0;">
                        <div style="font-weight: bold; font-size: 14px;">{{ strtoupper($issuerName) }}</div>
                        <table class="kv" style="margin-top: 2px;">
                            <tr>
                                <td class="k">R.F.C.</td>
                                <td class="v">{{ $safe($rfcIssuer) }}</td>
                            </tr>
                            <tr>
                                <td class="k">Régimen Fiscal</td>
                                <td class="v">{{ $safe($regimenIssuer) }}</td>
                            </tr>
                            <tr>
                                <td class="k">Expedido</td>
                                <td class="v">{{ $safe($cpIssuer) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 42%; padding: 0;">
            <table>
                <tr>
                    <td class="title-cell">Recibo de Nómina</td>
                </tr>
            </table>
            <div class="pad">
                <table class="kv">
                    <tr>
                        <td class="k">Folio</td>
                        <td class="v">{{ $safe($receipt->folio ?? null) }}</td>
                    </tr>
                    <tr>
                        <td class="k">Serie</td>
                        <td class="v">{{ $safe($receipt->series ?? null) }}</td>
                    </tr>
                    <tr>
                        <td class="k">Tipo de Comprobante</td>
                        <td class="v">Nómina</td>
                    </tr>
                    <tr>
                        <td class="k">Ciclo de pago</td>
                        <td class="v">{{ $safe($periodicity, 'N/D') }}</td>
                    </tr>
                    <tr>
                        <td class="k">Fecha efectiva de pago</td>
                        <td class="v">{{ $safe($paymentDate) }}</td>
                    </tr>
                    <tr>
                        <td class="k">Estado CFDI</td>
                        <td class="v">{{ $alternateStatusLabel ?? strtoupper((string) $receipt->status) }}</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

@if ($isDemo)
    <div class="demo-banner">
        TIMBRADO DEMO DEV - NO FISCAL - NO PAC/SAT
    </div>
@endif

<div class="box">
    <div class="box-title">Datos del empleado</div>
    <table class="info-grid">
        <tr>
            <td style="width: 14%;"><span class="label">No. empleado</span><br><span class="value">{{ $safe($employeeNumber) }}</span></td>
            <td style="width: 30%;"><span class="label">Nombre</span><br><span class="value">{{ $employeeName }}</span></td>
            <td style="width: 16%;"><span class="label">R.F.C.</span><br><span class="value">{{ $safe($employee['rfc'] ?? null) }}</span></td>
            <td style="width: 24%;"><span class="label">CURP</span><br><span class="value">{{ $safe($employee['curp'] ?? null) }}</span></td>
            <td style="width: 16%;"><span class="label">NSS</span><br><span class="value">{{ $safe($employee['social_security_number'] ?? null) }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Fecha alta</span><br><span class="value">{{ $safe($hireDate) }}</span></td>
            <td><span class="label">Departamento</span><br><span class="value">{{ $safe($department) }}</span></td>
            <td><span class="label">Puesto</span><br><span class="value">{{ $safe($position) }}</span></td>
            <td><span class="label">Régimen Fiscal Receptor</span><br><span class="value">{{ $safe($fiscalRegimeReceptorText) }}</span></td>
            <td><span class="label">PAC</span><br><span class="value">{{ $safe($receipt->pac_provider ?? null) }}</span></td>
        </tr>
    </table>
</div>

<div class="box">
    <div class="box-title">Periodo, salario y contrato</div>
    <table class="info-grid">
        <tr>
            <td style="width: 34%;"><span class="label">Periodo</span><br><span class="value nowrap">{{ $periodText }}</span></td>
            <td style="width: 14%;"><span class="label">Días pagados</span><br><span class="value">{{ $daysPaid !== null ? $num($daysPaid) : '-' }}</span></td>
            <td style="width: 16%;"><span class="label">Fecha pago</span><br><span class="value">{{ $safe($paymentDate) }}</span></td>
            <td style="width: 36%;"><span class="label">Tipo de contrato</span><br><span class="value">{{ $safe($contractTypeText) }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Salario diario</span><br><span class="value">{{ $salaryDaily !== null ? $money($salaryDaily) : '-' }}</span></td>
            <td><span class="label">Salario integrado</span><br><span class="value">{{ $salaryIntegrated !== null ? $money($salaryIntegrated) : '-' }}</span></td>
            <td colspan="2"><span class="label">Nómina</span><br><span class="value">{{ $safe($payrollRun->name ?? null) }}</span></td>
        </tr>
    </table>
</div>

<table class="two-col">
    <tr>
        <td style="padding-right: 2px;">
            <div class="box">
                <div class="box-title">Percepciones</div>
                <table class="tight">
                    <thead>
                        <tr>
                            <th style="width: 11%;">Clave</th>
                            <th style="width: 35%;">Concepto</th>
                            <th style="width: 10%;">Cant.</th>
                            <th style="width: 14%;">Gravado</th>
                            <th style="width: 14%;">Exento</th>
                            <th style="width: 16%;">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($perceptions as $line)
                            <tr>
                                <td class="center">{{ $safe($conceptKey($line)) }}</td>
                                <td>{{ $safe($line['name'] ?? $line['concept'] ?? null) }}</td>
                                <td class="right">{{ $num($line['quantity'] ?? 0) }}</td>
                                <td class="right">{{ $money($line['taxable_amount'] ?? $line['amount'] ?? 0) }}</td>
                                <td class="right">{{ $money($line['exempt_amount'] ?? 0) }}</td>
                                <td class="right">{{ $money($line['amount'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="center">Sin percepciones registradas.</td>
                            </tr>
                        @endforelse
                        <tr class="total-row">
                            <td colspan="5" class="right">Total Percepciones</td>
                            <td class="right">{{ $money($gross) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>
        <td style="padding-left: 2px;">
            <div class="box">
                <div class="box-title">Deducciones</div>
                <table class="tight">
                    <thead>
                        <tr>
                            <th style="width: 14%;">Clave</th>
                            <th style="width: 56%;">Concepto</th>
                            <th style="width: 30%;">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deductions as $line)
                            <tr>
                                <td class="center">{{ $safe($conceptKey($line)) }}</td>
                                <td>{{ $safe($line['name'] ?? $line['concept'] ?? null) }}</td>
                                <td class="right">{{ $money($line['amount'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="center">Sin deducciones registradas.</td>
                            </tr>
                        @endforelse
                        <tr class="total-row">
                            <td colspan="2" class="right">Total Deducciones</td>
                            <td class="right">{{ $money($deductionsTotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>
    </tr>
</table>

<table class="two-col" style="margin-top: 2px;">
    <tr>
        <td style="padding-right: 2px;">
            <div class="box">
                <div class="box-title">CFDI / Timbrado</div>
                <div class="pad">
                    <table class="kv">
                        <tr>
                            <td class="k">UUID</td>
                            <td class="v">{{ $safe($receipt->uuid ?? null, 'Sin UUID') }}</td>
                        </tr>
                        <tr>
                            <td class="k">Fecha timbrado</td>
                            <td class="v">{{ $safe($receipt->stamped_at ?? null) }}</td>
                        </tr>
                        <tr>
                            <td class="k">XML</td>
                            <td class="v path-text">{{ $safe($receipt->xml_path ?? null) }}</td>
                        </tr>
                    </table>

                    <div style="margin-top: 6px;">
                        <div class="bold small">UUID / Folio fiscal</div>
                        <div class="uuid-box">{{ $safe($receipt->uuid ?? null, 'Sin UUID') }}</div>
                    </div>
                </div>
            </div>
        </td>
        <td style="padding-left: 2px;">
            <div class="box">
                <div class="box-title">Resumen</div>
                <table class="summary">
                    <tr>
                        <td>Total percepciones</td>
                        <td class="right">{{ $money($gross) }}</td>
                    </tr>
                    <tr>
                        <td>Total deducciones</td>
                        <td class="right">{{ $money($deductionsTotal) }}</td>
                    </tr>
                    <tr class="grand">
                        <td>Importe Neto</td>
                        <td class="right">{{ $money($net) }}</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="footer">
    Recibo generado por Bexia ERP.
    @if($isDemo)
        TIMBRADO DEMO DEV - NO FISCAL - NO PAC/SAT.
    @endif
</div>
</body>
</html>
