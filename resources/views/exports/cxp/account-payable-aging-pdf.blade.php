<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        .header { width: 100%; margin-bottom: 12px; }
        .header td { border: 0; padding: 0; vertical-align: top; }
        .logo { text-align: right; }
        .logo img { max-height: 54px; max-width: 170px; }
        .summary { width: 100%; margin: 14px 0; border-collapse: collapse; }
        .summary td { border: 1px solid #d1d5db; padding: 6px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; font-weight: bold; }
        th, td { border: 1px solid #d1d5db; padding: 5px; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>Antigüedad de saldos CxP</h1>
                <div class="muted">{{ $company->name ?? 'Empresa' }} | Corte: {{ $asOfDate }} | Proveedor: {{ $supplierLabel }}</div>
                @if($documentSearch)
                    <div class="muted">Búsqueda: {{ $documentSearch }}</div>
                @endif
            </td>
            <td class="logo">
                @if(! empty($logoSrc))
                    <img src="{{ $logoSrc }}" alt="Logo">
                @endif
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td><b>Total</b><br>{{ '$' . number_format($summary['total'], 2) . ' MXN' }}</td>
            <td><b>Por vencer</b><br>{{ '$' . number_format($summary['not_due'], 2) . ' MXN' }}</td>
            <td><b>1 a 30</b><br>{{ '$' . number_format($summary['days_1_30'], 2) . ' MXN' }}</td>
            <td><b>31 a 60</b><br>{{ '$' . number_format($summary['days_31_60'], 2) . ' MXN' }}</td>
            <td><b>61 a 90</b><br>{{ '$' . number_format($summary['days_61_90'], 2) . ' MXN' }}</td>
            <td><b>Más de 90</b><br>{{ '$' . number_format($summary['days_90_plus'], 2) . ' MXN' }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Proveedor</th>
                <th>Vence</th>
                <th>Días</th>
                <th>Rango</th>
                <th class="right">Total</th>
                <th class="right">Pagado</th>
                <th class="right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row->number }}</td>
                    <td>{{ $row->supplier_name }}</td>
                    <td>{{ $row->due_date }}</td>
                    <td class="right">{{ $row->days_overdue }}</td>
                    <td>{{ $row->bucket_label }}</td>
                    <td class="right">{{ '$' . number_format((float) $row->total, 2) . ' ' . $row->currency }}</td>
                    <td class="right">{{ '$' . number_format((float) $row->paid_total, 2) . ' ' . $row->currency }}</td>
                    <td class="right"><b>{{ '$' . number_format((float) $row->balance_total, 2) . ' ' . $row->currency }}</b></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
