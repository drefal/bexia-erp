<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $status = (string) ($order->status ?? '');
            $inventoryStatus = (string) ($metadata['inventory_status'] ?? 'pending');
            $billingStatus = (string) ($metadata['billing_status'] ?? 'pending');
        @endphp

        <x-filament::section>
            <x-slot name="heading">Cabecera del ticket</x-slot>

            <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm md:grid-cols-4 xl:grid-cols-7">
                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Folio</div>
                    <div class="mt-0.5 font-black text-gray-950 dark:text-white">
                        {{ $order->number ?? ('#' . $order->id) }}
                    </div>
                </div>

                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Estado</div>
                    <div class="mt-0.5 font-bold">
                        {{ \App\Filament\Resources\PosTicketResource::statusLabel($status) }}
                    </div>
                </div>

                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Inventario</div>
                    <div class="mt-0.5 font-bold">
                        {{ \App\Filament\Resources\PosTicketResource::inventoryStatusLabel($inventoryStatus) }}
                    </div>
                    @if(! empty($metadata['stock_movement_reference']))
                        <div class="mt-0.5 text-[11px] text-gray-500">
                            {{ $metadata['stock_movement_reference'] }}
                        </div>
                    @endif
                </div>

                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Facturación</div>
                    <div class="mt-0.5 font-bold">
                        {{ \App\Filament\Resources\PosTicketResource::billingStatusLabel($billingStatus) }}
                    </div>
                    @if(! empty($metadata['billing_requested_at']))
                        <div class="mt-0.5 text-[11px] text-gray-500">
                            {{ $metadata['billing_requested_at'] }}
                        </div>
                    @endif
                </div>

                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Cliente</div>
                    <div class="mt-0.5 font-bold">
                        {{ \App\Filament\Resources\PosTicketResource::customerLabel($order->customer_id) }}
                    </div>
                </div>

                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Fecha</div>
                    <div class="mt-0.5 font-bold">
                        {{ $order->ordered_at ?? $order->created_at ?? '—' }}
                    </div>
                </div>

                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Total</div>
                    <div class="mt-0.5 text-lg font-black">
                        ${{ number_format((float) ($order->total ?? 0), 2) }}
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Líneas del ticket</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-left">Producto</th>
                            <th class="px-3 py-2 text-right">Cant.</th>
                            <th class="px-3 py-2 text-right">Precio</th>
                            <th class="px-3 py-2 text-right">Subtotal</th>
                            <th class="px-3 py-2 text-right">IVA</th>
                            <th class="px-3 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lines as $line)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2">
                                    <div class="font-bold">{{ $line->product_name ?? 'Producto' }}</div>
                                    @if(! empty($line->product_reference))
                                        <div class="text-xs text-gray-500">{{ $line->product_reference }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) ($line->quantity ?? 0), 2) }}</td>
                                <td class="px-3 py-2 text-right">${{ number_format((float) ($line->unit_price ?? 0), 2) }}</td>
                                <td class="px-3 py-2 text-right">${{ number_format((float) ($line->subtotal ?? 0), 2) }}</td>
                                <td class="px-3 py-2 text-right">${{ number_format((float) ($line->tax_total ?? 0), 2) }}</td>
                                <td class="px-3 py-2 text-right font-bold">${{ number_format((float) ($line->total ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-4 text-center text-gray-500">Sin líneas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Pagos</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-left">Método</th>
                            <th class="px-3 py-2 text-right">Importe</th>
                            <th class="px-3 py-2 text-left">Estado</th>
                            <th class="px-3 py-2 text-left">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2">{{ $payment->payment_label ?? 'Pago' }}</td>
                                <td class="px-3 py-2 text-right font-bold">${{ number_format((float) ($payment->amount ?? 0), 2) }}</td>
                                <td class="px-3 py-2">
                                    @php
                                        $paymentStatus = (string) ($payment->status ?? '');
                                        $paymentStatusLabel = match ($paymentStatus) {
                                            'paid' => 'Pagado',
                                            'pending' => 'Pendiente',
                                            'cancelled', 'canceled' => 'Cancelado',
                                            'refunded' => 'Reembolsado',
                                            'failed' => 'Fallido',
                                            default => $paymentStatus !== '' ? ucfirst(str_replace('_', ' ', $paymentStatus)) : '—',
                                        };
                                    @endphp

                                    {{ $paymentStatusLabel }}
                                </td>
                                <td class="px-3 py-2">{{ $payment->created_at ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-center text-gray-500">Sin pagos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        @if($movement)
            <x-filament::section>
                <x-slot name="heading">Salida de inventario asociada</x-slot>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Referencia</div>
                        <div class="font-bold">{{ $movement->reference ?? '—' }}</div>
                    </div>

                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Estado</div>
                        <div class="font-bold">
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

                            {{ $movementStatusLabel }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Origen</div>
                        <div class="font-bold">{{ $order->number ?? ($movement->origin_document ?? '—') }}</div>
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

                @if($stockMovementUrl !== '#')
                    <div class="mt-4">
                        <a href="{{ $stockMovementUrl }}" class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-sm font-bold text-white">
                            Ver salida completa
                        </a>
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
