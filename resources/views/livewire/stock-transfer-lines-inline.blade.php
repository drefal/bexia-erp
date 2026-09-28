<div class="space-y-4">
    @if (session()->has('stock_transfer_lines_saved'))
        <div class="rounded-lg bg-success-50 p-3 text-sm text-success-700 dark:bg-success-500/10 dark:text-success-400">
            {{ session('stock_transfer_lines_saved') }}
        </div>
    @endif

    @error('origin')
        <div class="rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
            {{ $message }}
        </div>
    @enderror

    @error('movement')
        <div class="rounded-lg bg-warning-50 p-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
            {{ $message }}
        </div>
    @enderror

    <div>
        <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3">
            <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                Buscar producto
            </span>
        </label>

        <div class="mt-2">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Nombre, referencia, SKU o código de barras"
                class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 shadow-sm ring-1 ring-inset ring-gray-950/10 transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-600 disabled:text-gray-500 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:placeholder:text-gray-500 dark:focus:ring-primary-500 sm:text-sm sm:leading-6"
            />
        </div>
    </div>

    @if (mb_strlen(trim($search)) >= 2)
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full table-auto divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-3 py-2 text-left">Producto</th>
                        <th class="px-3 py-2 text-right">Existencia</th>
                        <th class="px-3 py-2 text-right">Reservado</th>
                        <th class="px-3 py-2 text-right">Disponible</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($this->products as $product)
                        <tr>
                            <td class="px-3 py-2">
                                <div class="font-medium">
                                    {{ $product['name'] }}
                                </div>

                                @if ($product['sku'])
                                    <div class="text-xs text-gray-500">
                                        {{ $product['sku'] }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-3 py-2 text-right">
                                {{ number_format($product['quantity'], 4) }}
                            </td>

                            <td class="px-3 py-2 text-right">
                                {{ number_format($product['reserved'], 4) }}
                            </td>

                            <td class="px-3 py-2 text-right font-medium">
                                {{ number_format($product['available'], 4) }}
                            </td>

                            <td class="px-3 py-2 text-right">
                                <button
                                    type="button"
                                    wire:click="addProduct({{ $product['id'] }})"
                                    class="fi-btn fi-btn-size-sm fi-color-primary"
                                >
                                    Agregar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-center text-gray-500">
                                No se encontraron productos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full table-auto divide-y divide-gray-200 text-sm dark:divide-white/10">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="px-3 py-2 text-left">Producto / SKU</th>
                    <th class="px-3 py-2 text-right">Existencia</th>
                    <th class="px-3 py-2 text-right">Reservado</th>
                    <th class="px-3 py-2 text-right">Disponible</th>
                    <th class="px-3 py-2 text-right">Cantidad traslado</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($lines as $index => $line)
                    <tr wire:key="transfer-line-{{ $index }}-{{ $line['product_id'] }}">
                        <td class="px-3 py-3">
                            <div class="font-medium">
                                {{ $line['name'] }}
                            </div>

                            @if ($line['sku'])
                                <div class="text-xs text-gray-500">
                                    {{ $line['sku'] }}
                                </div>
                            @endif
                        </td>

                        <td class="px-3 py-3 text-right">
                            {{ number_format((float) $line['stock_quantity'], 4) }}
                        </td>

                        <td class="px-3 py-3 text-right">
                            {{ number_format((float) $line['reserved_quantity'], 4) }}
                        </td>

                        <td class="px-3 py-3 text-right font-medium">
                            {{ number_format((float) $line['available_quantity'], 4) }}
                        </td>

                        <td class="px-3 py-3 text-right align-middle">
                            <input
                                type="number"
                                min="0.0001"
                                step="0.0001"
                                max="{{ $line['available_quantity'] }}"
                                wire:model.blur="lines.{{ $index }}.quantity"
                                class="fi-input ml-auto block w-32 rounded-lg border-none bg-white px-3 py-2 text-right text-sm text-gray-950 shadow-sm ring-1 ring-inset ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20"
                            />

                            @error("lines.$index.quantity")
                                <div class="mt-1 text-xs text-danger-600">
                                    {{ $message }}
                                </div>
                            @enderror
                        </td>

                        <td class="px-3 py-3 text-right">
                            <button
                                type="button"
                                wire:click="removeLine({{ $index }})"
                                class="text-sm font-medium text-danger-600 hover:text-danger-500"
                            >
                                Quitar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-gray-500">
                            Agrega productos al traslado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($movementId)
        <div class="flex justify-end">
            <button
                type="button"
                wire:click="save"
                wire:loading.attr="disabled"
                class="fi-btn fi-btn-size-md fi-color-primary"
            >
                Guardar productos
            </button>
        </div>
    @else
        <div class="text-sm text-gray-500">
            Guarda primero el traslado como borrador para agregar productos.
        </div>
    @endif
</div>
