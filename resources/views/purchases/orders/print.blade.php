<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orden de compra {{ $order->number ?? '' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #111827;
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            font-size: 12px;
        }
        .page {
            width: 216mm;
            min-height: 279mm;
            margin: 0 auto;
            background: white;
            padding: 20mm 16mm;
        }
        .print-bar {
            width: 216mm;
            margin: 12px auto;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
        .btn {
            border: 1px solid #cbd5e1;
            background: #fff;
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-primary {
            border-color: #2563eb;
            background: #2563eb;
            color: white;
        }
        .header {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }
        .brand {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .logo {
            max-width: 140px;
            max-height: 62px;
            object-fit: contain;
        }
        .brand-name {
            color: #2563eb;
            font-size: 24px;
            font-weight: 800;
        }
        .muted { color: #64748b; }
        .title {
            text-align: right;
        }
        .title h1 {
            margin: 0;
            font-size: 25px;
        }
        .title .folio {
            margin-top: 6px;
            font-weight: 700;
        }
        .separator {
            border-top: 2px solid #334155;
            margin: 22px 0 14px;
        }
        h2 {
            margin: 14px 0 8px;
            font-size: 14px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        .info td {
            border: 1px solid #cbd5e1;
            padding: 6px;
        }
        .info .label {
            width: 18%;
            background: #f8fafc;
            font-weight: 700;
        }
        .lines th {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 7px 6px;
            text-align: left;
            font-size: 11px;
        }
        .lines td {
            border: 1px solid #cbd5e1;
            padding: 6px;
            font-size: 11px;
        }
        .right { text-align: right; }
        .totals {
            width: 42%;
            margin-left: auto;
            margin-top: 12px;
        }
        .totals td {
            padding: 6px;
            border-bottom: 1px solid #cbd5e1;
        }
        .totals .label {
            font-weight: 700;
            text-align: right;
        }
        .totals .total {
            font-size: 14px;
            font-weight: 800;
        }
        .notes {
            min-height: 54px;
            border: 1px solid #cbd5e1;
            padding: 8px;
            margin-top: 8px;
        }
        .footer {
            margin-top: 26px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
        }
        .signature {
            margin-top: 44px;
            border-top: 1px solid #334155;
            text-align: center;
            padding-top: 6px;
            color: #334155;
        }
        @media print {
            body { background: white; }
            .print-bar { display: none; }
            .page { width: auto; min-height: auto; margin: 0; padding: 12mm; }
        }
    </style>
</head>
<body>
    <div class="print-bar">
        <button class="btn" onclick="history.back()">Regresar</button>
        <button class="btn btn-primary" onclick="window.print()">Imprimir / guardar PDF</button>
    </div>

    <main class="page">
        <section class="header">
            <div>
                <div class="brand">
                    @if($logoSrc)
                        <img src="{{ $logoSrc }}" class="logo" alt="Logo">
                    @else
                        <div class="brand-name">{{ $company->name ?? 'BexiaERP' }}</div>
                    @endif
                </div>
                <div class="muted" style="margin-top:6px;">Orden de compra</div>
            </div>

            <div class="title">
                <h1>Orden de compra</h1>
                <div class="folio">Folio: {{ $order->number ?? '—' }}</div>
                <div><strong>Estado:</strong> {{ match($order->status ?? '') {
                    'draft' => 'Borrador',
                    'review' => 'Pendiente de revisión',
                    'confirmed' => 'Confirmada',
                    'received' => 'Recibida',
                    'cancelled' => 'Cancelada',
                    default => ucfirst((string) ($order->status ?? '—')),
                } }}</div>
                <div class="muted">Generado: {{ $generatedAt->format('d/m/Y H:i') }}</div>
            </div>
        </section>

        <div class="separator"></div>

        <h2>Datos generales</h2>
        <table class="info">
            <tr>
                <td class="label">Proveedor</td>
                <td>{{ $order->supplier_name ?? '—' }}</td>
                <td class="label">Fecha</td>
                <td>{{ $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d/m/Y H:i') : '—' }}</td>
            </tr>
            <tr>
                <td class="label">Almacén destino</td>
                <td>{{ $order->warehouse_label ?? '—' }}</td>
                <td class="label">Ubicación / recepción</td>
                <td>{{ $order->location_label ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Origen</td>
                <td>{{ $order->origin ?? '—' }}</td>
                <td class="label">Líneas</td>
                <td>{{ $lines->count() }}</td>
            </tr>
        </table>

        <h2>Productos</h2>
        <table class="lines">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Variante</th>
                    <th>Unidad</th>
                    <th class="right">Cantidad</th>
                    <th class="right">Cant. base</th>
                    <th class="right">Costo s/IVA</th>
                    <th class="right">IVA</th>
                    <th class="right">Importe</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lines as $line)
                    <tr>
                        <td>{{ $line->product_label ?? '—' }}</td>
                        <td>{{ $line->variant_label ?? '—' }}</td>
                        <td>{{ $line->purchase_unit_label ?? '—' }}</td>
                        <td class="right">{{ number_format((float) ($line->ordered_quantity ?? 0), 2) }}</td>
                        <td class="right">{{ number_format((float) ($line->base_quantity ?? 0), 2) }}</td>
                        <td class="right">$ {{ number_format((float) ($line->unit_cost_without_tax ?? 0), 4) }}</td>
                        <td class="right">{{ number_format((float) ($line->tax_rate ?? 0), 2) }}%</td>
                        <td class="right">$ {{ number_format((float) ($line->line_total_with_tax ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;">Sin productos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td class="label">Importe sin impuestos:</td>
                <td class="right">$ {{ number_format((float) ($order->total_without_tax ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td class="label">IVA:</td>
                <td class="right">$ {{ number_format((float) ($order->total_tax ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td class="label total">Total:</td>
                <td class="right total">$ {{ number_format((float) ($order->total_with_tax ?? 0), 2) }}</td>
            </tr>
        </table>

        <h2>Notas / términos</h2>
        <div class="notes">{{ $order->notes ?: '—' }}</div>

        <section class="footer">
            <div class="signature">Solicitó / Compras</div>
            <div class="signature">Autorizó</div>
        </section>
    </main>
</body>
</html>
