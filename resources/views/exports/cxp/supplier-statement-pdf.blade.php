<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 16px 0 6px; }
        .muted { color: #6b7280; }
        .header { width: 100%; margin-bottom: 12px; }
        .header td { border: 0; padding: 0; vertical-align: top; }
        .logo { text-align: right; }
        .logo img { max-height: 54px; max-width: 170px; }
        .summary { width: 100%; margin: 14px 0; border-collapse: collapse; }
        .summary td { border: 1px solid #d1d5db; padding: 6px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; font-weight: bold; }
        th, td { border: 1px solid #d1d5db; padding: 4px; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>Estado de cuenta proveedor CxP</h1>
                <div class="muted">{{ $company->name ?? 'Empresa' }} | Proveedor: {{ $supplierLabel }} | Periodo: {{ $dateFrom }} a {{ $dateTo }}</div>
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
            <td><b>Documentos</b><br>{{ '$' . number_format($totals['documents_total'], 2) . ' MXN' }}</td>
            <td><b>Pagado en documentos</b><br>{{ '$' . number_format($totals['paid_total'], 2) . ' MXN' }}</td>
            <td><b>Saldo pendiente</b><br>{{ '$' . number_format($totals['balance_total'], 2) . ' MXN' }}</td>
            <td><b>Pagos aplicados periodo</b><br>{{ '$' . number_format($totals['payments_total'], 2) . ' MXN' }}</td>
        </tr>
    </table>

    <h2>Documentos CxP</h2>
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Proveedor</th>
                <th>Fecha</th>
                <th>Vence</th>
                <th>Estado</th>
                <th class="right">Total</th>
                <th class="right">Pagado</th>
                <th class="right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payables as $row)
                <tr>
                    <td>{{ $row->number }}</td>
                    <td>{{ $row->supplier_name ?: 'Proveedor sin nombre' }}</td>
                    <td>{{ $row->issue_date }}</td>
                    <td>{{ $row->due_date }}</td>
                    <td>{{ $row->status }}</td>
                    <td class="right">{{ '$' . number_format((float) $row->total, 2) . ' ' . $row->currency }}</td>
                    <td class="right">{{ '$' . number_format((float) $row->paid_total, 2) . ' ' . $row->currency }}</td>
                    <td class="right">{{ '$' . number_format((float) $row->balance_total, 2) . ' ' . $row->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Pagos</h2>
    <table>
        <thead>
            <tr>
                <th>Pago</th>
                <th>Fecha</th>
                <th>Proveedor</th>
                <th>CxP</th>
                <th>Estado</th>
                <th>Póliza</th>
                <th>Referencia</th>
                <th class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $row)
                <tr>
                    <td>#{{ $row->id }}</td>
                    <td>{{ $row->payment_date }}</td>
                    <td>{{ $row->supplier_name ?: 'Proveedor sin nombre' }}</td>
                    <td>{{ $row->payable_number }}</td>
                    <td>{{ $row->status }}</td>
                    <td>{{ $row->entry_number ?: 'Sin póliza' }}</td>
                    <td>{{ $row->reference ?: '-' }}</td>
                    <td class="right">{{ '$' . number_format((float) $row->amount, 2) . ' ' . $row->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
