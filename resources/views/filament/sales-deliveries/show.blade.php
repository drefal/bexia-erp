@php
    use Illuminate\Support\Facades\DB;

    $order = DB::table('sales_orders')
        ->where('id', $delivery->sales_order_id)
        ->first();

    $lines = DB::table('sale_delivery_lines')
        ->where('sale_delivery_id', $delivery->id)
        ->orderBy('id')
        ->get();

    $movement = null;
    $movementLines = collect();

    if (! empty($delivery->stock_movement_id)) {
        $movement = DB::table('stock_movements')
            ->where('id', $delivery->stock_movement_id)
            ->first();

        $movementLines = DB::table('stock_movement_lines')
            ->where('stock_movement_id', $delivery->stock_movement_id)
            ->orderBy('id')
            ->get();
    }

    $statusLabel = match ((string) $delivery->status) {
        'draft' => 'Borrador',
        'done' => 'Hecha',
        'cancelled' => 'Cancelada',
        default => $delivery->status ?: 'Sin estado',
    };

    $typeLabel = match ((string) $delivery->delivery_type) {
        'complete' => 'Completa',
        'partial' => 'Parcial',
        default => $delivery->delivery_type ?: 'Sin tipo',
    };

    $tenant = $delivery->company_id;
@endphp

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Entrega {{ $delivery->number }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            margin: 0;
            background: #f8fafc;
            color: #0f172a;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 14px;
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
            padding: 28px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .breadcrumb {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .title {
            font-size: 28px;
            font-weight: 800;
            margin: 0;
        }

        .subtitle {
            color: #475569;
            margin-top: 6px;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #0f172a;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            border-color: #2563eb;
            background: #2563eb;
            color: #ffffff;
        }

        .btn-success {
            border-color: #16a34a;
            background: #16a34a;
            color: #ffffff;
        }

        .btn-danger {
            border-color: #dc2626;
            background: #dc2626;
            color: #ffffff;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #dbe3ef;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        }

        .card-title {
            font-weight: 800;
            margin-bottom: 12px;
        }

        .row {
            margin: 6px 0;
            color: #334155;
        }

        .label {
            color: #64748b;
            font-weight: 700;
        }

        .badge {
            display: inline-flex;
            border-radius: 999px;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            padding: 3px 8px;
            font-size: 12px;
            font-weight: 700;
            background: #eff6ff;
        }

        .badge-draft {
            color: #92400e;
            border-color: #fde68a;
            background: #fffbeb;
        }

        .badge-done {
            color: #166534;
            border-color: #bbf7d0;
            background: #f0fdf4;
        }

        .badge-cancelled {
            color: #991b1b;
            border-color: #fecaca;
            background: #fef2f2;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .right {
            text-align: right;
        }

        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            align-items: center;
            justify-content: center;
            background: rgba(17, 24, 39, 0.55);
            padding: 24px;
        }

        .modal {
            width: 100%;
            max-width: 520px;
            border-radius: 18px;
            background: #ffffff;
            padding: 26px;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28);
            border: 1px solid rgba(226, 232, 240, 0.9);
        }

        .modal-title {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .modal-body {
            font-size: 14px;
            line-height: 1.65;
            color: #475569;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 26px;
        }

        @media (max-width: 800px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <div>
            <div class="breadcrumb">Inventario / Entregas de venta / Revisar</div>
            <h1 class="title">Entrega {{ $delivery->number }}</h1>
            <div class="subtitle">
                Orden: {{ $order->number ?? ('#' . $delivery->sales_order_id) }}
                · Estado:
                <span class="badge {{ $delivery->status === 'draft' ? 'badge-draft' : ($delivery->status === 'done' ? 'badge-done' : 'badge-cancelled') }}">
                    {{ $statusLabel }}
                </span>
                · Tipo: {{ $typeLabel }}
            </div>
        </div>

        <div class="actions">
            <a class="btn" href="{{ route('sales.deliveries.print', ['saleDelivery' => $delivery->id]) }}" target="_blank">
                Imprimir
            </a>

            @if($delivery->status === 'draft')
                <button class="btn btn-success" type="button" onclick="openValidateModal()">
                    Validar entrega
                </button>
            @endif

            <a class="btn" href="{{ url('/admin/' . $tenant . '/sale-orders/' . $delivery->sales_order_id . '/edit') }}">
                Abrir orden
            </a>

            <a class="btn" href="{{ url('/admin/' . $tenant . '/sale-deliveries') }}">
                Volver
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="card" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534;margin-bottom:16px;">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="card" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;margin-bottom:16px;">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid">
        <div class="card">
            <div class="card-title">Datos de la entrega</div>
            <div class="row"><span class="label">Folio:</span> {{ $delivery->number }}</div>
            <div class="row"><span class="label">Estado:</span> {{ $statusLabel }}</div>
            <div class="row"><span class="label">Tipo:</span> {{ $typeLabel }}</div>
            <div class="row"><span class="label">Creada:</span> {{ $delivery->created_at }}</div>
            <div class="row"><span class="label">Validada:</span> {{ $delivery->delivered_at ?: 'No validada' }}</div>
            <div class="row"><span class="label">Movimiento:</span> {{ $delivery->stock_movement_id ?: 'Sin movimiento' }}</div>
        </div>

        <div class="card">
            <div class="card-title">Orden relacionada</div>
            <div class="row"><span class="label">Folio:</span> {{ $order->number ?? '—' }}</div>
            <div class="row"><span class="label">Cliente:</span> {{ $order->customer_name ?? '—' }}</div>
            <div class="row"><span class="label">Estado orden:</span> {{ $order->status ?? '—' }}</div>
            <div class="row"><span class="label">Almacén:</span> {{ $delivery->warehouse_id }}</div>
            <div class="row"><span class="label">Origen:</span> {{ $delivery->source_location_id }}</div>
            <div class="row"><span class="label">Destino:</span> {{ $delivery->destination_location_id }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Productos de la entrega</div>
        <table>
            <thead>
            <tr>
                <th>Producto</th>
                <th>Variante</th>
                <th class="right">Cantidad</th>
                <th>Movimiento línea</th>
            </tr>
            </thead>
            <tbody>
            @foreach($lines as $line)
                <tr>
                    <td>{{ $line->product_label }}</td>
                    <td>{{ $line->variant_label ?: '—' }}</td>
                    <td class="right">{{ number_format((float) $line->quantity, 2) }}</td>
                    <td>{{ $line->stock_movement_line_id ?: 'Pendiente' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if($movement)
        <div class="card" style="margin-top:16px;">
            <div class="card-title">Movimiento de inventario</div>
            <div class="row"><span class="label">Movimiento:</span> #{{ $movement->id }}</div>
            <div class="row"><span class="label">Referencia:</span> {{ $movement->reference }}</div>
            <div class="row"><span class="label">Estado:</span> {{ $movement->status }}</div>
            <div class="row"><span class="label">Documento origen:</span> {{ $movement->origin_document }}</div>

            <table style="margin-top:12px;">
                <thead>
                <tr>
                    <th>Producto ID</th>
                    <th>Variante ID</th>
                    <th class="right">Solicitado</th>
                    <th class="right">Hecho</th>
                </tr>
                </thead>
                <tbody>
                @foreach($movementLines as $line)
                    <tr>
                        <td>{{ $line->product_id }}</td>
                        <td>{{ $line->product_variant_id ?: '—' }}</td>
                        <td class="right">{{ number_format((float) $line->requested_quantity, 2) }}</td>
                        <td class="right">{{ number_format((float) $line->done_quantity, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($delivery->notes)
        <div class="card" style="margin-top:16px;">
            <div class="card-title">Notas</div>
            {{ $delivery->notes }}
        </div>
    @endif
</div>

@if($delivery->status === 'draft')
    <form id="validate-delivery-form" method="POST" action="{{ route('sales-deliveries.validate', ['saleDelivery' => $delivery->id]) }}">
        @csrf
    </form>

    <div id="validate-delivery-modal" class="modal-backdrop">
        <div class="modal">
            <div class="modal-title">Validar entrega</div>
            <div class="modal-body">
                Se validará la entrega, se generará el movimiento de salida y se descontará inventario.
                ¿Deseas continuar?
            </div>
            <div class="modal-actions">
                <button type="button" class="btn" onclick="closeValidateModal()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('validate-delivery-form').submit()">Aceptar</button>
            </div>
        </div>
    </div>

    <script>
        function openValidateModal() {
            document.getElementById('validate-delivery-modal').style.display = 'flex';
        }

        function closeValidateModal() {
            document.getElementById('validate-delivery-modal').style.display = 'none';
        }
    </script>
@endif
</body>
</html>
