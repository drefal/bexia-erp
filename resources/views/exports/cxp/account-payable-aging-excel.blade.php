<html>
<head><meta charset="utf-8"></head>
<body>
<table border="1">
    <tr><th colspan="8">Antigüedad de saldos CxP</th></tr>
    <tr><td colspan="8">{{ $company->name ?? 'Empresa' }} | Corte: {{ $asOfDate }} | Proveedor: {{ $supplierLabel }}</td></tr>
    <tr>
        <th>Total</th><th>Por vencer</th><th>1 a 30</th><th>31 a 60</th><th>61 a 90</th><th>Más de 90</th><th colspan="2">Búsqueda</th>
    </tr>
    <tr>
        <td>{{ $summary['total'] }}</td><td>{{ $summary['not_due'] }}</td><td>{{ $summary['days_1_30'] }}</td><td>{{ $summary['days_31_60'] }}</td><td>{{ $summary['days_61_90'] }}</td><td>{{ $summary['days_90_plus'] }}</td><td colspan="2">{{ $documentSearch }}</td>
    </tr>
    <tr>
        <th>Folio</th><th>Proveedor</th><th>Vence</th><th>Días vencido</th><th>Rango</th><th>Total</th><th>Pagado</th><th>Saldo</th>
    </tr>
    @foreach($rows as $row)
        <tr>
            <td>{{ $row->number }}</td>
            <td>{{ $row->supplier_name }}</td>
            <td>{{ $row->due_date }}</td>
            <td>{{ $row->days_overdue }}</td>
            <td>{{ $row->bucket_label }}</td>
            <td>{{ $row->total }}</td>
            <td>{{ $row->paid_total }}</td>
            <td>{{ $row->balance_total }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
