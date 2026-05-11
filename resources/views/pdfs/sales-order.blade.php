<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle }} {{ $order->number ?? '' }}</title>
    <style>
        @page { margin: 20px 22px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 9px;
            line-height: 1.18;
        }
        .header {
            width: 100%;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 9px;
            margin-bottom: 8px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .logo-cell {
            width: 35%;
            vertical-align: top;
        }
        .doc-cell {
            width: 65%;
            vertical-align: top;
            text-align: right;
        }
        .logo {
            max-width: 110px;
            max-height: 40px;
        }
        .company-name {
            font-size: 8px;
            font-weight: 800;
            margin-top: 6px;
        }
        .doc-title {
            font-size: 16px;
            font-weight: 900;
            text-transform: uppercase;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .doc-number {
            font-size: 10.5px;
            font-weight: 700;
            color: #2563eb;
        }
        .muted {
            color: #64748b;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .grid td {
            width: 50%;
            vertical-align: top;
            padding: 0 8px 0 0;
        }
        .box {
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            padding: 7px;
            min-height: 68px;
        }
        .box-title {
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 6px;
            font-size: 8px;
            text-transform: uppercase;
        }
        .row {
            margin-bottom: 3px;
        }
        .label {
            color: #64748b;
            font-weight: 700;
        }
        .lines {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .lines th {
            background: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
            color: #0f172a;
            font-weight: 800;
            padding: 5px 5px;
            text-align: left;
            font-size: 8px;
        }
        .lines td {
            border-bottom: 1px solid #e5e7eb;
            padding: 5px 5px;
            vertical-align: top;
            font-size: 8px;
        }
        .right {
            text-align: right;
        }
        .strong {
            font-weight: 800;
        }
        .totals-wrap {
            width: 100%;
            margin-top: 12px;
        }
        .totals {
            width: 260px;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals td {
            padding: 4px 5px;
            border-bottom: 1px solid #e5e7eb;
        }
        .totals .grand td {
            font-size: 11px;
            font-weight: 900;
            border-top: 1px solid #94a3b8;
            border-bottom: 0;
            padding-top: 8px;
        }
        .footer {
            position: fixed;
            bottom: -6px;
            left: 0;
            right: 0;
            font-size: 7.5px;
            color: #64748b;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
        }
        .note {
            margin-top: 12px;
            border: 1px solid #e5e7eb;
            padding: 8px;
            border-radius: 8px;
            color: #334155;
        }
    </style>
</head>
<body>
@php
    $number = trim((string) ($order->number ?? ''));
    $companyName = trim((string) ($company->name ?? 'BexiaERP'));
    $customerName = trim((string) ($order->customer_name ?? $customer->commercial_name ?? $customer->name ?? ''));
    $currency = trim((string) ($order->currency ?? 'MXN'));
@endphp

<div class="header">
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if($logoSrc)
                    <img class="logo" src="{{ $logoSrc }}" alt="Logo">
                @endif
                <div class="company-name">{{ $companyName }}</div>
                @if(! empty($company->rfc))
                    <div class="muted">RFC: {{ $company->rfc }}</div>
                @endif
            </td>
            <td class="doc-cell">
                <div class="doc-title">{{ $documentTitle }}</div>
                <div class="doc-number">{{ $number !== '' ? $number : ('#' . $order->id) }}</div>
                <div class="muted">Fecha: {{ $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</div>
                @if(! empty($order->expected_delivery_date))
                    <div class="muted">Entrega estimada: {{ \Carbon\Carbon::parse($order->expected_delivery_date)->format('d/m/Y') }}</div>
                @endif
            </td>
        </tr>
    </table>
</div>

<table class="grid">
    <tr>
        <td>
            <div class="box">
                <div class="box-title">Cliente</div>
                <div class="row strong">{{ $customerName ?: '—' }}</div>
                @if(! empty($customer->rfc))
                    <div class="row"><span class="label">RFC:</span> {{ $customer->rfc }}</div>
                @endif
                @if(! empty($order->billing_address))
                    <div class="row"><span class="label">Facturación:</span> {{ $order->billing_address }}</div>
                @endif
                @if(! empty($order->delivery_address))
                    <div class="row"><span class="label">Entrega:</span> {{ $order->delivery_address }}</div>
                @endif
            </div>
        </td>
        <td>
            <div class="box">
                <div class="box-title">Condiciones</div>
                <div class="row"><span class="label">Lista de precios:</span> {{ $priceList->name ?? '—' }}</div>
                <div class="row"><span class="label">Moneda:</span> {{ $currency }}</div>
                <div class="row"><span class="label">Forma de pago:</span> {{ $order->payment_method ?? 'Por definir' }}</div>
                <div class="row"><span class="label">Términos:</span> {{ $order->payment_terms ?? '—' }}</div>
                @if(! empty($warehouse->name))
                    <div class="row"><span class="label">Almacén:</span> {{ $warehouse->name }}</div>
                @endif
                @if(! empty($location->name))
                    <div class="row"><span class="label">Ubicación:</span> {{ $location->name }}</div>
                @endif
            </div>
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th style="width: 31%;">Producto</th>
            <th style="width: 16%;">Variante</th>
            <th style="width: 10%;">Unidad</th>
            <th style="width: 9%;" class="right">Cantidad</th>
            <th style="width: 11%;" class="right">Precio s/IVA</th>
            <th style="width: 8%;" class="right">IVA</th>
            <th style="width: 15%;" class="right">Importe</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lines as $line)
            <tr>
                <td class="strong">{{ $line->product_label ?? '—' }}</td>
                <td>{{ $line->variant_label ?: '—' }}</td>
                <td>{{ $line->unit_label ?? 'Pieza' }}</td>
                <td class="right">{{ number_format((float) ($line->quantity ?? 0), 2) }}</td>
                <td class="right">$ {{ number_format((float) ($line->unit_price_without_tax ?? 0), 4) }}</td>
                <td class="right">{{ number_format((float) ($line->tax_rate ?? 0), 2) }}%</td>
                <td class="right strong">$ {{ number_format((float) ($line->line_total_with_tax ?? 0), 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="muted">Sin productos agregados.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="totals-wrap">
    <table class="totals">
        <tr>
            <td>Importe sin impuestos:</td>
            <td class="right">$ {{ number_format((float) ($order->total_without_tax ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td>IVA:</td>
            <td class="right">$ {{ number_format((float) ($order->total_tax ?? 0), 2) }}</td>
        </tr>
        <tr class="grand">
            <td>Total:</td>
            <td class="right">$ {{ number_format((float) ($order->total_with_tax ?? 0), 2) }}</td>
        </tr>
    </table>
</div>

@if(! empty($order->notes))
    <div class="note">
        <strong>Notas:</strong><br>
        {{ $order->notes }}
    </div>
@endif

<div class="footer">
    Documento generado por BexiaERP. Esta representación impresa no muestra costos ni semáforo de margen.
</div>
</body>
</html>
