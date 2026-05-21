<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Estado de cuenta clientes CxC</title>
    <style>
        @page { size: letter landscape; margin: 9mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5px; color: #111827; }
        .header { display: table; width: 100%; border-bottom: 2px solid #111827; padding-bottom: 7px; margin-bottom: 9px; }
        .brand, .doc { display: table-cell; vertical-align: top; }
        .brand { width: 55%; }
        .doc { width: 45%; text-align: right; }
        .logo { max-height: 36px; max-width: 150px; margin-bottom: 4px; }
        .company { font-size: 13px; font-weight: 800; }
        .title { font-size: 15px; font-weight: 800; }
        .muted { color: #6b7280; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 5px; margin-bottom: 8px; }
        .summary td { border: 1px solid #d1d5db; border-radius: 6px; padding: 7px; background: #f9fafb; }
        .summary .label { color: #6b7280; font-size: 7px; }
        .summary .value { margin-top: 3px; font-size: 10px; font-weight: 800; }
        .section { margin-top: 10px; font-size: 10px; font-weight: 800; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        table.detail { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .detail th, .detail td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; }
        .detail th { background: #f3f4f6; text-transform: uppercase; font-size: 7px; text-align: left; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">
            @if($logoSrc)<img src="{{ $logoSrc }}" class="logo" alt="Logo">@endif
            <div class="company">{{ $company->name ?? 'Empresa' }}</div>
            <div class="muted">Bexia ERP · Cuentas por cobrar</div>
        </div>
        <div class="doc">
            <div class="title">Estado de cuenta clientes CxC</div>
            <div class="muted">Periodo: {{ $dateFrom }} a {{ $dateTo }}</div>
            <div class="muted">Impreso: {{ now()->format('Y-m-d H:i') }}</div>
        </div>
    </div>

    <table class="summary">
        <tr>
            <td><div class="label">Documentos</div><div class="value">${{ number_format($totals['receivables_total'], 2) }} MXN</div></td>
            <td><div class="label">Cobrado en documentos</div><div class="value">${{ number_format($totals['collected_total'], 2) }} MXN</div></td>
            <td><div class="label">Saldo pendiente</div><div class="value">${{ number_format($totals['balance_total'], 2) }} MXN</div></td>
            <td><div class="label">Cobros aplicados periodo</div><div class="value">${{ number_format($totals['payments_total'], 2) }} MXN</div></td>
            <td><div class="label">Cobros cancelados periodo</div><div class="value">${{ number_format($totals['cancelled_payments_total'], 2) }} MXN</div></td>
        </tr>
    </table>

    <div class="section">Documentos CxC</div>
    <table class="detail">
        <thead>
            <tr>
                <th>Folio</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Vence</th>
                <th>Estado</th>
                <th class="right">Total</th>
                <th class="right">Cobrado</th>
                <th class="right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($receivables as $row)
                <tr>
                    <td>{{ $row->number }}</td>
                    <td>{{ $row->customer_name ?: '-' }}</td>
                    <td>{{ $row->issue_date ?: '-' }}</td>
                    <td>{{ $row->due_date ?: '-' }}</td>
                    <td>{{ \App\Filament\Resources\AccountReceivableResource::statusLabel($row->status) }}</td>
                    <td class="right">${{ number_format((float) $row->total, 2) }} {{ $row->currency }}</td>
                    <td class="right">${{ number_format((float) $row->collected_total, 2) }} {{ $row->currency }}</td>
                    <td class="right">${{ number_format((float) $row->balance_total, 2) }} {{ $row->currency }}</td>
                </tr>
            @empty
                <tr><td colspan="8">Sin documentos en el periodo.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section">Cobros</div>
    <table class="detail">
        <thead>
            <tr>
                <th>Cobro</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>CxC</th>
                <th>Estado</th>
                <th>Póliza</th>
                <th>Referencia</th>
                <th class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $row)
                <tr>
                    <td>#{{ $row->id }}</td>
                    <td>{{ $row->payment_date ?: '-' }}</td>
                    <td>{{ $row->customer_name ?: '-' }}</td>
                    <td>{{ $row->receivable_number }}</td>
                    <td>{{ \App\Filament\Resources\AccountReceivablePaymentResource::statusLabel($row->status) }}</td>
                    <td>{{ $row->accounting_entry_number ?: ($row->accounting_entry_id ?: 'Sin póliza') }}</td>
                    <td>{{ $row->reference ?: '-' }}</td>
                    <td class="right">${{ number_format((float) $row->amount, 2) }} {{ $row->currency }}</td>
                </tr>
            @empty
                <tr><td colspan="8">Sin cobros en el periodo.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
