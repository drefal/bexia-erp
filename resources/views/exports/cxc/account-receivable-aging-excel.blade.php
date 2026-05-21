<table>
    <tr><th colspan="10">Antigüedad de saldos CxC - {{ $company->name ?? 'Empresa' }}</th></tr>
    <tr><td>Fecha de corte</td><td>{{ $asOfDate }}</td></tr>
    <tr><td>Generado</td><td>{{ now()->format('Y-m-d H:i') }}</td></tr>
</table>

<table border="1">
    <tr>
        <th>Total saldo</th>
        <th>Por vencer</th>
        <th>1 a 30 días</th>
        <th>31 a 60 días</th>
        <th>61 a 90 días</th>
        <th>Más de 90 días</th>
    </tr>
    <tr>
        <td>{{ $summary['total'] }}</td>
        <td>{{ $summary['not_due'] }}</td>
        <td>{{ $summary['days_1_30'] }}</td>
        <td>{{ $summary['days_31_60'] }}</td>
        <td>{{ $summary['days_61_90'] }}</td>
        <td>{{ $summary['days_90_plus'] }}</td>
    </tr>
</table>

<br>

<table border="1">
    <tr>
        <th>Folio</th>
        <th>Cliente</th>
        <th>Referencia</th>
        <th>Fecha</th>
        <th>Vence</th>
        <th>Días vencido</th>
        <th>Rango</th>
        <th>Total</th>
        <th>Cobrado</th>
        <th>Saldo</th>
        <th>Moneda</th>
        <th>Estado</th>
    </tr>
    @foreach($rows as $row)
        <tr>
            <td>{{ $row->number }}</td>
            <td>{{ $row->customer_name }}</td>
            <td>{{ $row->customer_reference }}</td>
            <td>{{ $row->issue_date }}</td>
            <td>{{ $row->due_date }}</td>
            <td>{{ $row->days_overdue }}</td>
            <td>{{ $row->bucket_label }}</td>
            <td>{{ (float) $row->total }}</td>
            <td>{{ (float) $row->collected_total }}</td>
            <td>{{ (float) $row->balance_total }}</td>
            <td>{{ $row->currency }}</td>
            <td>{{ \App\Filament\Resources\AccountReceivableResource::statusLabel($row->status) }}</td>
        </tr>
    @endforeach
</table>
