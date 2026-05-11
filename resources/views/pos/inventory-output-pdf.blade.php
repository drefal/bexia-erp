<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Salida de inventario PDV</title>

    <style>
        @page {
            margin: 24px 28px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
        }

        .header {
            width: 100%;
            border-bottom: 1px solid #111827;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .header-table,
        .grid-table,
        .signatures {
            width: 100%;
            border-collapse: collapse;
        }

        .logo-cell {
            width: 90px;
            vertical-align: top;
        }

        .logo {
            max-width: 78px;
            max-height: 78px;
        }

        .title-cell {
            vertical-align: top;
        }

        .doc-cell {
            width: 210px;
            text-align: right;
            vertical-align: top;
        }

        .company {
            font-size: 16px;
            font-weight: bold;
        }

        .title {
            font-size: 20px;
            font-weight: bold;
            margin-top: 4px;
        }

        .muted {
            color: #6b7280;
            font-size: 10px;
        }

        .ref {
            font-size: 16px;
            font-weight: bold;
        }

        .section {
            margin-top: 12px;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 6px 8px;
        }

        .box {
            border: 1px solid #d1d5db;
            border-top: 0;
            padding: 8px;
        }

        .grid-table td {
            width: 25%;
            vertical-align: top;
            padding: 4px 8px 6px 0;
        }

        .label {
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: bold;
        }

        .value {
            margin-top: 2px;
            font-weight: bold;
        }

        table.lines {
            width: 100%;
            border-collapse: collapse;
        }

        table.lines th {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 6px;
            font-size: 10px;
            text-align: left;
        }

        table.lines td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
        }

        .right {
            text-align: right;
        }

        .message {
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #166534;
            padding: 7px;
            margin-top: 8px;
        }

        .signatures {
            margin-top: 55px;
        }

        .signatures td {
            width: 50%;
            text-align: center;
            padding: 0 28px;
        }

        .signature-line {
            border-top: 1px solid #111827;
            padding-top: 6px;
        }

        .footer {
            position: fixed;
            bottom: -8px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #6b7280;
        }
    </style>
</head>
<body>
@php
    $movementStatus = (string) ($movement->status ?? '');
    $movementStatusLabel = match ($movementStatus) {
        'draft' => 'Borrador',
        'waiting' => 'En espera',
        'confirmed' => 'Confirmado',
        'assigned' => 'Reservado',
        'done' => 'Realizado',
        'cancelled', 'canceled' => 'Cancelado',
        default => $movementStatus !== '' ? ucfirst(str_replace('_', ' ', $movementStatus)) : '—',
    };
@endphp

<div class="header">
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if($logoSrc)
                    <img class="logo" src="{{ $logoSrc }}" alt="Logo">
                @endif
            </td>

            <td class="title-cell">
                @if(! $logoSrc)
                    <div class="company">{{ $companyName }}</div>
                @endif

                <div class="title">Salida de inventario PDV</div>
                <div class="muted">Generada desde ticket de Punto de Venta</div>
            </td>

            <td class="doc-cell">
                <div class="label">Referencia salida PDV</div>
                <div class="ref">{{ $movement->reference ?? '—' }}</div>
                <div class="muted">Generado: {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Cabecera de la salida</div>
    <div class="box">
        <table class="grid-table">
            <tr>
                <td>
                    <div class="label">Ticket origen</div>
                    <div class="value">{{ $order->number ?? ('#' . $order->id) }}</div>
                </td>
                <td>
                    <div class="label">Estado</div>
                    <div class="value">{{ $movementStatusLabel }}</div>
                </td>
                <td>
                    <div class="label">Fecha movimiento</div>
                    <div class="value">{{ $movement->movement_at ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">Movimiento</div>
                    <div class="value">#{{ $movement->id }}</div>
                </td>
            </tr>
        </table>

        @if(! empty($metadata['inventory_message']))
            <div class="message">{{ $metadata['inventory_message'] }}</div>
        @endif
    </div>
</div>

<div class="section">
    <div class="section-title">Líneas de salida</div>

    <table class="lines">
        <thead>
            <tr>
                <th>Producto</th>
                <th class="right">Solicitado</th>
                <th class="right">Realizado</th>
                <th class="right">Costo unitario</th>
                <th>Notas</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movementLines as $line)
                @php
                    $product = $products->get((int) ($line->product_id ?? 0));
                    $productName = $product->name ?? ('Producto #' . ($line->product_id ?? ''));
                @endphp

                <tr>
                    <td>
                        <strong>{{ $productName }}</strong>
                        @if(! empty($product?->sku))
                            <br><span class="muted">{{ $product->sku }}</span>
                        @endif
                    </td>
                    <td class="right">{{ number_format((float) ($line->requested_quantity ?? 0), 2) }}</td>
                    <td class="right"><strong>{{ number_format((float) ($line->done_quantity ?? 0), 2) }}</strong></td>
                    <td class="right">${{ number_format((float) ($line->unit_cost ?? 0), 2) }}</td>
                    <td>{{ $line->notes ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;">Sin líneas de salida.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<table class="signatures">
    <tr>
        <td>
            <div class="signature-line">Entregó</div>
        </td>
        <td>
            <div class="signature-line">Recibió / Validó</div>
        </td>
    </tr>
</table>

<div class="footer">
    Bexia ERP · Salida de inventario PDV · {{ $movement->reference ?? '' }}
</div>
</body>
</html>
