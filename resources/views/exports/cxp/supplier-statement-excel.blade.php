<html>
<head><meta charset="utf-8"></head>
<body>
<table border="1">
    <tr><th colspan="8">Estado de cuenta proveedor CxP</th></tr>
    <tr><td colspan="8">{{ $company->name ?? 'Empresa' }} | Proveedor: {{ $supplierLabel }} | Periodo: {{ $dateFrom }} a {{ $dateTo }}</td></tr>
    <tr><th>Documentos</th><th>Pagado documentos</th><th>Saldo pendiente</th><th>Pagos aplicados periodo</th><th colspan="4"></th></tr>
    <tr><td>{{ $totals['documents_total'] }}</td><td>{{ $totals['paid_total'] }}</td><td>{{ $totals['balance_total'] }}</td><td>{{ $totals['payments_total'] }}</td><td colspan="4"></td></tr>

    <tr><th colspan="8">Documentos CxP</th></tr>
    <tr><th>Folio</th><th>Proveedor</th><th>Fecha</th><th>Vence</th><th>Estado</th><th>Total</th><th>Pagado</th><th>Saldo</th></tr>
    @foreach($payables as $row)
        <tr>
            <td>{{ $row->number }}</td>
            <td>{{ $row->supplier_name }}</td>
            <td>{{ $row->issue_date }}</td>
            <td>{{ $row->due_date }}</td>
            <td>{{ $row->status }}</td>
            <td>{{ $row->total }}</td>
            <td>{{ $row->paid_total }}</td>
            <td>{{ $row->balance_total }}</td>
        </tr>
    @endforeach

    <tr><th colspan="8">Pagos</th></tr>
    <tr><th>Pago</th><th>Fecha</th><th>Proveedor</th><th>CxP</th><th>Estado</th><th>Póliza</th><th>Referencia</th><th>Importe</th></tr>
    @foreach($payments as $row)
        <tr>
            <td>#{{ $row->id }}</td>
            <td>{{ $row->payment_date }}</td>
            <td>{{ $row->supplier_name }}</td>
            <td>{{ $row->payable_number }}</td>
            <td>{{ $row->status }}</td>
            <td>{{ $row->entry_number ?: 'Sin póliza' }}</td>
            <td>{{ $row->reference ?: '-' }}</td>
            <td>{{ $row->amount }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
