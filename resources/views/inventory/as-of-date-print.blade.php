<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Inventario a fecha</title>
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

        .note {
            border: 1px solid #fcd34d;
            background: #fffbeb;
            color: #92400e;
            padding: 7px;
            margin-bottom: 10px;
            border-radius: 6px;
            font-size: 8px;
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
        .negative { color: #dc2626; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="title">Inventario a fecha</div>
                    <div class="subtitle">{{ $company->name ?? 'Empresa' }}</div>
                    <div class="subtitle">Corte: {{ $summary['cutoff_at'] ?? '—' }}</div>
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

    <div class="note">
        Reporte de existencias al corte indicado. El resultado depende de los movimientos registrados en el sistema.
    </div>

    <table class="summary">
        <tr>
            <td><div class="label">Líneas</div><div class="value">{{ number_format($summary['lines'] ?? 0) }}</div></td>
            <td><div class="label">Cantidad total</div><div class="value">{{ number_format($summary['total_quantity'] ?? 0, 2) }}</div></td>
            <td><div class="label">Positivos</div><div class="value">{{ number_format($summary['positive_lines'] ?? 0) }}</div></td>
            <td><div class="label">Negativos</div><div class="value">{{ number_format($summary['negative_lines'] ?? 0) }}</div></td>
            <td><div class="label">Ceros</div><div class="value">{{ number_format($summary['zero_lines'] ?? 0) }}</div></td>
            <td><div class="label">Con lote</div><div class="value">{{ number_format($summary['with_lot'] ?? 0) }}</div></td>
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
                <th class="right">Existencia al corte</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->company_name ?: ('Empresa #' . $row->company_id) }}</td>
                    <td>{{ $row->warehouse_name ?: ('Almacén #' . $row->warehouse_id) }}</td>
                    <td>{{ $row->location_name ?: ('Ubicación #' . $row->location_id) }}</td>
                    <td>
                        <div class="bold">{{ $row->product_name ?: ('Producto #' . $row->product_id) }}</div>
                        <div class="muted">#{{ $row->product_id }}</div>
                    </td>
                    <td>
                        {{ $row->variant_name ?: 'Sin variante' }}
                        @if ($row->variant_id)
                            <div class="muted">#{{ $row->variant_id }}</div>
                        @endif
                    </td>
                    <td>{{ $row->lot_number ?: 'Sin lote' }}</td>
                    <td class="right {{ $row->quantity_as_of < 0 ? 'negative' : 'bold' }}">
                        {{ number_format((float) $row->quantity_as_of, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
