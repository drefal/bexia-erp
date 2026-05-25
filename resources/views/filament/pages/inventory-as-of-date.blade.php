<x-filament-panels::page>
    @php
        $summary = $this->summary;
        $rows = $this->rows;
        $productOptions = $this->productOptions();
        $variantOptions = $this->variantOptions();
        $lotOptions = $this->lotOptions();
    @endphp

    <div class="space-y-6">

        <div class="rounded-2xl border border-gray-200 bg-white px-6 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-sm font-bold text-gray-950 dark:text-white">Exportar inventario a fecha</div>
                    <div class="text-xs text-gray-500">
                        Los archivos se generan con la fecha/hora aplicada.
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ route('inventory.as-of-date.print', $this->exportParams()) }}"
                        target="_blank"
                        class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm"
                        style="background:#2563eb;"
                    >
                        Imprimir PDF
                    </a>

                    <a
                        href="{{ route('inventory.as-of-date.excel', $this->exportParams()) }}"
                        class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm"
                        style="background:#16a34a;"
                    >
                        Descargar Excel
                    </a>
                </div>
            </div>
        </div>

        <div class="overflow-visible rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900" style="position:relative; z-index:50;">
            <div class="border-b border-gray-100 bg-gray-50 px-6 py-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Inventario</div>
                <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Inventario a fecha</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Selecciona una fecha/hora y presiona Calcular inventario.
                </p>
            </div>

            <div class="p-6 space-y-5">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap:1.25rem; align-items:end;">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Empresa</label>
                        <select wire:model.live="company_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <option value="">Todas</option>
                            @foreach ($this->companyOptions() as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Almacén</label>
                        <select wire:model.live="warehouse_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <option value="">Todos</option>
                            @foreach ($this->warehouseOptions() as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ubicación</label>
                        <select wire:model.live="location_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <option value="">Todas</option>
                            @foreach ($this->locationOptions() as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha de corte</label>
                        <input type="date" wire:model="cutoff_date" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Hora de corte</label>
                        <input type="time" wire:model="cutoff_time" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Límite</label>
                        <input type="number" min="50" max="5000" wire:model.live="limit" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 2fr 1.2fr 1fr; gap:1.25rem; align-items:start;">
                    <div style="position: relative;">
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Producto</label>
                        <div class="mt-1 flex gap-2">
                            <input
                                type="text"
                                wire:model.live.debounce.400ms="product_search"
                                placeholder="Buscar producto por nombre, SKU o referencia..."
                                class="w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800"
                            >

                            @if ($this->selectedProductLabel())
                                <button type="button" wire:click="clearProduct" class="whitespace-nowrap rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-semibold shadow-sm hover:bg-gray-50">
                                    Quitar
                                </button>
                            @endif
                        </div>

                        @if (! $this->selectedProductLabel() && trim($product_search ?? '') !== '' && count($productOptions) > 0)
                            <div class="absolute mt-2 w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900" style="z-index:9999;">
                                @foreach ($productOptions as $id => $label)
                                    <button type="button" wire:click="selectProduct({{ $id }})" class="block w-full border-b border-gray-100 px-4 py-2 text-left text-sm hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800">
                                        {{ $label }} <span class="text-xs text-gray-400">#{{ $id }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Variante</label>
                        <select wire:model.live="product_variant_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800" @disabled(! $product_id)>
                            <option value="">Todas</option>
                            @foreach ($variantOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Lote</label>
                        <select wire:model.live="lot_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <option value="">Todos</option>
                            @foreach ($lotOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap:1.25rem; align-items:end;">
                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-800">
                        <input type="checkbox" wire:model.live="show_zero" class="rounded border-gray-300">
                        Mostrar existencias en cero
                    </label>

                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-800">
                        <input type="checkbox" wire:model.live="only_negative" class="rounded border-gray-300">
                        Solo negativos
                    </label>

                    <button type="button" wire:click="recalculate" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm" style="background:#16a34a;">
                        Calcular inventario
                    </button>

                    <button type="button" wire:click="resetFilters" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm" style="background:#2563eb;">
                        Limpiar filtros
                    </button>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    El reporte se basa en movimientos registrados y existencias disponibles al corte indicado. Los números de serie se manejarán en una sección separada.
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap:1rem;">
            <div class="rounded-2xl border bg-white px-5 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Corte</div>
                <div class="mt-2 text-base font-bold">{{ $summary['cutoff_at'] ?? '—' }}</div>
                <div class="mt-1 text-xs text-gray-500">Recalculado: {{ $last_calculated_at ?: '—' }}</div>
            </div>
            <div class="rounded-2xl border bg-white px-5 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Líneas</div>
                <div class="mt-2 text-2xl font-bold">{{ number_format($summary['lines'] ?? 0) }}</div>
            </div>
            <div class="rounded-2xl border bg-white px-5 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Cantidad total</div>
                <div class="mt-2 text-2xl font-bold">{{ number_format($summary['total_quantity'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded-2xl border bg-white px-5 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Positivos / negativos</div>
                <div class="mt-2 text-2xl font-bold">{{ number_format($summary['positive_lines'] ?? 0) }} / {{ number_format($summary['negative_lines'] ?? 0) }}</div>
            </div>
            <div class="rounded-2xl border bg-white px-5 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Con lote</div>
                <div class="mt-2 text-2xl font-bold">{{ number_format($summary['with_lot'] ?? 0) }}</div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <div style="display:grid; grid-template-columns:1fr auto; align-items:center; gap:1rem;">
                    <h3 class="text-base font-bold">Existencias al corte</h3>
                    <div class="text-sm font-medium text-gray-500" style="text-align:right;">{{ $rows->count() }} líneas</div>
                </div>
            </div>

            <div class="overflow-x-auto px-4 pb-4" wire:key="inventory-as-of-table-{{ $refresh_token }}">
                <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left">Empresa</th>
                            <th class="px-4 py-3 text-left">Almacén</th>
                            <th class="px-4 py-3 text-left">Ubicación</th>
                            <th class="px-4 py-3 text-left">Producto</th>
                            <th class="px-4 py-3 text-left">Variante</th>
                            <th class="px-4 py-3 text-left">Lote</th>
                            <th class="px-4 py-3 text-right">Existencia al corte</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                <td class="px-4 py-3">{{ $row->company_name ?: ('Empresa #' . $row->company_id) }}</td>
                                <td class="px-4 py-3">{{ $row->warehouse_name ?: ('Almacén #' . $row->warehouse_id) }}</td>
                                <td class="px-4 py-3">{{ $row->location_name ?: ('Ubicación #' . $row->location_id) }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold">{{ $row->product_name ?: ('Producto #' . $row->product_id) }}</div>
                                    <div class="text-xs text-gray-500">#{{ $row->product_id }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div>{{ $row->variant_name ?: 'Sin variante' }}</div>
                                    @if ($row->variant_id)
                                        <div class="text-xs text-gray-500">#{{ $row->variant_id }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $row->lot_label }}</td>
                                <td class="px-4 py-3 text-right font-bold {{ $row->quantity_as_of < 0 ? 'text-red-600' : '' }}">
                                    {{ number_format((float) $row->quantity_as_of, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                                    No hay existencias para los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
