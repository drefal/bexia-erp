<x-filament-panels::page>
    @php
        $order = $this->order;
        $editable = false;
        $fieldClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white disabled:opacity-60';
    @endphp

    @if ($order)
        <div class="space-y-6">
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::badge color="info">
                    {{ match ($order->status) {
                        'captured' => 'Capturado',
                        'sent_to_pos', 'pending_pos' => 'En PDV',
                        'paid' => 'Pagado',
                        'cancelled' => 'Cancelado',
                        'delivered' => 'Entregado',
                        default => $order->status,
                    } }}
                </x-filament::badge>

                <span class="text-sm text-gray-500">
                    Papelón · Empresa 3
                </span>

                @if ($order->pos_order_id)
                    <span class="text-sm text-gray-500">
                        Ticket PDV: {{ $order->pos_order_id }}
                    </span>
                @else
                    <span class="text-sm text-gray-500">
                        Sin ticket PDV
                    </span>
                @endif
            </div>

            <x-filament::section heading="Información del pedido">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Cliente *
                        </label>
                        <select
                            wire:model.live="customerContactId"
                            class="{{ $fieldClass }}"
                            @disabled(! $editable)
                        >
                            <option value="">Seleccionar cliente</option>
                            @foreach ($this->customers as $customer)
                                <option value="{{ $customer->id }}">
                                    {{ $customer->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('customerContactId')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Fecha y hora solicitadas *
                        </label>
                        <input
                            type="datetime-local"
                            wire:model="requestedDeliveryAt"
                            class="{{ $fieldClass }}"
                            @disabled(! $editable)
                        />
                        @error('requestedDeliveryAt')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($this->missingCustomerPhone && $editable)
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Teléfono del cliente *
                            </label>
                            <input
                                type="tel"
                                wire:model="newCustomerPhone"
                                class="{{ $fieldClass }}"
                                maxlength="30"
                                placeholder="5512345678"
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                Se actualizará en Contactos al guardar.
                            </p>
                            @error('newCustomerPhone')
                                <p class="text-xs text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    @endif

                    <div>
                        <span class="block text-xs text-gray-500">Folio</span>
                        <strong>{{ $order->number }}</strong>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section heading="Productos y personalización">
                <div class="space-y-4">
                    @foreach ($lines as $i => $line)
                        <div
                            wire:key="glb-order-line-{{ $i }}"
                            class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <strong>Partida {{ $i + 1 }}</strong>

                                @if ($editable)
                                    <x-filament::button
                                        color="danger"
                                        size="sm"
                                        wire:click="removeLine({{ $i }})"
                                        wire:confirm="¿Quitar esta partida del formulario?"
                                    >
                                        Quitar
                                    </x-filament::button>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm">
                                        Producto *
                                    </label>
                                    <select
                                        wire:model.live="lines.{{ $i }}.product_id"
                                        class="{{ $fieldClass }}"
                                        @disabled(! $editable)
                                    >
                                        <option value="0">Seleccionar producto</option>
                                        @foreach ($this->products as $product)
                                            <option value="{{ $product->id }}">
                                                {{ $product->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm">
                                        Variante
                                    </label>
                                    <select
                                        wire:model="lines.{{ $i }}.variant_id"
                                        class="{{ $fieldClass }}"
                                        @disabled(! $editable)
                                    >
                                        <option value="0">
                                            Sin variante
                                        </option>
                                        @foreach ($this->variantsForProduct($line['product_id'] ?? 0) as $variant)
                                            <option value="{{ $variant->id }}">
                                                {{ $variant->variant_value ?: $variant->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm">
                                        Modelo / diseño
                                    </label>
                                    <select
                                        wire:model="lines.{{ $i }}.model_id"
                                        class="{{ $fieldClass }}"
                                        @disabled(! $editable)
                                    >
                                        <option value="0">
                                            Sin diseño
                                        </option>
                                        @foreach ($this->designs as $design)
                                            <option value="{{ $design->id }}">
                                                {{ $design->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm">
                                        Cantidad *
                                    </label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="99"
                                        step="1"
                                        wire:model="lines.{{ $i }}.quantity"
                                        class="{{ $fieldClass }}"
                                        @disabled(! $editable)
                                    />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-sm">
                                        Texto de personalización
                                    </label>
                                    <textarea
                                        wire:model="lines.{{ $i }}.personalization_text"
                                        class="{{ $fieldClass }}"
                                        rows="2"
                                        maxlength="1000"
                                        @disabled(! $editable)
                                    ></textarea>
                                </div>
                            </div>

                            <div class="mt-3 text-sm text-gray-500">
                                Precio registrado:
                                ${{ number_format((float) ($line['unit_price'] ?? 0), 2) }}
                                · Importe:
                                ${{ number_format((float) ($line['subtotal'] ?? 0), 2) }}
                            </div>

                            <p class="mt-1 text-xs text-gray-500">
                                Si cambias producto, variante o cantidad, el precio
                                se recalculará desde Bexia al guardar.
                            </p>
                        </div>
                    @endforeach

                    @if ($editable)
                        <x-filament::button
                            color="gray"
                            icon="heroicon-o-plus"
                            wire:click="addLine"
                        >
                            Agregar producto
                        </x-filament::button>
                    @endif

                    @error('lines')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @error('cart')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </x-filament::section>

            <x-filament::section heading="Observaciones">
                <textarea
                    wire:model="orderNotes"
                    class="{{ $fieldClass }}"
                    rows="3"
                    maxlength="2000"
                    @disabled(! $editable)
                ></textarea>
                @error('orderNotes')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </x-filament::section>

            <x-filament::section heading="Resumen económico">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <div class="text-xs text-gray-500">Total registrado</div>
                        <div class="text-xl font-bold">
                            ${{ number_format((float) $order->total, 2) }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Anticipo registrado</div>
                        <div class="text-xl font-bold">
                            ${{ number_format((float) $order->deposit_amount, 2) }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Saldo registrado</div>
                        <div class="text-xl font-bold">
                            ${{ number_format((float) $order->balance_amount, 2) }}
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    Los importes anteriores son los guardados. Los cambios
                    se recalculan al guardar. Los cobros se hacen en PDV.
                </p>
            </x-filament::section>

        </div>
    @endif
</x-filament-panels::page>
