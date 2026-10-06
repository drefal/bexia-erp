<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">

<style>
body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 7.5px;
    color: #222;
}

.header {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
}

.header td {
    vertical-align: middle;
}

.logo {
    max-width: 150px;
    max-height: 65px;
}

.title {
    text-align: right;
}

h1 {
    margin: 0;
    font-size: 17px;
}

.summary,
.data {
    width: 100%;
    border-collapse: collapse;
}

.summary {
    margin-bottom: 10px;
}

.summary td,
.data th,
.data td {
    border: 1px solid #ddd;
    padding: 3px;
}

.data th {
    background: #f2f2f2;
}

.right {
    text-align: right;
}
</style>
</head>

<body>

<table class="header">
<tr>
<td>
@if (! empty($logoDataUri))
    <img src="{{ $logoDataUri }}" class="logo">
@endif
</td>

<td class="title">
<h1>Comprobaciones de gastos</h1>
<strong>{{ $companyName }}</strong><br>
Periodo: {{ $dateFrom }} al {{ $dateTo }}<br>
Generado: {{ $generatedAt->format('d/m/Y H:i') }}
</td>
</tr>
</table>

<table class="summary">
<tr>
<td><strong>Gastos:</strong> {{ $summary['lines'] }}</td>
<td><strong>Subtotal:</strong> ${{ number_format($summary['subtotal'],2) }}</td>
<td><strong>IVA:</strong> ${{ number_format($summary['tax'],2) }}</td>
<td><strong>Total:</strong> ${{ number_format($summary['total'],2) }}</td>
<td><strong>Con comprobante:</strong> {{ $summary['with_receipt'] }}</td>
</tr>
</table>

<table class="data">
<thead>
<tr>
<th>Fecha</th>
<th>Comprobación</th>
<th>Tipo</th>
<th>Estado</th>
<th>Empleado</th>
<th>Categoría</th>
<th>Proveedor</th>
<th>Descripción</th>
<th class="right">Subtotal</th>
<th class="right">IVA</th>
<th class="right">Total</th>
<th>UUID</th>
</tr>
</thead>

<tbody>
@foreach ($rows as $row)
<tr>
<td>{{ $row->expense_date }}</td>
<td>{{ $row->report_number }}</td>
<td>{{ $row->type_label }}</td>
<td>{{ $row->status_label }}</td>
<td>{{ $row->spent_by_name }}</td>
<td>{{ $row->category_name ?: 'Sin categoría' }}</td>
<td>{{ $row->supplier_name ?: '—' }}</td>
<td>{{ $row->description }}</td>

<td class="right">
${{ number_format((float)$row->subtotal,2) }}
</td>

<td class="right">
${{ number_format((float)$row->tax_amount,2) }}
</td>

<td class="right">
${{ number_format((float)$row->total_amount,2) }}
</td>

<td>{{ $row->cfdi_uuid ?: '—' }}</td>
</tr>
@endforeach
</tbody>
</table>

</body>
</html>
