<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Valorización de inventario</title>
    <style>
        @page { margin: 18px 18px 20px 18px; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            font-size: 9px;
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
            font-size: 10px;
            margin: 2px 0;
        }

        .logo {
            max-height: 58px;
            max-width: 160px;
        }

        .summary {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin: 8px 0 12px 0;
        }

        .summary td {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 7px;
            width: 25%;
        }

        .label {
            color: #6b7280;
            font-size: 8px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .value {
            font-size: 13px;
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
            font-size: 7.5px;
            text-transform: uppercase;
            padding: 5px 4px;
            border-bottom: 1px solid #d1d5db;
        }

        table.data td {
            padding: 5px 4px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            font-size: 8px;
        }

        .right { text-align: right; }
        .bold { font-weight: bold; }
        .muted { color: #6b7280; font-size: 7.5px; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="title">Valorización de inventario</div>
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
            <td><div class="label">Partidas</div><div class="value">{{ number_format($summary['lines'] ?? 0) }}</div></td>
            <td><div class="label">Cantidad total</div><div class="value">{{ number_format($summary['quantity'] ?? 0, 2) }}</div></td>
            <td><div class="label">Valor total</div><div class="value">$ {{ number_format($summary['value'] ?? 0, 2) }}</div></td>
            <td><div class="label">Alertas</div><div class="value">{{ number_format($summary['with_alerts'] ?? 0) }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Empresa</th>
                <th>Almacén</th>
                <th>Ubicación</th>
                <th>Producto</th>
                <th>Variante</th>
                <th>Lote</th>
                <th>Serie</th>
                <th class="right">Cantidad</th>
                <th class="right">Costo unit.</th>
                <th class="right">Valor</th>
                <th>Método</th>
                <th>Fuente costo</th>
                <th>Alertas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->company_name }}</td>
                    <td>{{ $row->warehouse_name }}</td>
                    <td>{{ $row->location_name }}</td>
                    <td>
                        <div class="bold">{{ $row->product_name }}</div>
                        <div class="muted">#{{ $row->product_id }}</div>
                    </td>
                    <td>{{ $row->variant_name ?: '—' }}</td>
                    <td>{{ $row->lot_number ?: ($row->lot_id ?: '—') }}</td>
                    <td>{{ $row->serial_number ?: ($row->stock_serial_number_id ?: '—') }}</td>
                    <td class="right bold">{{ number_format($row->quantity, 2) }}</td>
                    <td class="right">$ {{ number_format($row->unit_cost, 4) }}</td>
                    <td class="right bold">$ {{ number_format($row->total_value, 2) }}</td>
                    <td>{{ $row->costing_method }}</td>
                    <td>{{ $row->cost_source }}</td>
                    <td>{{ $row->alerts ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
