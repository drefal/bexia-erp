<x-filament-panels::page>
    @php
        $lines = $this->getLines();
        $isDraft = (string) $this->record->status === 'draft';
        $variantOptions = $this->quickVariantOptions();
        $lotOptions = $this->quickLotOptions();
        $requiresLot = $this->quickProductRequiresLot();
        $requiresSerial = $this->quickProductRequiresSerial();
        $totalDifference = $lines->sum(fn ($line) => (float) ($line->difference_quantity ?? 0));
        $totalValue = $lines->sum(fn ($line) => (float) ($line->difference_quantity ?? 0) * (float) ($line->unit_cost ?? 0));
    @endphp

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Referencia</div>
                    <div class="mt-1 font-semibold text-gray-950 dark:text-white">
                        {{ $this->record->reference ?: ('Ajuste #' . $this->record->id) }}
                    </div>
                </div>

                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Estado</div>
                    <div class="mt-1">
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $isDraft ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                            {{ $this->record->status === 'draft' ? 'Borrador' : ($this->record->status === 'done' ? 'Hecho' : 'Cancelado') }}
                        </span>
                    </div>
                </div>

                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Líneas</div>
                    <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $lines->count() }}</div>
                </div>

                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Diferencia total</div>
                    <div class="mt-1 font-semibold text-gray-950 dark:text-white">
                        {{ number_format($totalDifference, 2) }}
                        <span class="text-sm text-gray-500">/ {{ $this->money($totalValue) }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                <strong>Motivo:</strong> {{ $this->record->reason ?: 'Sin motivo' }}
            </div>
        </div>

        @if ($isDraft)
            <div class="rounded-2xl border border-primary-200 bg-white p-5 shadow-sm dark:border-primary-800 dark:bg-gray-900">
                <form wire:submit.prevent="addQuickLineInline" class="space-y-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">Agregar producto rápido</h3>
                        <p class="text-sm text-gray-500">
                            Busca por nombre, SKU, código de barras o referencia interna. Si el producto tiene variantes, selecciona la variante antes de agregar.
                        </p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-12">
                        <div class="lg:col-span-5">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Producto</label>

                            <input
                                type="text"
                                wire:model.live.debounce.350ms="quickProductSearch"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                placeholder="Escribe al menos 2 caracteres..."
                                @if($this->quickProductId) readonly @endif
                            />

                            @if (! $this->quickProductId && trim($this->quickProductSearch) !== '' && mb_strlen(trim($this->quickProductSearch)) >= 2)
                                <div class="mt-2 max-h-64 overflow-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                    @forelse ($this->quickProductOptions() as $productId => $label)
                                        <button
                                            type="button"
                                            wire:click="selectQuickProduct({{ (int) $productId }})"
                                            class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm hover:bg-primary-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                        >
                                            {{ $label }}
                                        </button>
                                    @empty
                                        <div class="px-3 py-2 text-sm text-gray-500">Sin resultados.</div>
                                    @endforelse
                                </div>
                            @endif

                            @if ($this->quickProductId)
                                <div class="mt-2 flex items-center justify-between rounded-xl bg-primary-50 px-3 py-2 text-sm text-primary-900 dark:bg-primary-950 dark:text-primary-100">
                                    <span>{{ $this->quickProductLabel }}</span>
                                    <button type="button" wire:click="clearQuickProduct" class="font-semibold text-primary-700 hover:underline">
                                        Cambiar
                                    </button>
                                </div>
                            @endif
                        </div>

                        <div class="lg:col-span-3">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Variante</label>

                            <select
                                wire:model.live="quickVariantId"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                @if(count($variantOptions) === 0) disabled @endif
                            >
                                <option value="">Sin variante</option>
                                @foreach ($variantOptions as $variantId => $label)
                                    <option value="{{ (int) $variantId }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            @if ($this->quickProductId && count($variantOptions) > 0 && ! $this->quickVariantId)
                                <p class="mt-1 text-xs text-amber-600">Este producto tiene variantes.</p>
                            @endif
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Lote</label>

                            <select
                                wire:model.live="quickLotId"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                @if(! $requiresLot) disabled @endif
                            >
                                <option value="">Sin lote</option>
                                @foreach ($lotOptions as $lotId => $label)
                                    <option value="{{ (int) $lotId }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            @if ($requiresLot && ! $this->quickLotId)
                                <p class="mt-1 text-xs text-amber-600">Lote requerido.</p>
                            @endif
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Cantidad contada</label>
                            <input
                                type="number"
                                step="0.000001"
                                wire:model.defer="quickCountedQuantity"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>

                        <div class="lg:col-span-10">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Notas</label>
                            <input
                                type="text"
                                wire:model.defer="quickNotes"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                placeholder="Opcional"
                            />
                        </div>

                        <div class="flex items-end lg:col-span-2">
                            <button
                                type="submit"
                                class="w-full rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50"
                                @if($requiresSerial) disabled @endif
                            >
                                Agregar / actualizar
                            </button>
                        </div>
                    </div>

                    @if ($requiresSerial)
                        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            Este producto maneja número de serie. El ajuste por serie se hará en una pantalla especial para mantener trazabilidad individual.
                        </div>
                    @endif
                </form>
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Productos capturados</h3>
                <p class="text-sm text-gray-500">Puedes capturar varias cantidades y después usar “Guardar cantidades”.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Producto</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Variante / lote</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Actual</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Contada</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Diferencia</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Costo</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Notas</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($lines as $line)
                            @php
                                $productBits = array_filter([
                                    $line->product_internal_reference ?? null,
                                    $line->product_sku ?? null,
                                    $line->product_barcode ?? null,
                                ]);

                                $variantName = trim((string) ($line->variant_variant_name ?: $line->variant_name ?: ''));
                                $variantBits = array_filter([
                                    $line->variant_internal_reference ?? null,
                                    $line->variant_sku ?? null,
                                    $line->variant_barcode ?? null,
                                ]);

                                $lotLabel = $line->lot_number ? ('Lote: ' . $line->lot_number) : null;
                                $difference = (float) ($line->difference_quantity ?? 0);
                            @endphp

                            <tr class="align-top hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-950 dark:text-white">
                                        {{ $line->product_name ?: ('Producto #' . $line->product_id) }}
                                    </div>

                                    @if ($productBits)
                                        <div class="mt-1 text-xs text-gray-500">{{ implode(' · ', $productBits) }}</div>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    @if ($variantName !== '')
                                        <div class="font-medium text-gray-800 dark:text-gray-100">{{ $variantName }}</div>
                                    @else
                                        <div class="text-gray-400">Sin variante</div>
                                    @endif

                                    @if ($variantBits)
                                        <div class="mt-1 text-xs text-gray-500">{{ implode(' · ', $variantBits) }}</div>
                                    @endif

                                    @if ($lotLabel)
                                        <div class="mt-1 rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                            {{ $lotLabel }}
                                        </div>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ $this->quantity($line->current_quantity) }}
                                </td>

                                <td class="px-4 py-3 text-right">
                                    @if ($isDraft)
                                        <input
                                            type="number"
                                            step="0.000001"
                                            wire:model.defer="countedInputs.{{ (int) $line->id }}"
                                            class="w-28 rounded-lg border-gray-300 text-right text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        />
                                    @else
                                        <span class="tabular-nums">{{ $this->quantity($line->counted_quantity) }}</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right tabular-nums">
                                    <span class="{{ $difference < 0 ? 'text-red-600' : ($difference > 0 ? 'text-emerald-600' : 'text-gray-600') }}">
                                        {{ $this->quantity($difference) }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ $this->money($line->unit_cost) }}
                                </td>

                                <td class="px-4 py-3">
                                    @if ($isDraft)
                                        <input
                                            type="text"
                                            wire:model.defer="notesInputs.{{ (int) $line->id }}"
                                            class="w-56 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        />
                                    @else
                                        <span class="text-gray-600 dark:text-gray-300">{{ $line->notes ?: '—' }}</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right">
                                    @if ($isDraft)
                                        <div class="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                wire:click="saveLine({{ (int) $line->id }})"
                                                class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700"
                                            >
                                                Guardar
                                            </button>

                                            <button
                                                type="button"
                                                wire:click="deleteLine({{ (int) $line->id }})"
                                                wire:confirm="¿Quitar esta línea?"
                                                class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700"
                                            >
                                                Quitar
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-gray-400">Bloqueado</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                    No hay productos capturados todavía.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
