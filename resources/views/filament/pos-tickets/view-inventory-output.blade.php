<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $movementStatus = (string) ($movement->status ?? '');
            $movementStatusLabel = match ($movementStatus) {
                'draft' => 'Borrador',
                'waiting' => 'En espera',
                'confirmed' => 'Confirmado',
                'assigned' => 'Reservado',
                'done' => 'Realizado',
                'cancelled', 'canceled' => 'Cancelado',
                default => $movementStatus !== '' ? ucfirst(str_replace('_', ' ', $movementStatus)) : '—',
            };
        @endphp

        <x-filament::section>
            <x-slot name="heading">Cabecera de la salida PDV</x-slot>

            @if(! $movement)
                <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-900">
                    Este ticket no tiene salida de inventario asociada.
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Ticket origen</div>
                        <div class="font-black">{{ $order->number ?? ('#' . $order->id) }}</div>
                    </div>

                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Referencia salida PDV</div>
                        <div class="font-bold">{{ $movement->reference ?? '—' }}</div>
                    </div>

                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Estado</div>
                        <div class="font-bold">{{ $movementStatusLabel }}</div>
                    </div>

                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Fecha</div>
                        <div class="font-bold">{{ $movement->movement_at ?? '—' }}</div>
                    </div>
                </div>

                @if(! empty($metadata['inventory_message']))
                    <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900 dark:border-green-900 dark:bg-green-950 dark:text-green-100">
                        {{ $metadata['inventory_message'] }}
                    </div>
                @endif
            @endif
        </x-filament::section>

        @if($movement)
            <x-filament::section>
                <x-slot name="heading">Líneas de salida</x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-3 py-2 text-left">Producto</th>
                                <th class="px-3 py-2 text-right">Solicitado</th>
                                <th class="px-3 py-2 text-right">Realizado</th>
                                <th class="px-3 py-2 text-right">Costo unitario</th>
                                <th class="px-3 py-2 text-left">Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movementLines as $line)
                                @php
                                    $product = $products->get((int) ($line->product_id ?? 0));
                                    $productName = $product->name ?? ('Producto #' . ($line->product_id ?? ''));
                                @endphp

                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-2">
                                        <div class="font-bold">{{ $productName }}</div>
                                        @if(! empty($product?->sku))
                                            <div class="text-xs text-gray-500">{{ $product->sku }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">{{ number_format((float) ($line->requested_quantity ?? 0), 2) }}</td>
                                    <td class="px-3 py-2 text-right font-bold">{{ number_format((float) ($line->done_quantity ?? 0), 2) }}</td>
                                    <td class="px-3 py-2 text-right">${{ number_format((float) ($line->unit_cost ?? 0), 2) }}</td>
                                    <td class="px-3 py-2">{{ $line->notes ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-4 text-center text-gray-500">
                                        Sin líneas de salida.
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
