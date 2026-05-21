<table>
    <tr><th colspan="9">Estado de cuenta clientes CxC - {{ $company->name ?? 'Empresa' }}</th></tr>
    <tr><td>Periodo</td><td>{{ $dateFrom }} a {{ $dateTo }}</td></tr>
    <tr><td>Generado</td><td>{{ now()->format('Y-m-d H:i') }}</td></tr>
</table>

<table border="1">
    <tr>
        <th>Documentos</th>
        <th>Cobrado en documentos</th>
        <th>Saldo pendiente</th>
        <th>Cobros aplicados periodo</th>
        <th>Cobros cancelados periodo</th>
    </tr>
    <tr>
        <td>{{ $totals['receivables_total'] }}</td>
        <td>{{ $totals['collected_total'] }}</td>
        <td>{{ $totals['balance_total'] }}</td>
        <td>{{ $totals['payments_total'] }}</td>
        <td>{{ $totals['cancelled_payments_total'] }}</td>
    </tr>
</table>

<br>

<table border="1">
    <tr><th colspan="9">Documentos CxC</th></tr>
    <tr>
        <th>Folio</th>
        <th>Cliente</th>
        <th>Referencia</th>
        <th>Fecha</th>
        <th>Vence</th>
        <th>Estado</th>
        <th>Total</th>
        <th>Cobrado</th>
        <th>Saldo</th>
        <th>Moneda</th>
    </tr>
    @foreach($receivables as $row)
        <tr>
            <td>{{ $row->number }}</td>
            <td>{{ $row->customer_name }}</td>
            <td>{{ $row->customer_reference }}</td>
            <td>{{ $row->issue_date }}</td>
            <td>{{ $row->due_date }}</td>
            <td>{{ \App\Filament\Resources\AccountReceivableResource::statusLabel($row->status) }}</td>
            <td>{{ (float) $row->total }}</td>
            <td>{{ (float) $row->collected_total }}</td>
            <td>{{ (float) $row->balance_total }}</td>
            <td>{{ $row->currency }}</td>
        </tr>
    @endforeach
</table>

<br>

<table border="1">
    <tr><th colspan="8">Cobros</th></tr>
    <tr>
        <th>Cobro</th>
        <th>Fecha</th>
        <th>Cliente</th>
        <th>CxC</th>
        <th>Estado</th>
        <th>Póliza</th>
        <th>Referencia</th>
        <th>Importe</th>
        <th>Moneda</th>
    </tr>
    @foreach($payments as $row)
        <tr>
            <td>#{{ $row->id }}</td>
            <td>{{ $row->payment_date }}</td>
            <td>{{ $row->customer_name }}</td>
            <td>{{ $row->receivable_number }}</td>
            <td>{{ \App\Filament\Resources\AccountReceivablePaymentResource::statusLabel($row->status) }}</td>
            <td>{{ $row->accounting_entry_number ?: $row->accounting_entry_id }}</td>
            <td>{{ $row->reference }}</td>
            <td>{{ (float) $row->amount }}</td>
            <td>{{ $row->currency }}</td>
        </tr>
    @endforeach
</table>
