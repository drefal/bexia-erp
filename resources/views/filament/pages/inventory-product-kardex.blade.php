<x-filament-panels::page>
    @php
        $summary = $this->summary;
        $rows = $this->rows;
        $productOptions = $this->productOptions();
        $variantOptions = $this->variantOptions();

        $methodLabels = [
            'auto' => 'Automático',
            'average' => 'Costo promedio',
            'fifo' => 'PEPS / FIFO',
            'standard' => 'Costo estándar',
            'recorded' => 'Registrado',
            'mixed' => 'Mixto',
        ];

        $methodText = function ($value) use ($methodLabels) {
            $key = strtolower((string) $value);

            return $methodLabels[$key] ?? ($value ?: '—');
        };

        $sourceText = function ($value) {
            return match (strtolower((string) $value)) {
                'purchase_receipt' => 'Recepción de compra',
                'sale_delivery' => 'Entrega de venta',
                'pos_order' => 'Venta PDV',
                'pos_order_refund' => 'Devolución PDV',
                'stock_adjustment' => 'Ajuste de inventario',
                'legacy', '', 'null' => 'Histórico',
                default => ucfirst(str_replace('_', ' ', (string) $value)),
            };
        };

        $costSourceText = function ($value) {
            $value = (string) $value;

            return match (true) {
                str_contains($value, 'legacy.stock_movement_line.unit_cost:product_variant') => 'Costo histórico de la variante',
                str_contains($value, 'legacy.stock_movement_line.unit_cost:product') => 'Costo histórico del producto',
                str_contains($value, 'legacy.backfill.variant.average_cost_without_tax') => 'Costo promedio histórico de la variante',
                str_contains($value, 'legacy.backfill.product.average_cost_without_tax') => 'Costo promedio histórico del producto',
                str_contains($value, 'purchase_receipt.unit_cost_without_tax') => 'Costo de recepción sin IVA',
                str_contains($value, 'purchase_receipt') => 'Costo de recepción',
                str_contains($value, 'sale_delivery.unit_cost') => 'Costo de entrega',
                str_contains($value, 'pos_order.average_cost_at_sale') => 'Costo promedio al vender',
                str_contains($value, 'pos_order_refund.original_sale_cost') => 'Costo original de la venta',
                str_contains($value, 'stock_adjustment.unit_cost') => 'Costo del ajuste',
                $value === '' => '—',
                default => str_replace(['_', '.'], [' ', ' · '], $value),
            };
        };

        $documentSubtitle = function ($value) {
            $value = trim((string) $value);

            if ($value === '') {
                return '';
            }

            $clean = preg_replace('/^(purchase_receipt|sale_delivery|pos_order_refund|pos_order|stock_adjustment)[\:\-\s]*/i', '', $value);

            return match (true) {
                str_starts_with(strtolower($value), 'purchase_receipt') => 'Recepción de compra' . ($clean ? ': ' . $clean : ''),
                str_starts_with(strtolower($value), 'sale_delivery') => 'Entrega de venta' . ($clean ? ': ' . $clean : ''),
                str_starts_with(strtolower($value), 'pos_order_refund') => 'Devolución PDV' . ($clean ? ': ' . $clean : ''),
                str_starts_with(strtolower($value), 'pos_order') => 'Venta PDV' . ($clean ? ': ' . $clean : ''),
                str_starts_with(strtolower($value), 'stock_adjustment') => 'Ajuste de inventario' . ($clean ? ': ' . $clean : ''),
                default => $value,
            };
        };
    @endphp

    <div class="space-y-6">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-100 bg-gray-50 px-6 py-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Inventario</div>
                        <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Kardex por producto</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Consulta movimientos, entradas, salidas, saldo físico y saldo valorizado.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a
                            href="{{ route('inventory.kardex.print', $this->exportParams()) }}"
                            target="_blank"
                            class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm"
                            style="background:#2563eb;"
                        >
                            Imprimir PDF
                        </a>

                        <a
                            href="{{ route('inventory.kardex.excel', $this->exportParams()) }}"
                            class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm"
                            style="background:#16a34a;"
                        >
                            Descargar Excel
                        </a>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; align-items:start;">
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
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Método valorización</label>
                        <select wire:model.live="valuation_method" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            @foreach ($this->valuationMethodOptions() as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="mt-1 text-xs text-gray-500">{{ $this->valuationMethodHelp() }}</div>
                    </div>

                    <div style="grid-column: span 2; position: relative;">
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Buscar producto</label>
                        <div class="mt-1 flex gap-2">
                            <input
                                type="text"
                                wire:model.live.debounce.400ms="product_search"
                                placeholder="Nombre, SKU, código de barras o referencia interna..."
                                class="w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800"
                            >
                            @if ($this->selectedProductLabel())
                                <button type="button" wire:click="clearProduct" class="whitespace-nowrap rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-semibold shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700">
                                    Quitar
                                </button>
                            @endif
                        </div>

                        @if (! $this->selectedProductLabel() && trim($product_search ?? '') !== '' && count($productOptions) > 0)
                            <div class="absolute z-20 mt-2 w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                @foreach ($productOptions as $id => $label)
                                    <button type="button" wire:click="selectProduct({{ $id }})" class="block w-full border-b border-gray-100 px-4 py-2 text-left text-sm hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800">
                                        {{ $label }} <span class="text-xs text-gray-400">#{{ $id }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div style="position: relative;">
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Buscar variante</label>
                        <div class="mt-1 flex gap-2">
                            <input
                                type="text"
                                wire:model.live.debounce.400ms="variant_search"
                                placeholder="Color, presentación, SKU..."
                                class="w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800"
                                @disabled(! $product_id)
                            >
                            @if ($this->selectedVariantLabel())
                                <button type="button" wire:click="clearVariant" class="whitespace-nowrap rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-semibold shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700">
                                    Quitar
                                </button>
                            @endif
                        </div>

                        @if ($product_id && ! $this->selectedVariantLabel() && count($variantOptions) > 0)
                            <div class="absolute z-20 mt-2 w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
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
                            @foreach ($this->lotOptions() as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Serie</label>
                        <select wire:model.live="stock_serial_number_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <option value="">Todas</option>
                            @foreach ($this->serialOptions() as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Desde</label>
                        <input type="date" wire:model.live="date_from" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Hasta</label>
                        <input type="date" wire:model.live="date_to" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Estado movimiento</label>
                        <select wire:model.live="status" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <option value="">Todos</option>
                            <option value="done">Confirmado</option>
                            <option value="draft">Borrador</option>
                            <option value="cancelled">Cancelado</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Límite</label>
                        <input type="number" min="50" max="5000" wire:model.live="limit" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    </div>

                    <div style="display:flex; align-items:center; justify-content:center; min-height:4.75rem;">
                        <button
                            type="button"
                            wire:click="resetFilters"
                            class="w-full rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2"
                            style="background:#2563eb; border:1px solid #2563eb;"
                        >
                            Limpiar filtros
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 1rem;">
            <div class="rounded-2xl border bg-white px-6 py-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Método</div>
                <div class="mt-2 text-lg font-bold">{{ $methodLabels[$summary['valuation_method'] ?? 'mixed'] ?? ($summary['valuation_method'] ?? '—') }}</div>
            </div>
            <div class="rounded-2xl border bg-white px-6 py-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Entradas</div>
                <div class="mt-2 text-2xl font-bold">{{ number_format($summary['in_qty'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded-2xl border bg-white px-6 py-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Salidas</div>
                <div class="mt-2 text-2xl font-bold">{{ number_format($summary['out_qty'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded-2xl border bg-white px-6 py-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Saldo físico</div>
                <div class="mt-2 text-2xl font-bold">{{ number_format($summary['balance_qty'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded-2xl border bg-white px-6 py-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Saldo valorizado</div>
                <div class="mt-2 text-2xl font-bold">$ {{ number_format($summary['balance_value'] ?? 0, 2) }}</div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <div style="display:grid; grid-template-columns: 1fr auto; align-items:center; gap:1rem;">
                    <h3 class="text-base font-bold">Movimientos del Kardex</h3>
                    <div class="text-sm font-medium text-gray-500" style="text-align:right; align-self:center;">
                        {{ $rows->count() }} movimientos
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto px-4 pb-4">
                <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left">Fecha</th>
                            <th class="px-4 py-3 text-left">Documento</th>
                            <th class="px-4 py-3 text-left">Producto</th>
                            <th class="px-4 py-3 text-right">Entrada</th>
                            <th class="px-4 py-3 text-right">Salida</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                            <th class="px-4 py-3 text-right">Costo aplicado</th>
                            <th class="px-4 py-3 text-right">Costo registrado</th>
                            <th class="px-4 py-3 text-right">Valor mov.</th>
                            <th class="px-4 py-3 text-right">Saldo valor</th>
                            <th class="px-4 py-3 text-left">Método</th>
                            <th class="px-4 py-3 text-left">Origen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                <td class="whitespace-nowrap px-4 py-3">
                                    {{ $row->date ? \Carbon\Carbon::parse($row->date)->format('d/m/Y H:i') : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold">{{ $row->reference ?: '—' }}</div>
                                    @if ($documentSubtitle($row->origin_document))
                                        <div class="text-xs text-gray-500">{{ $documentSubtitle($row->origin_document) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold">{{ $row->product_name ?: ('Producto #' . $row->product_id) }}</div>
                                    @if ($row->variant_name)
                                        <div class="text-xs text-gray-500">{{ $row->variant_name }}</div>
                                    @endif
                                    @if ($row->lot_id || $row->stock_serial_number_id)
                                        <div class="mt-1 text-xs text-gray-500">
                                            Lote: {{ $row->lot_id ?: '—' }} · Serie: {{ $row->stock_serial_number_id ?: '—' }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium">{{ $row->in_qty ? number_format($row->in_qty, 2) : '—' }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ $row->out_qty ? number_format($row->out_qty, 2) : '—' }}</td>
                                <td class="px-4 py-3 text-right font-bold">{{ number_format($row->balance_qty, 2) }}</td>
                                <td class="px-4 py-3 text-right">$ {{ number_format($row->applied_unit_cost, 4) }}</td>
                                <td class="px-4 py-3 text-right text-gray-500">$ {{ number_format($row->recorded_unit_cost, 4) }}</td>
                                <td class="px-4 py-3 text-right">$ {{ number_format($row->movement_value, 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold">$ {{ number_format($row->balance_value, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                        {{ $methodText($row->valuation_method) }}
                                    </span>
                                    @if ($row->costing_method)
                                        <div class="mt-1 text-xs text-gray-500">Config.: {{ $methodText($row->costing_method) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $sourceText($row->source_type ?: 'legacy') }}</div>
                                    <div class="text-xs text-gray-500">{{ $costSourceText($row->cost_source) }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-4 py-10 text-center text-gray-500">
                                    No hay movimientos para los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
