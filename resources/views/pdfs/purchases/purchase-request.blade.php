<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud de compra</title>
    <style>
        @page { margin: 18px 22px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
        }
        table { width: 100%; border-collapse: collapse; }
        .title {
            font-size: 24px;
            font-weight: bold;
            text-align: right;
            color: #0f172a;
        }
        .muted {
            color: #6b7280;
            font-size: 10px;
        }
        .hr {
            border-top: 2px solid #334155;
            margin: 8px 0 12px 0;
        }
        .block-title {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 6px;
            color: #111827;
        }
        .meta td,
        .lines th,
        .lines td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            font-size: 10px;
        }
        .meta-label,
        .lines th {
            background: #f1f5f9;
            font-weight: bold;
        }
        .num {
            text-align: right;
            white-space: nowrap;
        }
        .totals td {
            padding: 4px 6px;
            font-size: 10px;
        }
        .totals-label {
            text-align: right;
            font-weight: bold;
        }
        .totals-value {
            text-align: right;
            width: 120px;
            white-space: nowrap;
        }
        .notes {
            border: 1px solid #cbd5e1;
            padding: 8px;
            min-height: 50px;
            font-size: 10px;
        }
        .logo {
            max-height: 58px;
            max-width: 190px;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    @php
        $number = data_get($purchaseRequest, 'number') ?: ('SC-' . data_get($purchaseRequest, 'id'));
        $statusMap = [
            'draft' => 'Borrador',
            'review' => 'Pendiente de revisión',
            'pending_review' => 'Pendiente de revisión',
            'approved' => 'Aprobada',
            'cancelled' => 'Cancelada',
            'converted' => 'Convertida',
        ];
        $status = $statusMap[data_get($purchaseRequest, 'status')] ?? ucfirst((string) data_get($purchaseRequest, 'status'));
        $supplier = data_get($purchaseRequest, 'supplier_name') ?: 'Sin proveedor';
        $origin = data_get($purchaseRequest, 'origin') ?: 'manual';
        $notes = data_get($purchaseRequest, 'notes') ?: data_get($purchaseRequest, 'notes_terms') ?: data_get($purchaseRequest, 'terms') ?: '';
        $date = data_get($purchaseRequest, 'request_date')
            ?: data_get($purchaseRequest, 'date')
            ?: data_get($purchaseRequest, 'created_at');
    @endphp

    <table>
        <tr>
            <td style="vertical-align: top;">
                @if(! empty($companyLogo))
                    <img src="{{ $companyLogo }}" class="logo">
                    <div class="muted">{{ $companyName ?? '' }}</div>
                @else
                    <div style="font-size: 22px; font-weight: bold; color: #2563eb;">
                        {{ $companyName ?? 'BexiaERP' }}
                    </div>
                    <div class="muted">Solicitud de compra</div>
                @endif
            </td>
            <td style="vertical-align: top; text-align: right;">
                <div class="title">Solicitud de compra</div>
                <div><strong>Folio:</strong> {{ $number }}</div>
                <div><strong>Estado:</strong> {{ $status }}</div>
                <div class="muted">Generado: {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="hr"></div>

    <div class="block-title">Datos generales</div>
    <table class="meta" style="margin-bottom: 12px;">
        <tr>
            <td class="meta-label">Proveedor</td>
            <td>{{ $supplier }}</td>
            <td class="meta-label">Fecha</td>
            <td>
                @if($date instanceof \Carbon\CarbonInterface)
                    {{ $date->format('d/m/Y H:i') }}
                @else
                    {{ $date }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="meta-label">Almacén destino</td>
            <td>{{ $warehouseLabel ?? '—' }}</td>
            <td class="meta-label">Ubicación / recepción</td>
            <td>{{ $locationLabel ?? '—' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Origen</td>
            <td>{{ $origin }}</td>
            <td class="meta-label">Líneas</td>
            <td>{{ $lines->count() }}</td>
        </tr>
    </table>

    <div class="block-title">Productos</div>
    <table class="lines">
        <thead>
            <tr>
                <th style="width: 24%;">Producto</th>
                <th style="width: 16%;">Variante</th>
                <th style="width: 10%;">Unidad</th>
                <th class="num" style="width: 9%;">Cantidad</th>
                <th class="num" style="width: 10%;">Cantidad base</th>
                <th class="num" style="width: 10%;">Costo s/IVA</th>
                <th class="num" style="width: 8%;">IVA</th>
                <th class="num" style="width: 13%;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                @php
                    $qty = (float) ($line->requested_quantity ?? 0);
                    $baseQty = (float) ($line->base_quantity ?? $qty);
                    $cost = (float) ($line->unit_cost_without_tax ?? 0);
                    $taxRate = (float) ($line->tax_rate ?? $line->tax_percent ?? 0);
                    $lineSubtotal = (float) (
                        $line->line_total_without_tax
                        ?? ($qty * $cost)
                    );
                    $lineTotal = (float) (
                        $line->line_total_with_tax
                        ?? ($lineSubtotal * (1 + ($taxRate / 100)))
                    );
                @endphp
                <tr>
                    <td>{{ $line->product_label ?? '—' }}</td>
                    <td>{{ $line->variant_label ?? '—' }}</td>
                    <td>{{ $line->purchase_unit_label ?? '—' }}</td>
                    <td class="num">{{ number_format($qty, 2) }}</td>
                    <td class="num">{{ number_format($baseQty, 2) }}</td>
                    <td class="num">$ {{ number_format($cost, 4) }}</td>
                    <td class="num">{{ number_format($taxRate, 2) }}%</td>
                    <td class="num">$ {{ number_format($lineTotal, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center;">No hay líneas registradas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals" style="margin-top: 10px;">
        <tr>
            <td class="totals-label">Importe sin impuestos:</td>
            <td class="totals-value">$ {{ number_format((float) $subtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="totals-label">IVA:</td>
            <td class="totals-value">$ {{ number_format((float) $taxTotal, 2) }}</td>
        </tr>
        <tr>
            <td class="totals-label" style="font-size: 12px;">Total:</td>
            <td class="totals-value" style="font-size: 12px; font-weight: bold;">
                $ {{ number_format((float) $total, 2) }}
            </td>
        </tr>
    </table>

    <div style="margin-top: 14px;" class="block-title">Notas / términos</div>
    <div class="notes">
        {{ $notes ?: '—' }}
    </div>
</body>
</html>
