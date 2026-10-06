<!DOCTYPE html>
<html lang="es">

<head>
<meta charset="utf-8">

<style>

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 8px;
    color: #222;
}

.header,
.summary,
.data {
    width: 100%;
    border-collapse: collapse;
}

.header {
    margin-bottom: 10px;
}

.logo {
    max-width: 150px;
    max-height: 65px;
}

.title {
    text-align: right;
}

.summary {
    margin-bottom: 10px;
}

.summary td,
.data th,
.data td {
    border: 1px solid #ddd;
    padding: 4px;
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
<img
src="{{ $logoDataUri }}"
class="logo"
>
@endif

</td>

<td class="title">

<h2>
Caja chica a una fecha
</h2>

<strong>
{{ $companyName }}
</strong>

<br>

Fecha de corte:
{{ $asOfDate }}

<br>

@if ($fundId)

Caja:
{{ $fundOptions[$fundId] ?? $fundId }}

<br>

@endif

Generado:
{{ $generatedAt->format('d/m/Y H:i') }}

</td>

</tr>

</table>


<table class="summary">

<tr>

<td>
Cajas:
{{ $summary['funds'] }}
</td>

<td>
Autorizado:
${{ number_format($summary['authorized'], 2) }}
</td>

<td>
Saldo:
${{ number_format($summary['balance'], 2) }}
</td>

<td>
Gastos:
${{ number_format($summary['expenses'], 2) }}
</td>

<td>
Reposiciones:
${{ number_format($summary['replenishments'], 2) }}
</td>

</tr>

</table>


<table class="data">

<thead>

<tr>

<th>Folio</th>
<th>Caja</th>
<th>Responsable</th>
<th class="right">Autorizado</th>
<th class="right">Saldo fecha</th>
<th class="right">Fondeo</th>
<th class="right">Reposición</th>
<th class="right">Gasto</th>
<th class="right">Devolución</th>
<th>Último mov.</th>
<th>Tipo</th>
<th>Estado</th>

</tr>

</thead>

<tbody>

@foreach ($rows as $row)

<tr>

<td>{{ $row->number }}</td>

<td>{{ $row->name }}</td>

<td>
{{ $row->employee_name ?: '—' }}
</td>

<td class="right">
${{ number_format((float) $row->authorized_amount, 2) }}
</td>

<td class="right">
${{ number_format($row->balance_as_of, 2) }}
</td>

<td class="right">
${{ number_format($row->initial_funding_total, 2) }}
</td>

<td class="right">
${{ number_format($row->replenishment_total, 2) }}
</td>

<td class="right">
${{ number_format($row->expense_total, 2) }}
</td>

<td class="right">
${{ number_format($row->return_total, 2) }}
</td>

<td>
{{ $row->last_movement_date ?: '—' }}
</td>

<td>
{{ \App\Support\Expenses\PettyCashAsOfReportService::typeLabel($row->last_movement_type) }}
</td>

<td>
{{ $row->status_as_of }}
</td>

</tr>

@endforeach

</tbody>

</table>

</body>

</html>
