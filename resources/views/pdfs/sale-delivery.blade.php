<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle }} {{ $delivery->number ?? '' }}</title>
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

        .strong {
            font-weight: 800;
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

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 999px;
            border: 1px solid #cbd5e1;
            font-size: 7.5px;
            font-weight: 800;
            color: #334155;
        }

        .totals-wrap {
            width: 100%;
            margin-top: 12px;
        }

        .totals {
            width: 240px;
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

        .note {
            margin-top: 12px;
            border: 1px solid #e5e7eb;
            padding: 8px;
            border-radius: 8px;
            color: #334155;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 46px;
        }

        .signatures td {
            width: 50%;
            padding: 0 22px;
            text-align: center;
            vertical-align: bottom;
        }

        .signature-line {
            border-top: 1px solid #111827;
            padding-top: 6px;
            color: #475569;
            font-size: 8px;
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
    </style>
@php
    $pdfLotLabel = function ($lotId) {
        if (empty($lotId) || ! \Illuminate\Support\Facades\Schema::hasTable('stock_lots')) {
            return '—';
        }

        $lot = \Illuminate\Support\Facades\DB::table('stock_lots')->where('id', $lotId)->first();

        return $lot->lot_number ?? ('#' . $lotId);
    };
@endphp
</head>
<body>
@php
    $companyName = trim((string) ($company->name ?? 'BexiaERP'));
    $deliveryNumber = trim((string) ($delivery->number ?? ''));
    $orderNumber = trim((string) ($order->number ?? ''));
    $customerName = trim((string) ($order->customer_name ?? $customer->commercial_name ?? $customer->name ?? ''));

    $typeLabel = match ((string) ($delivery->delivery_type ?? '')) {
        'complete' => 'Completa',
        'partial' => 'Parcial',
        default => $delivery->delivery_type ?: '—',
    };

    $statusLabel = match ((string) ($delivery->status ?? '')) {
        'draft' => 'Borrador',
        'done' => 'Validada',
        'cancelled' => 'Cancelada',
        default => $delivery->status ?: '—',
    };

    $totalQuantity = $lines->sum(fn ($line) => (float) ($line->quantity ?? 0));
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
                <div class="doc-number">{{ $deliveryNumber !== '' ? $deliveryNumber : ('#' . $delivery->id) }}</div>
                <div class="muted">Fecha: {{ $delivery->created_at ? \Carbon\Carbon::parse($delivery->created_at)->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</div>
                <div class="muted">Orden: {{ $orderNumber !== '' ? $orderNumber : ('#' . $delivery->sales_order_id) }}</div>
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
                @if(! empty($order->delivery_address))
                    <div class="row"><span class="label">Entrega:</span> {{ $order->delivery_address }}</div>
                @endif
            </div>
        </td>
        <td>
            <div class="box">
                <div class="box-title">Datos de entrega</div>
                <div class="row"><span class="label">Estado:</span> <span class="badge">{{ $statusLabel }}</span></div>
                <div class="row"><span class="label">Tipo:</span> <span class="badge">{{ $typeLabel }}</span></div>
                @if(! empty($warehouse->name))
                    <div class="row"><span class="label">Almacén:</span> {{ $warehouse->name }}</div>
                @endif
                @if(! empty($sourceLocation->name))
                    <div class="row"><span class="label">Origen:</span> {{ $sourceLocation->name }}</div>
                @endif
                @if(! empty($destinationLocation->name))
                    <div class="row"><span class="label">Destino:</span> {{ $destinationLocation->name }}</div>
                @endif
            </div>
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th style="width: 40%;">Producto</th>
            <th style="width: 23%;">Variante</th>
            <th style="width: 20%;">Lote</th>
            <th style="width: 17%;" class="right">Cantidad entregada</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lines as $line)
            <tr>
                <td class="strong">{{ $line->product_label ?? '—' }}</td>
                <td>{{ $line->variant_label ?: '—' }}</td>
                <td>{{ $pdfLotLabel($line->stock_lot_id ?? null) }}</td>
                <td class="right">{{ number_format((float) ($line->quantity ?? 0), 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="muted">Sin productos en esta entrega.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="totals-wrap">
    <table class="totals">
        <tr class="grand">
            <td>Total entregado:</td>
            <td class="right">{{ number_format((float) $totalQuantity, 2) }}</td>
        </tr>
    </table>
</div>

@if(! empty($delivery->notes))
    <div class="note">
        <div class="box-title">Notas</div>
        {{ $delivery->notes }}
    </div>
@endif

<table class="signatures">
    <tr>
        <td>
            <div class="signature-line">Entrega</div>
        </td>
        <td>
            <div class="signature-line">Recibe</div>
        </td>
    </tr>
</table>

<div class="footer">
    Documento generado por BexiaERP · {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
