<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibir orden de compra</title>
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

        .card {
            background: #fff;
            border: 1px solid #dbe3ef;
            border-radius: 16px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, .05);
            overflow: hidden;
        }

        .header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
        }

        h1 {
            margin: 0;
            font-size: 24px;
        }

        .muted {
            color: #64748b;
            font-size: 13px;
            margin-top: 6px;
        }

        .content {
            padding: 20px 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            background: #f8fafc;
            border-bottom: 1px solid #dbe3ef;
            padding: 10px;
            text-align: left;
            font-weight: 800;
        }

        td {
            border-bottom: 1px solid #edf2f7;
            padding: 10px;
            vertical-align: middle;
        }

        input, textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 8px 10px;
            min-height: 38px;
            outline: none;
        }

        input:focus, textarea:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .qty {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .receive-input {
            max-width: 150px;
            text-align: right;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            min-height: 42px;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            padding: 0 16px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            border-color: #2563eb;
            background: #2563eb;
            color: white;
            box-shadow: 0 12px 22px rgba(37, 99, 235, .25);
        }

        .btn-gray {
            background: white;
            color: #334155;
        }

        .btn-small {
            min-height: 34px;
            border-radius: 10px;
            padding: 0 12px;
            font-size: 12px;
        }

        .notes {
            margin-top: 18px;
        }

        .notice {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1e3a8a;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .error {
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #991b1b;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 16px;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="page">
    @if(session('error'))
        <div class="error">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="header">
            <h1>Recibir orden de compra</h1>
            <div class="muted">
                OC {{ $order->number ?? ('#' . $order->id) }}.
                Captura la cantidad recibida por producto. No puedes recibir más de lo pendiente.
            </div>
        </div>

        <form method="POST" action="{{ route('purchases.orders.receipts.store', ['purchaseOrder' => $order->id]) }}">
            @csrf

            <div class="content">
                <div class="notice">
                    Esta versión registra la recepción documental. El movimiento de inventario se conectará en el siguiente paso.
                </div>

                <table>
                    <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Variante</th>
                        <th>Unidad</th>
                        <th class="qty">Ordenado</th>
                        <th class="qty">Recibido</th>
                        <th class="qty">Pendiente</th>
                        <th class="qty">A recibir</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($lines as $line)
                        <tr>
                            <td>
                                <strong>{{ $line->product_label ?? 'Producto' }}</strong>
                            </td>
                            <td>{{ $line->variant_label ?? '—' }}</td>
                            <td>{{ $line->purchase_unit_label ?? 'Unidad' }}</td>
                            <td class="qty">{{ number_format((float) $line->ordered_for_view, 6) }}</td>
                            <td class="qty">{{ number_format((float) $line->received_for_view, 6) }}</td>
                            <td class="qty">{{ number_format((float) $line->pending_for_view, 6) }}</td>
                            <td class="qty">
                                <input
                                    class="receive-input"
                                    type="number"
                                    step="0.000001"
                                    min="0"
                                    max="{{ $line->pending_for_view }}"
                                    name="quantities[{{ $line->id }}]"
                                    value="{{ $line->pending_for_view > 0 ? number_format((float) $line->pending_for_view, 6, '.', '') : '0' }}"
                                    @disabled($line->pending_for_view <= 0)
                                >
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-gray btn-small"
                                    onclick="this.closest('tr').querySelector('input').value='0'"
                                >
                                    No recibir
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div class="notes">
                    <label style="font-weight:800; display:block; margin-bottom:6px;">Notas de recepción</label>
                    <textarea name="notes" rows="3" placeholder="Notas opcionales de recepción"></textarea>
                </div>

                <div class="actions">
                    <a class="btn btn-gray" href="{{ url('/admin/' . $tenantId . '/purchase-orders/' . $order->id . '/edit') }}">
                        Cancelar
                    </a>

                    <button type="submit" class="btn btn-primary">
                        Guardar recepción
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
</body>
</html>
