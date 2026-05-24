<x-filament-panels::page>
    @php
        $summary = $this->summary;
        $rows = $this->rows;
        $productOptions = $this->productOptions();
        $variantOptions = $this->variantOptions();
        $lotOptions = $this->lotOptions();
        $serialOptions = $this->serialOptions();
    @endphp

    <div class="space-y-6">
        <div class="overflow-visible rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900" style="position:relative; z-index:50;">
            <div class="border-b border-gray-100 bg-gray-50 px-6 py-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Inventario</div>
                        <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Valorización de inventario</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Valor actual estimado a partir de existencias físicas en almacén y costos disponibles.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a
                            href="{{ route('inventory.valuation.print', $this->exportParams()) }}"
                            target="_blank"
                            class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm"
                            style="background:#2563eb;"
                        >
                            Imprimir PDF
                        </a>

                        <a
                            href="{{ route('inventory.valuation.excel', $this->exportParams()) }}"
                            class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm"
                            style="background:#16a34a;"
                        >
                            Descargar Excel
                        </a>
                    </div>
                </div>
            </div>

            <div class="p-6 space-y-5">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; align-items:end;">
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
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Límite</label>
                        <input type="number" min="50" max="5000" wire:model.live="limit" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    </div>

                    <div class="flex items-center gap-2 pb-2">
                        <input type="checkbox" wire:model.live="only_positive" class="rounded border-gray-300">
                        <span class="text-sm text-gray-700 dark:text-gray-200">Solo existencias positivas</span>
                    </div>

                    <div>
                        <button type="button" wire:click="resetFilters" class="w-full rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm" style="background:#2563eb;">
                            Limpiar filtros
                        </button>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 2fr 1.5fr 1fr 1fr; gap:1.25rem; align-items:start;">
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

                    <div style="position: relative;">
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Variante</label>
                        <div class="mt-1 flex gap-2">
                            <input
                                type="text"
                                wire:model.live.debounce.400ms="variant_search"
                                placeholder="Buscar variante..."
                                class="w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800"
                                @disabled(! $product_id)
                            >
                            @if ($this->selectedVariantLabel())
                                <button type="button" wire:click="clearVariant" class="whitespace-nowrap rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-semibold shadow-sm hover:bg-gray-50">
                                    Quitar
                                </button>
                            @endif
                        </div>

                        @if ($product_id && ! $this->selectedVariantLabel() && count($variantOptions) > 0)
                            <div class="absolute mt-2 w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900" style="z-index:9999;">
                                @foreach ($variantOptions as $id => $label)
                                    <button type="button" wire:click="selectVariant({{ $id }})" class="block w-full border-b border-gray-100 px-4 py-2 text-left text-sm hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800">
                                        {{ $label }} <span class="text-xs text-gray-400">#{{ $id }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
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

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Número de serie</label>
                        <select wire:model.live="stock_serial_number_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800" @disabled(! $this->supportsSerialQuantFilter())>
                            <option value="">Todas</option>
                            @foreach ($serialOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @if (! $this->supportsSerialQuantFilter())
                            <div class="mt-1 text-xs text-gray-500">Pendiente: existencia por número de serie.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:1rem;">
            <div class="rounded-2xl border bg-white px-6 py-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Partidas</div>
                <div class="mt-2 text-2xl font-bold">{{ number_format($summary['lines'] ?? 0) }}</div>
            </div>
            <div class="rounded-2xl border bg-white px-6 py-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Cantidad total</div>
                <div class="mt-2 text-2xl font-bold">{{ number_format($summary['quantity'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded-2xl border bg-white px-6 py-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Valor total</div>
                <div class="mt-2 text-2xl font-bold">$ {{ number_format($summary['value'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded-2xl border bg-white px-6 py-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Alertas</div>
                <div class="mt-2 text-2xl font-bold">{{ number_format($summary['with_alerts'] ?? 0) }}</div>
                <div class="text-xs text-gray-500">Sin costo o costo tomado de respaldo</div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <div style="display:grid; grid-template-columns:1fr auto; align-items:center; gap:1rem;">
                    <h3 class="text-base font-bold">Existencias valorizadas</h3>
                    <div class="text-sm font-medium text-gray-500" style="text-align:right;">{{ $rows->count() }} partidas</div>
                </div>
            </div>

            <div class="overflow-x-auto px-4 pb-4">
                <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left">Empresa</th>
                            <th class="px-4 py-3 text-left">Almacén</th>
                            <th class="px-4 py-3 text-left">Ubicación</th>
                            <th class="px-4 py-3 text-left">Producto</th>
                            <th class="px-4 py-3 text-left">Variante</th>
                            <th class="px-4 py-3 text-left">Lote</th>
                            <th class="px-4 py-3 text-left">Serie</th>
                            <th class="px-4 py-3 text-right">Cantidad</th>
                            <th class="px-4 py-3 text-right">Costo unit.</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                            <th class="px-4 py-3 text-left">Método</th>
                            <th class="px-4 py-3 text-left">Fuente costo</th>
                            <th class="px-4 py-3 text-left">Alertas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                <td class="px-4 py-3">{{ $row->company_name ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $row->warehouse_name ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $row->location_name ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold">{{ $row->product_name ?: ('Producto #' . $row->product_id) }}</div>
                                    <div class="text-xs text-gray-500">#{{ $row->product_id }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $row->variant_name ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $row->lot_number ?: ($row->lot_id ?: '—') }}</td>
                                <td class="px-4 py-3">{{ $row->serial_number ?: ($row->stock_serial_number_id ?: '—') }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row->quantity, 2) }}</td>
                                <td class="px-4 py-3 text-right">$ {{ number_format($row->unit_cost, 4) }}</td>
                                <td class="px-4 py-3 text-right font-bold">$ {{ number_format($row->total_value, 2) }}</td>
                                <td class="px-4 py-3">{{ $row->costing_method }}</td>
                                <td class="px-4 py-3">{{ $row->cost_source }}</td>
                                <td class="px-4 py-3">
                                    @if ($row->alerts)
                                        <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">{{ $row->alerts }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-4 py-10 text-center text-gray-500">
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
