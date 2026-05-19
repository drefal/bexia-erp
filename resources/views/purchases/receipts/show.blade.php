@php
    $money = fn ($v) => '$' . number_format((float) $v, 2);
    $qty = fn ($v) => number_format((float) $v, 6);

    $orderUrl = $order
        ? url('/admin/' . $tenantId . '/purchase-orders/' . $order->id . '/edit')
        : null;

    $movementUrl = $movement
        ? url('/admin/' . $tenantId . '/stock-movements/' . $movement->id . '/edit')
        : null;

    $pdfUrl = url('/admin/' . $tenantId . '/purchase-receipts/' . $receipt->id . '/pdf');
@endphp

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->number ?? 'Recepción' }}</title>
    <style>
        body {
            margin: 0;
            background: #f8fafc;
            color: #0f172a;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .page {
            padding: 28px;
        }

        .toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 16px;
        }

        .btn {
            min-height: 40px;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #0f172a;
            padding: 0 16px;
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            font-weight: 800;
        }

        .btn-primary {
            background: #2563eb;
            border-color: #2563eb;
            color: #ffffff;
            box-shadow: 0 12px 22px rgba(37, 99, 235, .22);
        }

        .card {
            background: #ffffff;
            border: 1px solid #dbe3ef;
            border-radius: 18px;
            box-shadow: 0 12px 26px rgba(15, 23, 42, .06);
            overflow: hidden;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 24px;
            border-bottom: 1px solid #e5e7eb;
        }

        .brand {
            display: flex;
            gap: 14px;
            align-items: center;
        }

        .brand img {
            max-width: 130px;
            max-height: 70px;
            object-fit: contain;
        }

        h1 {
            margin: 0;
            font-size: 24px;
        }

        .muted {
            color: #64748b;
            font-size: 13px;
        }

        .badge {
            display: inline-flex;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            padding: 5px 11px;
            font-size: 12px;
            font-weight: 900;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
        }

        .field {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 12px;
            background: #f8fafc;
        }

        .label {
            font-size: 11px;
            color: #64748b;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 4px;
        }

        .value {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            background: #f8fafc;
            text-align: left;
            padding: 11px 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 12px;
            color: #334155;
        }

        td {
            padding: 11px 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .right {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .totals {
            display: flex;
            justify-content: flex-end;
            padding: 18px 24px;
        }

        .totals table {
            width: 330px;
        }

        .totals td {
            border: 0;
            padding: 5px 0;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .toolbar {
                display: none;
            }

            .page {
                padding: 0;
            }

            .card {
                border: 0;
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="toolbar">
        @if($orderUrl)
            <a class="btn" href="{{ $orderUrl }}">Ver OC</a>
        @endif

        @if($movementUrl)
            <a class="btn" href="{{ $movementUrl }}">Ver movimiento</a>
        @endif

        <a class="btn btn-primary" href="{{ $pdfUrl }}" target="_blank">PDF / Imprimir</a>
    </div>

    <div class="card">
        <div class="header">
            <div class="brand">
                @if(! empty($companyInfo['logo']))
                    <img src="{{ $companyInfo['logo'] }}" alt="Logo">
                @endif

                <div>
                    <h1>Recepción de compra</h1>
                    <div class="muted">{{ $companyInfo['name'] ?? 'Empresa' }}</div>
                    @if(! empty($companyInfo['rfc']))
                        <div class="muted">RFC: {{ $companyInfo['rfc'] }}</div>
                    @endif
                </div>
            </div>

            <div style="text-align:right;">
                <div style="font-size:20px; font-weight:900;">{{ $receipt->number ?? ('REC #' . $receipt->id) }}</div>
                <div style="margin-top:8px;"><span class="badge">Recibida</span></div>
            </div>
        </div>

        <div class="grid">
            <div class="field">
                <div class="label">Orden de compra</div>
                <div class="value">{{ $order->number ?? '—' }}</div>
            </div>

            <div class="field">
                <div class="label">Movimiento inventario</div>
                <div class="value">{{ $movement->reference ?? '—' }}</div>
            </div>

            <div class="field">
                <div class="label">Fecha recepción</div>
                <div class="value">{{ $receipt->received_at ? \Carbon\Carbon::parse($receipt->received_at)->format('d/m/Y H:i') : '—' }}</div>
            </div>

            <div class="field">
                <div class="label">Almacén</div>
                <div class="value">{{ $warehouse->name ?? ($receipt->warehouse_id ? 'Almacén #' . $receipt->warehouse_id : '—') }}</div>
            </div>

            <div class="field">
                <div class="label">Ubicación</div>
                <div class="value">{{ $location->name ?? ($receipt->location_id ? 'Ubicación #' . $receipt->location_id : '—') }}</div>
            </div>

            <div class="field">
                <div class="label">Recibió</div>
                <div class="value">{{ $receivedBy->name ?? $receivedBy->email ?? '—' }}</div>
            </div>
        </div>

        <div style="overflow:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Variante</th>
                        <th>Unidad</th>
                        <th class="right">Cantidad</th>
                        <th class="right">Costo s/IVA</th>
                        <th class="right">IVA</th>
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $line)
    @php
        $trackingType = (string) ($line->tracking_type ?? 'none');
    @endphp

    <tr>
        <td>
            <strong>{{ $line->product_label ?? 'Producto' }}</strong>

            @if($trackingType === 'lot' && ! empty($line->lot_number ?? null))
                <div style="margin-top:5px;">
                    <span style="display:inline-flex;border-radius:999px;border:1px solid #a7f3d0;background:#ecfdf5;color:#047857;padding:4px 8px;font-size:12px;font-weight:800;">
                        Lote: {{ $line->lot_number }}
                    </span>
                </div>
            @elseif($trackingType === 'serial')
                <div style="margin-top:5px;color:#64748b;font-size:12px;font-weight:700;">
                    Detalle abajo
                </div>
            @endif
        </td>
        <td>{{ $line->variant_label ?? '—' }}</td>
        <td>{{ $line->purchase_unit_label ?? '—' }}</td>
        <td class="right">{{ $qty($line->received_quantity ?? 0) }}</td>
        <td class="right">{{ $money($line->unit_cost_without_tax ?? 0) }}</td>
        <td class="right">{{ $money($line->line_tax ?? 0) }}</td>
        <td class="right"><strong>{{ $money($line->line_total_with_tax ?? 0) }}</strong></td>
    </tr>

    @include('purchases.receipts.partials.tracking-details', ['line' => $line, 'trackingDetailsColspan' => 7])
@endforeach
                </tbody>
            </table>
        </div>

        <div class="totals">
            <table>
                <tr>
                    <td>Importe sin impuestos:</td>
                    <td class="right"><strong>{{ $money($receipt->total_without_tax ?? 0) }}</strong></td>
                </tr>
                <tr>
                    <td>IVA:</td>
                    <td class="right"><strong>{{ $money($receipt->total_tax ?? 0) }}</strong></td>
                </tr>
                <tr>
                    <td style="font-size:16px;">Total:</td>
                    <td class="right" style="font-size:16px;"><strong>{{ $money($receipt->total_with_tax ?? 0) }}</strong></td>
                </tr>
            </table>
        </div>
    </div>
</div>
</body>
</html>
