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
            font-size: 7.3px;
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
            font-size: 6.8px;
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

        .out {
            color: #6b7280;
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
            <td><strong>Líneas:</strong> {{ number_format($totals['lines']) }}</td>
            <td><strong>Total sugerido c/IVA:</strong> $ {{ number_format($totals['full_total'], 2) }}</td>
            <td><strong>Dentro presupuesto:</strong> $ {{ number_format($totals['included_total'], 2) }}</td>
            <td><strong>Fuera presupuesto:</strong> $ {{ number_format($totals['out_total'], 2) }}</td>
        </tr>
    </table>

    @forelse ($groupedRows as $supplier => $rows)
        @php
            $includedSubtotal = collect($rows)->where('included_in_budget', true)->sum('estimated_total');
            $outSubtotal = collect($rows)->where('included_in_budget', false)->sum('estimated_total');
            $hasBudget = ($totals['budget'] ?? 0) > 0;
        @endphp

        <div class="group-title">
            {{ $supplier }} - {{ count($rows) }} línea(s)
            | Dentro: $ {{ number_format($includedSubtotal, 2) }}
            @if ($hasBudget)
                | Fuera: $ {{ number_format($outSubtotal, 2) }}
            @endif
        </div>

        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 24%;">Producto</th>
                    <th style="width: 17%;">Variante</th>
                    <th style="width: 17%;">Ubicación</th>
                    <th style="width: 9%;" class="right">Disponible</th>
                    <th style="width: 9%;" class="right">Sugerido</th>
                    <th style="width: 10%;" class="right">Costo c/IVA</th>
                    <th style="width: 14%;" class="right">Importe c/IVA</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="{{ ($hasBudget && ! $row['included_in_budget']) ? 'out' : '' }}">
                        <td>{{ $row['product'] }}</td>
                        <td>{{ $row['variant'] }}</td>
                        <td>{{ $row['location'] }}</td>
                        <td class="right">{{ number_format($row['available'], 2) }}</td>
                        <td class="right"><strong>{{ number_format($row['suggested'], 2) }}</strong></td>
                        <td class="right">$ {{ number_format($row['unit_cost'], 4) }}</td>
                        <td class="right"><strong>$ {{ number_format($row['estimated_total'], 2) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <div style="padding: 14px; border: 0.6px solid #cbd5e1; text-align: center;">
            No hay productos sugeridos para compra.
        </div>
    @endforelse
</body>
</html>
