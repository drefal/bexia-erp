<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Trazabilidad de producto</title>
    <style>
        @page { margin: 18px 18px 20px 18px; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            font-size: 8px;
            margin: 0;
        }

        .header {
            width: 100%;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            border: 0;
            padding: 0;
            vertical-align: middle;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 4px 0;
        }

        .subtitle {
            color: #4b5563;
            font-size: 9px;
            margin: 2px 0;
        }

        .logo {
            max-height: 58px;
            max-width: 160px;
        }

        .summary {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px;
            margin: 8px 0 12px 0;
        }

        .summary td {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 6px;
            width: 16.66%;
        }

        .label {
            color: #6b7280;
            font-size: 7px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .value {
            font-size: 12px;
            font-weight: bold;
            margin-top: 3px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th {
            background: #f3f4f6;
            color: #374151;
            text-align: left;
            font-size: 6.5px;
            text-transform: uppercase;
            padding: 4px 3px;
            border-bottom: 1px solid #d1d5db;
        }

        table.data td {
            padding: 4px 3px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            font-size: 7px;
        }

        .right { text-align: right; }
        .bold { font-weight: bold; }
        .muted { color: #6b7280; font-size: 6.5px; }
        .ok { color: #047857; font-weight: bold; }
        .warn { color: #b45309; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="title">Trazabilidad de producto</div>
                    <div class="subtitle">{{ $company->name ?? 'Empresa' }}</div>
                    <div class="subtitle">Generado: {{ $generatedAt->format('d/m/Y H:i') }}</div>
                </td>
                <td style="text-align:right;">
                    @if ($logoDataUri)
                        <img src="{{ $logoDataUri }}" class="logo" alt="Logo empresa">
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="summary">
        <tr>
            <td><div class="label">Movimientos</div><div class="value">{{ number_format($summary['lines'] ?? 0) }}</div></td>
            <td><div class="label">Entradas</div><div class="value">{{ number_format($summary['in_qty'] ?? 0, 2) }}</div></td>
            <td><div class="label">Salidas</div><div class="value">{{ number_format($summary['out_qty'] ?? 0, 2) }}</div></td>
            <td><div class="label">Neto</div><div class="value">{{ number_format($summary['net_qty'] ?? 0, 2) }}</div></td>
            <td><div class="label">Con lote / serie</div><div class="value">{{ number_format($summary['with_lot'] ?? 0) }} / {{ number_format($summary['with_serial'] ?? 0) }}</div></td>
            <td><div class="label">Origen histórico</div><div class="value">{{ number_format($summary['legacy_origin'] ?? 0) }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Origen</th>
                <th>Documento</th>
                <th>Producto</th>
                <th>Lote / serie</th>
                <th>Ubicaciones</th>
                <th class="right">Entrada</th>
                <th class="right">Salida</th>
                <th class="right">Costo unit.</th>
                <th class="right">Costo total</th>
                <th>Método / fuente</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>
                        <div class="bold">{{ $row->date_label ? \Illuminate\Support\Carbon::parse($row->date_label)->format('d/m/Y H:i') : '—' }}</div>
                    </td>
                    <td>
                        <div class="bold">{{ $row->origin_label }}</div>
                        <div class="muted">{{ $row->operation_label }}</div>
                        <div class="{{ $row->source_type ? 'ok' : 'warn' }}">{{ $row->legacy_label }}</div>
                    </td>
                    <td>
                        <div class="bold">{{ $row->reference ?: '—' }}</div>
                        <div class="muted">{{ $row->document_label }}</div>
                        @if ($row->source_reference_label)
                            <div class="muted">{{ $row->source_reference_label }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="bold">{{ $row->product_name }}</div>
                        <div class="muted">{{ $row->variant_name ?: 'Sin variante' }}</div>
                        <div class="muted">#{{ $row->product_id }}</div>
                    </td>
                    <td>
                        <div>{{ $row->lot_number ?: '—' }}</div>
                        <div class="muted">{{ $row->serial_number ?: '—' }}</div>
                    </td>
                    <td>
                        <div>{{ $row->warehouse_name ?: '—' }}</div>
                        <div class="muted">{{ $row->location_flow }}</div>
                    </td>
                    <td class="right bold">{{ $row->direction === 'in' ? number_format($row->quantity_abs, 2) : '—' }}</td>
                    <td class="right bold">{{ $row->direction === 'out' ? number_format($row->quantity_abs, 2) : '—' }}</td>
                    <td class="right">$ {{ number_format((float) ($row->unit_cost ?? 0), 4) }}</td>
                    <td class="right bold">$ {{ number_format((float) ($row->total_cost ?? 0), 2) }}</td>
                    <td>
                        <div>{{ $row->costing_method_label }}</div>
                        <div class="muted">{{ $row->cost_source_label }}</div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
