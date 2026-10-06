<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:8px;color:#222}
.header,.data,.summary{width:100%;border-collapse:collapse}
.header{margin-bottom:10px}
.logo{max-width:150px;max-height:65px}
.title{text-align:right}
.summary{margin-bottom:10px}
.summary td,.data th,.data td{border:1px solid #ddd;padding:4px}
.data th{background:#f2f2f2}
.right{text-align:right}
</style>
</head>
<body>

<table class="header">
<tr>
<td>
@if(!empty($logoDataUri))
<img src="{{ $logoDataUri }}" class="logo">
@endif
</td>
<td class="title">
<h2>Gastos por empleado</h2>
<strong>{{ $companyName }}</strong><br>
Periodo {{ $dateFrom }} al {{ $dateTo }}<br>
{{ $generatedAt->format('d/m/Y H:i') }}
</td>
</tr>
</table>

<table class="summary">
<tr>
<td>Empleados: {{ $summary['employees'] }}</td>
<td>Gastos: {{ $summary['expenses'] }}</td>
<td>Subtotal: ${{ number_format($summary['subtotal'],2) }}</td>
<td>IVA: ${{ number_format($summary['tax'],2) }}</td>
<td>Total: ${{ number_format($summary['total'],2) }}</td>
</tr>
</table>

<table class="data">
<thead>
<tr>
<th>Empleado</th>
<th>Gastos</th>
<th>Subtotal</th>
<th>IVA</th>
<th>Total</th>
<th>Promedio</th>
<th>Caja chica</th>
<th>Reembolsos</th>
</tr>
</thead>
<tbody>
@foreach($rows as $row)
<tr>
<td>{{ $row->employee_name }}</td>
<td class="right">{{ $row->expense_count }}</td>
<td class="right">${{ number_format($row->subtotal,2) }}</td>
<td class="right">${{ number_format($row->tax,2) }}</td>
<td class="right">${{ number_format($row->total,2) }}</td>
<td class="right">${{ number_format($row->average,2) }}</td>
<td class="right">${{ number_format($row->petty_cash_total,2) }}</td>
<td class="right">${{ number_format($row->reimbursement_total,2) }}</td>
</tr>
@endforeach
</tbody>
</table>

</body>
</html>
