<x-filament-panels::page>

<div class="space-y-6">

<x-filament::section>

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">

<div>
<label class="mb-1 block text-sm font-medium">
Fecha de corte
</label>

<input
    type="date"
    wire:model="asOfDate"
    class="w-full rounded-lg border-gray-300"
>
</div>

<div>
<label class="mb-1 block text-sm font-medium">
Caja chica
</label>

<select
    wire:model="fundId"
    class="w-full rounded-lg border-gray-300"
>
<option value="">
Todas
</option>

@foreach ($fundOptions as $id => $label)
<option value="{{ $id }}">
{{ $label }}
</option>
@endforeach

</select>
</div>

</div>

<div class="mt-4 flex gap-3">

<x-filament::button
    wire:click="applyFilters"
    icon="heroicon-o-funnel"
>
Aplicar
</x-filament::button>

<x-filament::button
    wire:click="clearFilters"
    color="gray"
>
Limpiar
</x-filament::button>

</div>

</x-filament::section>


<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">

@foreach ([
    ['Cajas', $summary['funds'] ?? 0, false],
    ['Monto autorizado', $summary['authorized'] ?? 0, true],
    ['Saldo a la fecha', $summary['balance'] ?? 0, true],
    ['Gastos acumulados', $summary['expenses'] ?? 0, true],
] as [$label, $value, $money])

<x-filament::section>

<div class="text-sm text-gray-500">
{{ $label }}
</div>

<div class="mt-1 text-2xl font-semibold">

@if ($money)
${{ number_format($value, 2) }}
@else
{{ number_format($value) }}
@endif

</div>

</x-filament::section>

@endforeach

</div>


<div class="grid grid-cols-1 gap-4 md:grid-cols-3">

<x-filament::section>
<div class="text-sm text-gray-500">
Fondeo inicial acumulado
</div>
<div class="mt-1 text-xl font-semibold">
${{ number_format($summary['initial_funding'] ?? 0, 2) }}
</div>
</x-filament::section>

<x-filament::section>
<div class="text-sm text-gray-500">
Reposiciones acumuladas
</div>
<div class="mt-1 text-xl font-semibold">
${{ number_format($summary['replenishments'] ?? 0, 2) }}
</div>
</x-filament::section>

<x-filament::section>
<div class="text-sm text-gray-500">
Devoluciones acumuladas
</div>
<div class="mt-1 text-xl font-semibold">
${{ number_format($summary['returns'] ?? 0, 2) }}
</div>
</x-filament::section>

</div>


<x-filament::section>

<div class="mb-4 text-sm text-gray-500">
Saldo reconstruido al
<strong>{{ $asOfDate }}</strong>
con base en los movimientos registrados hasta esa fecha.
</div>

<div class="overflow-x-auto">

<table class="w-full min-w-[1400px] text-sm">

<thead>

<tr class="border-b border-gray-200 text-left">

<th class="p-3">Folio</th>
<th class="p-3">Caja chica</th>
<th class="p-3">Responsable</th>
<th class="p-3 text-right">Autorizado</th>
<th class="p-3 text-right">Saldo a fecha</th>
<th class="p-3 text-right">Fondeo</th>
<th class="p-3 text-right">Reposiciones</th>
<th class="p-3 text-right">Gastos</th>
<th class="p-3 text-right">Devoluciones</th>
<th class="p-3 text-right">Movimientos</th>
<th class="p-3">Último movimiento</th>
<th class="p-3">Tipo</th>
<th class="p-3">Referencia</th>
<th class="p-3">Estado a fecha</th>

</tr>

</thead>

<tbody>

@forelse ($rows as $row)

<tr class="border-b border-gray-100">

<td class="p-3 font-medium">

<a
href="{{ \App\Filament\Resources\PettyCashFundResource::getUrl('view', ['record' => $row->id]) }}"
class="text-primary-600 hover:underline"
>
{{ $row->number }}
</a>

</td>

<td class="p-3">
{{ $row->name }}
</td>

<td class="p-3">
{{ $row->employee_name ?: '—' }}
</td>

<td class="p-3 text-right">
${{ number_format((float) $row->authorized_amount, 2) }}
</td>

<td class="p-3 text-right font-semibold">
${{ number_format((float) $row->balance_as_of, 2) }}
</td>

<td class="p-3 text-right">
${{ number_format($row->initial_funding_total, 2) }}
</td>

<td class="p-3 text-right">
${{ number_format($row->replenishment_total, 2) }}
</td>

<td class="p-3 text-right">
${{ number_format($row->expense_total, 2) }}
</td>

<td class="p-3 text-right">
${{ number_format($row->return_total, 2) }}
</td>

<td class="p-3 text-right">
{{ number_format($row->movement_count) }}
</td>

<td class="p-3">
{{ $row->last_movement_date ?: '—' }}
</td>

<td class="p-3">
{{ \App\Support\Expenses\PettyCashAsOfReportService::typeLabel($row->last_movement_type) }}
</td>

<td class="p-3">
{{ $row->last_movement_reference ?: '—' }}
</td>

<td class="p-3">
{{ $row->status_as_of }}
</td>

</tr>

@empty

<tr>

<td
colspan="14"
class="p-8 text-center text-gray-500"
>
No existían cajas chicas para la fecha seleccionada.
</td>

</tr>

@endforelse

</tbody>

</table>

</div>

</x-filament::section>

</div>

</x-filament-panels::page>
