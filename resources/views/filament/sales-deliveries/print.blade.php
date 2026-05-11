@php
    use Illuminate\Support\Facades\DB;

    $order = DB::table('sales_orders')->where('id', $delivery->sales_order_id)->first();

    $lines = DB::table('sale_delivery_lines')
        ->where('sale_delivery_id', $delivery->id)
        ->orderBy('id')
        ->get();

    $typeLabel = match ($delivery->delivery_type) {
        'complete' => 'Completa',
        'partial' => 'Parcial',
        default => $delivery->delivery_type ?: 'Sin tipo',
    };

    $statusLabel = match ($delivery->status) {
        'draft' => 'Borrador',
        'done' => 'Validada',
        'cancelled' => 'Cancelada',
        default => $delivery->status ?: 'Sin estado',
    };

    $totalQuantity = $lines->sum(fn ($line) => (float) $line->quantity);
@endphp

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Entrega {{ $delivery->number ?: ('#' . $delivery->id) }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f8fafc;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
        }

        .page {
            max-width: 900px;
            margin: 24px auto;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 28px;
        }

        .top-actions {
            max-width: 900px;
            margin: 24px auto 0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #111827;
            padding: 9px 14px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            border-color: #2563eb;
            background: #2563eb;
            color: #ffffff;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 18px;
            margin-bottom: 22px;
        }

        .brand {
            font-size: 24px;
            font-weight: 800;
            color: #1d4ed8;
        }

        .subtitle {
            color: #64748b;
            margin-top: 4px;
        }

        .doc-title {
            text-align: right;
        }

        .doc-title h1 {
            margin: 0;
            font-size: 22px;
            color: #111827;
        }

        .doc-title .number {
            margin-top: 6px;
            color: #475569;
            font-weight: 700;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .box {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
            background: #ffffff;
        }

        .box-title {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 8px;
        }

        .line {
            margin: 4px 0;
        }

        .label {
            color: #64748b;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th {
            background: #f1f5f9;
            color: #475569;
            font-size: 11px;
            text-align: left;
            text-transform: uppercase;
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: 11px 10px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .text-right {
            text-align: right;
        }

        .summary {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
        }

        .summary-box {
            width: 260px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            font-weight: 800;
        }

        .notes {
            margin-top: 22px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
            min-height: 70px;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 50px;
            margin-top: 70px;
        }

        .signature-line {
            border-top: 1px solid #111827;
            text-align: center;
            padding-top: 8px;
            color: #475569;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .top-actions {
                display: none !important;
            }

            .page {
                margin: 0;
                max-width: none;
                border: none;
                border-radius: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="top-actions">
        <button type="button" class="btn" onclick="window.close()">Cerrar</button>
        <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir</button>
    </div>

    <main class="page">
        <section class="header">
            <div>
                <div class="brand">BexiaERP</div>
                <div class="subtitle">Documento de entrega de venta</div>
            </div>

            <div class="doc-title">
                <h1>Entrega de venta</h1>
                <div class="number">{{ $delivery->number ?: ('Entrega #' . $delivery->id) }}</div>
            </div>
        </section>

        <section class="grid">
            <div class="box">
                <div class="box-title">Orden de venta</div>
                <div class="line"><span class="label">Folio:</span> {{ $order->number ?? ('Orden #' . $delivery->sales_order_id) }}</div>
                <div class="line"><span class="label">Cliente:</span> {{ $order->customer_name ?? 'Sin cliente' }}</div>
                <div class="line"><span class="label">Dirección entrega:</span> {{ $order->delivery_address ?? 'Sin dirección' }}</div>
            </div>

            <div class="box">
                <div class="box-title">Datos de entrega</div>
                <div class="line"><span class="label">Estado:</span> {{ $statusLabel }}</div>
                <div class="line"><span class="label">Tipo:</span> {{ $typeLabel }}</div>
                <div class="line"><span class="label">Fecha:</span> {{ $delivery->created_at }}</div>
            </div>
        </section>

        <section>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Variante</th>
                        <th class="text-right">Cantidad</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $line)
                        <tr>
                            <td>{{ $line->product_label ?: 'Producto' }}</td>
                            <td>{{ $line->variant_label ?: 'Sin variante' }}</td>
                            <td class="text-right">{{ number_format((float) $line->quantity, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="summary">
            <div class="summary-box">
                <div class="summary-row">
                    <span>Total entregado</span>
                    <span>{{ number_format((float) $totalQuantity, 2) }}</span>
                </div>
            </div>
        </section>

        <section class="notes">
            <div class="box-title">Notas</div>
            <div>{{ $delivery->notes ?: 'Sin notas' }}</div>
        </section>

        <section class="signatures">
            <div class="signature-line">Entrega</div>
            <div class="signature-line">Recibe</div>
        </section>
    </main>
</body>
</html>
