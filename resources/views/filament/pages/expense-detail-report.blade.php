<x-filament-panels::page>

<div class="space-y-6">

<x-filament::section>

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">

<div>
<label class="mb-1 block text-sm font-medium">
Desde
</label>

<input
    type="date"
    wire:model="dateFrom"
    class="w-full rounded-lg border-gray-300"
>
</div>

<div>
<label class="mb-1 block text-sm font-medium">
Hasta
</label>

<input
    type="date"
    wire:model="dateTo"
    class="w-full rounded-lg border-gray-300"
>
</div>

<div>
<label class="mb-1 block text-sm font-medium">
Tipo
</label>

<select
    wire:model="reportType"
    class="w-full rounded-lg border-gray-300"
>
<option value="">Todos</option>

@foreach ($typeOptions as $value => $label)
<option value="{{ $value }}">
{{ $label }}
</option>
@endforeach
</select>
</div>

<div>
<label class="mb-1 block text-sm font-medium">
Estado
</label>

<select
    wire:model="reportStatus"
    class="w-full rounded-lg border-gray-300"
>
<option value="">Todos</option>

@foreach ($statusOptions as $value => $label)
<option value="{{ $value }}">
{{ $label }}
</option>
@endforeach
</select>
</div>

<div>
<label class="mb-1 block text-sm font-medium">
Empleado
</label>

<select
    wire:model="employeeId"
    class="w-full rounded-lg border-gray-300"
>
<option value="">Todos</option>

@foreach ($employeeOptions as $id => $label)
<option value="{{ $id }}">
{{ $label }}
</option>
@endforeach
</select>
</div>

<div>
<label class="mb-1 block text-sm font-medium">
Categoría
</label>

<select
    wire:model="categoryId"
    class="w-full rounded-lg border-gray-300"
>
<option value="">Todas</option>

@foreach ($categoryOptions as $id => $label)
<option value="{{ $id }}">
{{ $label }}
</option>
@endforeach
</select>
</div>

<div>
<label class="mb-1 block text-sm font-medium">
Proyecto
</label>

<select
    wire:model="projectId"
    class="w-full rounded-lg border-gray-300"
>
<option value="">Todos</option>

@foreach ($projectOptions as $id => $label)
<option value="{{ $id }}">
{{ $label }}
</option>
@endforeach
</select>
</div>

<div class="xl:col-span-2">
<label class="mb-1 block text-sm font-medium">
Proveedor
</label>

<input
    type="text"
    wire:model="supplier"
    placeholder="Buscar proveedor..."
    class="w-full rounded-lg border-gray-300"
>
</div>

</div>

<div class="mt-4 flex flex-wrap gap-3">

<x-filament::button
    wire:click="applyFilters"
    icon="heroicon-o-funnel"
>
Aplicar filtros
</x-filament::button>

<x-filament::button
    wire:click="clearFilters"
    color="gray"
    icon="heroicon-o-x-mark"
>
Limpiar
</x-filament::button>

</div>

<div class="mt-6 border-t border-gray-200 pt-5">

<div class="mb-2 flex items-center justify-between gap-4">

<div>
<div class="text-sm font-semibold">
Gastos seleccionados
</div>

<div class="text-xs text-gray-500">
Si no seleccionas ninguno, se usan todos los gastos que cumplen los filtros.
</div>
</div>

<div class="text-sm text-gray-500">
{{ count($selectedLineIds) }} seleccionados
</div>

</div>

<select
    multiple
    wire:model="selectedLineIds"
    size="6"
    class="w-full rounded-lg border-gray-300"
>
@foreach ($selectableExpenseOptions as $id => $label)
<option value="{{ $id }}">
{{ $label }}
</option>
@endforeach
</select>

<div class="mt-3 flex flex-wrap gap-3">

<x-filament::button
    wire:click="applySelection"
    icon="heroicon-o-check"
>
Aplicar selección
</x-filament::button>

<x-filament::button
    wire:click="selectAllVisible"
    color="gray"
>
Seleccionar todos los visibles
</x-filament::button>

<x-filament::button
    wire:click="clearSelection"
    color="gray"
>
Limpiar selección
</x-filament::button>

</div>

</div>

</x-filament::section>


<x-filament::section>

<div class="mb-3 text-sm font-semibold">
Vista del reporte
</div>

<div class="flex flex-wrap gap-3">

<x-filament::button
    wire:click="setViewMode('detail')"
    :color="$viewMode === 'detail' ? 'primary' : 'gray'"
    icon="heroicon-o-list-bullet"
>
Detalle
</x-filament::button>

<x-filament::button
    wire:click="setViewMode('employee')"
    :color="$viewMode === 'employee' ? 'primary' : 'gray'"
    icon="heroicon-o-users"
>
Por empleado
</x-filament::button>

<x-filament::button
    wire:click="setViewMode('category')"
    :color="$viewMode === 'category' ? 'primary' : 'gray'"
    icon="heroicon-o-tag"
>
Por categoría
</x-filament::button>

</div>

</x-filament::section>


@if ($viewMode === 'detail')

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">

@foreach ([
    ['Gastos', $summary['lines'] ?? 0, false],
    ['Subtotal', $summary['subtotal'] ?? 0, true],
    ['IVA', $summary['tax'] ?? 0, true],
    ['Total', $summary['total'] ?? 0, true],
    ['Con comprobante', $summary['with_receipt'] ?? 0, false],
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


<x-filament::section>

<div class="overflow-x-auto">

<table class="w-full min-w-[1950px] text-sm">

<thead>
<tr class="border-b border-gray-200 text-left">

<th class="p-3">Fecha</th>
<th class="p-3">Comprobación</th>
<th class="p-3">Tipo</th>
<th class="p-3">Estado</th>
<th class="p-3">Empleado</th>
<th class="p-3">Categoría</th>
<th class="p-3">Proyecto</th>
<th class="p-3">Proveedor</th>
<th class="p-3">RFC</th>
<th class="p-3">Descripción</th>
<th class="p-3 text-right">Subtotal</th>
<th class="p-3 text-right">IVA</th>
<th class="p-3 text-right">Total</th>
<th class="p-3">UUID CFDI</th>
<th class="p-3">Comprobante</th>

</tr>
</thead>

<tbody>

@forelse ($rows as $row)

<tr class="border-b border-gray-100">

<td class="p-3">
{{ $row->expense_date }}
</td>

<td class="p-3">
<a
href="{{ \App\Filament\Resources\ExpenseReportResource::getUrl('view', ['record' => $row->expense_report_id]) }}"
class="font-medium text-primary-600 hover:underline"
>
{{ $row->report_number }}
</a>
</td>

<td class="p-3">
{{ $row->type_label }}
</td>

<td class="p-3">
{{ $row->status_label }}
</td>

<td class="p-3">
{{ $row->spent_by_name ?: '—' }}
</td>

<td class="p-3">
{{ $row->category_name ?: 'Sin categoría' }}
</td>

<td class="p-3">
@if ($row->project_name)
    {{ $row->project_code ? $row->project_code . ' · ' : '' }}
    {{ $row->project_name }}
@else
    —
@endif
</td>

<td class="p-3">
{{ $row->supplier_name ?: '—' }}
</td>

<td class="p-3">
{{ $row->supplier_rfc ?: '—' }}
</td>

<td class="p-3">
{{ $row->description }}
</td>

<td class="p-3 text-right">
${{ number_format((float) $row->subtotal, 2) }}
</td>

<td class="p-3 text-right">
${{ number_format((float) $row->tax_amount, 2) }}
</td>

<td class="p-3 text-right font-semibold">
${{ number_format((float) $row->total_amount, 2) }}
</td>

<td class="p-3 font-mono text-xs">
{{ $row->cfdi_uuid ?: '—' }}
</td>

<td class="p-3">
{{ $row->has_receipt ? 'Sí' : 'No' }}
</td>

</tr>

@empty

<tr>
<td colspan="15" class="p-8 text-center text-gray-500">
No hay gastos para los filtros seleccionados.
</td>
</tr>

@endforelse

</tbody>
</table>

</div>

</x-filament::section>


@elseif ($viewMode === 'employee')

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">

@foreach ([
    ['Empleados', $summary['employees'] ?? 0, false],
    ['Gastos', $summary['expenses'] ?? 0, false],
    ['Subtotal', $summary['subtotal'] ?? 0, true],
    ['IVA', $summary['tax'] ?? 0, true],
    ['Total', $summary['total'] ?? 0, true],
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

<x-filament::section>

<div class="overflow-x-auto">

<table class="w-full min-w-[1200px] text-sm">

<thead>
<tr class="border-b border-gray-200 text-left">

<th class="p-3">Empleado</th>
<th class="p-3 text-right">Gastos</th>
<th class="p-3 text-right">Subtotal</th>
<th class="p-3 text-right">IVA</th>
<th class="p-3 text-right">Total</th>
<th class="p-3 text-right">Promedio</th>
<th class="p-3 text-right">Caja chica</th>
<th class="p-3 text-right">Reembolsos</th>
<th class="p-3 text-right">Con comprobante</th>

</tr>
</thead>

<tbody>

@forelse ($employeeRows as $row)

<tr class="border-b border-gray-100">

<td class="p-3 font-medium">
{{ $row->employee_name }}
</td>

<td class="p-3 text-right">
{{ $row->expense_count }}
</td>

<td class="p-3 text-right">
${{ number_format($row->subtotal, 2) }}
</td>

<td class="p-3 text-right">
${{ number_format($row->tax, 2) }}
</td>

<td class="p-3 text-right font-semibold">
${{ number_format($row->total, 2) }}
</td>

<td class="p-3 text-right">
${{ number_format($row->average, 2) }}
</td>

<td class="p-3 text-right">
${{ number_format($row->petty_cash_total, 2) }}
</td>

<td class="p-3 text-right">
${{ number_format($row->reimbursement_total, 2) }}
</td>

<td class="p-3 text-right">
{{ $row->with_receipt }}
</td>

</tr>

@empty

<tr>
<td colspan="9" class="p-8 text-center text-gray-500">
No hay datos para los filtros seleccionados.
</td>
</tr>

@endforelse

</tbody>
</table>

</div>

</x-filament::section>


@else

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">

@foreach ([
    ['Categorías', $summary['categories'] ?? 0, false],
    ['Gastos', $summary['expenses'] ?? 0, false],
    ['Subtotal', $summary['subtotal'] ?? 0, true],
    ['IVA', $summary['tax'] ?? 0, true],
    ['Total', $summary['total'] ?? 0, true],
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

<x-filament::section>

<div class="overflow-x-auto">

<table class="w-full min-w-[1000px] text-sm">

<thead>
<tr class="border-b border-gray-200 text-left">

<th class="p-3">Categoría</th>
<th class="p-3 text-right">Gastos</th>
<th class="p-3 text-right">Empleados</th>
<th class="p-3 text-right">Subtotal</th>
<th class="p-3 text-right">IVA</th>
<th class="p-3 text-right">Total</th>
<th class="p-3 text-right">Participación</th>
<th class="p-3 text-right">Con comprobante</th>

</tr>
</thead>

<tbody>

@forelse ($categoryRows as $row)

<tr class="border-b border-gray-100">

<td class="p-3 font-medium">
{{ $row->category_name }}
</td>

<td class="p-3 text-right">
{{ $row->expense_count }}
</td>

<td class="p-3 text-right">
{{ $row->employees }}
</td>

<td class="p-3 text-right">
${{ number_format($row->subtotal, 2) }}
</td>

<td class="p-3 text-right">
${{ number_format($row->tax, 2) }}
</td>

<td class="p-3 text-right font-semibold">
${{ number_format($row->total, 2) }}
</td>

<td class="p-3 text-right">
{{ number_format($row->percentage, 2) }}%
</td>

<td class="p-3 text-right">
{{ $row->with_receipt }}
</td>

</tr>

@empty

<tr>
<td colspan="8" class="p-8 text-center text-gray-500">
No hay datos para los filtros seleccionados.
</td>
</tr>

@endforelse

</tbody>
</table>

</div>

</x-filament::section>

@endif

</div>

</x-filament-panels::page>
