<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Antigüedad de saldos CxC</title>
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
        table.detail { width: 100%; border-collapse: collapse; }
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
            <div class="title">Antigüedad de saldos CxC</div>
            <div class="muted">Fecha de corte: {{ $asOfDate }}</div>
            <div class="muted">Impreso: {{ now()->format('Y-m-d H:i') }}</div>
        </div>
    </div>

    <table class="summary">
        <tr>
            <td><div class="label">Total saldo</div><div class="value">${{ number_format($summary['total'], 2) }} MXN</div></td>
            <td><div class="label">Por vencer</div><div class="value">${{ number_format($summary['not_due'], 2) }} MXN</div></td>
            <td><div class="label">1 a 30 días</div><div class="value">${{ number_format($summary['days_1_30'], 2) }} MXN</div></td>
            <td><div class="label">31 a 60 días</div><div class="value">${{ number_format($summary['days_31_60'], 2) }} MXN</div></td>
            <td><div class="label">61 a 90 días</div><div class="value">${{ number_format($summary['days_61_90'], 2) }} MXN</div></td>
            <td><div class="label">Más de 90 días</div><div class="value">${{ number_format($summary['days_90_plus'], 2) }} MXN</div></td>
        </tr>
    </table>

    <table class="detail">
        <thead>
            <tr>
                <th>Folio</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Vence</th>
                <th class="right">Días vencido</th>
                <th>Rango</th>
                <th class="right">Total</th>
                <th class="right">Cobrado</th>
                <th class="right">Saldo</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->number }}</td>
                    <td>{{ $row->customer_name ?: '-' }}</td>
                    <td>{{ $row->issue_date ?: '-' }}</td>
                    <td>{{ $row->due_date ?: '-' }}</td>
                    <td class="right">{{ $row->days_overdue }}</td>
                    <td>{{ $row->bucket_label }}</td>
                    <td class="right">${{ number_format((float) $row->total, 2) }} {{ $row->currency }}</td>
                    <td class="right">${{ number_format((float) $row->collected_total, 2) }} {{ $row->currency }}</td>
                    <td class="right">${{ number_format((float) $row->balance_total, 2) }} {{ $row->currency }}</td>
                    <td>{{ \App\Filament\Resources\AccountReceivableResource::statusLabel($row->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="10">Sin cuentas por cobrar abiertas.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
