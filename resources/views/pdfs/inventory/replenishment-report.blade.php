<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: letter landscape;
            margin: 8mm 7mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 7.2px;
            line-height: 1.12;
            color: #111827;
        }

        .header {
            width: 100%;
            border-bottom: 1.4px solid #111827;
            padding-bottom: 5px;
            margin-bottom: 6px;
        }

        .logo {
            max-width: 100px;
            max-height: 38px;
            object-fit: contain;
        }

        .title {
            font-size: 15px;
            font-weight: bold;
            text-align: right;
        }

        .muted {
            color: #6b7280;
            font-size: 7px;
        }

        .summary td {
            padding: 2px 5px;
            vertical-align: top;
        }

        .summary .label {
            font-weight: bold;
            color: #374151;
            width: 70px;
        }

        .stats {
            margin: 5px 0 7px;
            width: 100%;
            border-collapse: collapse;
        }

        .stats td {
            border: 0.5px solid #cbd5e1;
            padding: 4px 6px;
        }

        .group-title {
            font-size: 9px;
            font-weight: bold;
            background: #f3f4f6;
            border: 0.6px solid #cbd5e1;
            padding: 4px 5px;
            margin-top: 6px;
        }

        table.lines {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 6.2px;
            line-height: 1.05;
        }

        .lines thead {
            display: table-header-group;
        }

        .lines tr {
            page-break-inside: avoid;
        }

        .lines th {
            background: #f3f4f6;
            border: 0.5px solid #cbd5e1;
            padding: 2px;
            text-align: left;
            font-weight: bold;
        }

        .lines td {
            border: 0.5px solid #cbd5e1;
            padding: 2px;
            vertical-align: top;
        }

        .right {
            text-align: right;
            white-space: nowrap;
        }

        .center {
            text-align: center;
        }

        .nowrap {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 35%;">
                @if ($logoPath)
                    <img src="{{ $logoPath }}" class="logo">
                @else
                    <div style="font-size: 13px; font-weight: bold;">{{ $companyName }}</div>
                @endif
            </td>
            <td style="width: 65%; text-align: right;">
                <div class="title">{{ $title }}</div>
                <div class="muted">{{ $companyName }}</div>
                <div class="muted">Generado: {{ $generatedAt }}</div>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            @foreach ($filters as $label => $value)
                <td>
                    <span class="label">{{ $label }}:</span>
                    {{ $value }}
                </td>
            @endforeach
        </tr>
    </table>

    <table class="stats">
        <tr>
            <td><strong>Líneas:</strong> {{ number_format($totals['rules']) }}</td>
            <td><strong>Faltantes:</strong> {{ number_format($totals['shortages']) }}</td>
            <td><strong>Cantidad sugerida total:</strong> {{ number_format($totals['suggested_total'], 2) }}</td>
        </tr>
    </table>

    @forelse ($groupedRows as $group => $rows)
        <div class="group-title">{{ $group }} - {{ count($rows) }} línea(s)</div>

        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 10%;">Almacén</th>
                    <th style="width: 9%;">Ubicación</th>
                    <th style="width: 13%;">Producto</th>
                    <th style="width: 12%;">Variante</th>
                    <th style="width: 5%;" class="right">Disp.</th>
                    <th style="width: 5%;" class="right">Mín.</th>
                    <th style="width: 5%;" class="right">Máx.</th>
                    <th style="width: 6%;" class="right">Falt.</th>
                    <th style="width: 5%;" class="right">UXES</th>
                    <th style="width: 6%;" class="right">Mín. compra</th>
                    <th style="width: 6%;" class="right">Múlt.</th>
                    <th style="width: 6%;" class="right">Sugerido</th>
                    <th style="width: 8%;">Proveedor</th>
                    <th style="width: 5%;">Prior.</th>
                    <th style="width: 9%;">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['warehouse'] }}</td>
                        <td>{{ $row['location'] }}</td>
                        <td>{{ $row['product'] }}</td>
                        <td>{{ $row['variant'] }}</td>
                        <td class="right">{{ number_format($row['available'], 2) }}</td>
                        <td class="right">{{ number_format($row['min'], 2) }}</td>
                        <td class="right">{{ number_format($row['max'], 2) }}</td>
                        <td class="right">{{ number_format($row['base_needed'], 2) }}</td>
                        <td class="right">{{ number_format($row['pack_units'], 2) }}</td>
                        <td class="right">{{ $row['purchase_min'] > 0 ? number_format($row['purchase_min'], 2) : '-' }}</td>
                        <td class="right">{{ number_format($row['purchase_multiple'], 2) }}</td>
                        <td class="right"><strong>{{ number_format($row['suggested'], 2) }}</strong></td>
                        <td>{{ $row['supplier'] }}</td>
                        <td>{{ $row['priority_label'] }}</td>
                        <td>{{ $row['status'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <div style="padding: 14px; border: 0.6px solid #cbd5e1; text-align: center;">
            No hay líneas para mostrar.
        </div>
    @endforelse
</body>
</html>
