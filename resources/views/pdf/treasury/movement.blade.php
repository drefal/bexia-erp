<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Movimiento de Tesorería</title>
    <style>
        @page { margin: 28px 32px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111827;
        }
        .header-table,
        .info-table,
        .amount-table,
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
        }
        .logo-box {
            width: 170px;
        }
        .logo-box img {
            max-width: 160px;
            max-height: 70px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .title {
            text-align: right;
            font-size: 20px;
            font-weight: bold;
        }
        .subtitle {
            text-align: right;
            color: #4b5563;
            font-size: 11px;
        }
        .section-title {
            margin-top: 18px;
            margin-bottom: 8px;
            padding: 6px 10px;
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            font-weight: bold;
        }
        .info-table td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
        }
        .label {
            width: 28%;
            background: #f9fafb;
            font-weight: bold;
        }
        .amount-box {
            margin-top: 18px;
            border: 1px solid #d1d5db;
        }
        .amount-table td {
            padding: 10px 12px;
            border-collapse: collapse;
        }
        .amount-label {
            background: #f9fafb;
            font-weight: bold;
            width: 70%;
        }
        .amount-value {
            text-align: right;
            font-size: 18px;
            font-weight: bold;
        }
        .notes {
            min-height: 90px;
            border: 1px solid #d1d5db;
            padding: 10px;
            white-space: pre-line;
        }
        .muted {
            color: #6b7280;
        }
        .footer-table {
            margin-top: 28px;
        }
        .footer-table td {
            width: 50%;
            padding-top: 28px;
            text-align: center;
            vertical-align: bottom;
        }
        .signature-line {
            border-top: 1px solid #9ca3af;
            margin: 0 24px;
            padding-top: 6px;
        }
        .small {
            font-size: 10px;
        }
    </style>
</head>
<body>
@php
    $companyName = trim((string) (
        $company->fiscal_name
        ?? $company->business_name
        ?? $company->trade_name
        ?? $company->name
        ?? 'Empresa'
    ));

    $accountName = trim((string) (
        $movement->treasuryAccount->name
        ?? 'Sin cuenta'
    ));

    $reference = trim((string) ($movement->reference ?? ''));
    $description = trim((string) ($movement->description ?? ''));
    $statusRaw = (string) ($movement->status ?? '');
    $typeRaw = (string) ($movement->type ?? '');
    $dateValue = $movement->movement_date ?? $movement->date ?? $movement->created_at ?? null;
    $amount = (float) ($movement->amount ?? 0);

    $statusLabel = match ($statusRaw) {
        'draft' => 'Borrador',
        'posted', 'confirmed' => 'Confirmado',
        'cancelled', 'canceled' => 'Cancelado',
        default => $statusRaw !== '' ? $statusRaw : '-',
    };

    $typeLabel = match ($typeRaw) {
        'inbound' => 'Entrada',
        'outbound' => 'Salida',
        default => $typeRaw !== '' ? $typeRaw : '-',
    };

    $number = trim((string) ($movement->number ?? $movement->folio ?? $movement->id));
@endphp

<table class="header-table">
    <tr>
        <td class="logo-box">
            @if(! empty($logoPath))
                <img src="{{ $logoPath }}" alt="Logo">
            @endif
        </td>
        <td>
            <div class="company-name">{{ $companyName }}</div>
            <div class="muted">
                Movimiento de Tesorería
            </div>
        </td>
        <td>
            <div class="title">Comprobante</div>
            <div class="subtitle">Movimiento #{{ $number }}</div>
        </td>
    </tr>
</table>

<div class="section-title">Datos del movimiento</div>

<table class="info-table">
    <tr>
        <td class="label">Movimiento</td>
        <td>{{ $number }}</td>
        <td class="label">Fecha</td>
        <td>{{ $dateValue ? \Carbon\Carbon::parse($dateValue)->format('d/m/Y') : '-' }}</td>
    </tr>
    <tr>
        <td class="label">Cuenta / Caja</td>
        <td>{{ $accountName }}</td>
        <td class="label">Tipo</td>
        <td>{{ $typeLabel }}</td>
    </tr>
    <tr>
        <td class="label">Estado</td>
        <td>{{ $statusLabel }}</td>
        <td class="label">Referencia</td>
        <td>{{ $reference !== '' ? $reference : '-' }}</td>
    </tr>
</table>

<div class="amount-box">
    <table class="amount-table">
        <tr>
            <td class="amount-label">Importe</td>
            <td class="amount-value">${{ number_format($amount, 2, '.', ',') }}</td>
        </tr>
    </table>
</div>

<div class="section-title">Descripción / concepto</div>
<div class="notes">{{ $description !== '' ? $description : 'Sin descripción.' }}</div>

<div class="section-title">Control de impresión</div>
<table class="info-table">
    <tr>
        <td class="label">Impreso por</td>
        <td>{{ $printedBy?->name ?? '-' }}</td>
        <td class="label">Fecha de impresión</td>
        <td>{{ $generatedAt ? \Carbon\Carbon::parse($generatedAt)->format('d/m/Y H:i') : '-' }}</td>
    </tr>
</table>

<table class="footer-table">
    <tr>
        <td>
            <div class="signature-line">
                Elaboró
            </div>
        </td>
        <td>
            <div class="signature-line">
                Autorizó
            </div>
        </td>
    </tr>
</table>

<p class="small muted" style="margin-top: 20px;">
    Documento generado desde Tesorería de Bexia ERP.
</p>
</body>
</html>
