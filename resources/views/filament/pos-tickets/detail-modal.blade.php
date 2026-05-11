<div class="space-y-5">
    @php
        $status = (string) ($order->status ?? '');
        $inventoryStatus = (string) ($metadata['inventory_status'] ?? 'pending');
        $billingStatus = (string) ($metadata['billing_status'] ?? 'pending');
    @endphp

    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-gray-200 p-4">
            <div class="text-xs font-semibold uppercase text-gray-500">Folio</div>
            <div class="mt-1 text-lg font-black text-gray-900">{{ $order->number ?? ('#' . $order->id) }}</div>
        </div>

        <div class="rounded-xl border border-gray-200 p-4">
            <div class="text-xs font-semibold uppercase text-gray-500">Estado</div>
            <div class="mt-1 font-bold">{{ \App\Filament\Resources\PosTicketResource::statusLabel($status) }}</div>
        </div>

        <div class="rounded-xl border border-gray-200 p-4">
            <div class="text-xs font-semibold uppercase text-gray-500">Inventario</div>
            <div class="mt-1 font-bold">{{ \App\Filament\Resources\PosTicketResource::inventoryStatusLabel($inventoryStatus) }}</div>
            @if(! empty($metadata['stock_movement_reference']))
                <div class="mt-1 text-xs text-gray-500">{{ $metadata['stock_movement_reference'] }}</div>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 p-4">
            <div class="text-xs font-semibold uppercase text-gray-500">Facturación</div>
            <div class="mt-1 font-bold">{{ \App\Filament\Resources\PosTicketResource::billingStatusLabel($billingStatus) }}</div>
            @if(! empty($metadata['billing_requested_at']))
                <div class="mt-1 text-xs text-gray-500">{{ $metadata['billing_requested_at'] }}</div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-gray-200 p-4">
            <div class="text-xs font-semibold uppercase text-gray-500">Cliente</div>
            <div class="mt-1 font-bold">{{ \App\Filament\Resources\PosTicketResource::customerLabel($order->customer_id) }}</div>
        </div>

        <div class="rounded-xl border border-gray-200 p-4">
            <div class="text-xs font-semibold uppercase text-gray-500">Fecha</div>
            <div class="mt-1 font-bold">{{ $order->ordered_at ?? $order->created_at ?? '—' }}</div>
        </div>

        <div class="rounded-xl border border-gray-200 p-4">
            <div class="text-xs font-semibold uppercase text-gray-500">Total</div>
            <div class="mt-1 text-xl font-black">${{ number_format((float) ($order->total ?? 0), 2) }}</div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="text-sm font-black text-gray-900">Acciones del ticket</div>
                <div class="text-xs text-gray-500">Atajos relacionados con este folio.</div>
            </div>

            <div class="flex flex-wrap gap-2">
                @if($status === 'pending_payment')
                    <a href="{{ $pendingPrintUrl }}" target="_blank" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-bold">
                        Imprimir ticket pendiente
                    </a>
                @endif

                @if($status === 'paid')
                    <a href="{{ $receiptPrintUrl }}" target="_blank" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-bold">
                        Imprimir ticket pagado
                    </a>

                    @if($stockMovementUrl !== '#')
                        <a href="{{ $stockMovementUrl }}" target="_blank" class="rounded-lg bg-green-600 px-3 py-2 text-sm font-bold text-white">
                            Ver salida de inventario
                        </a>
                    @endif

                    @if($billingStatus !== 'requested' && $billingStatus !== 'invoiced')
                        <form method="POST" action="{{ route('pos.tickets.billing.request', ['order' => $order->id]) }}" target="_blank">
                            @csrf
                            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white">
                                Enviar a facturación
                            </button>
                        </form>
                    @endif

                    <a href="{{ $invoicePortalUrl }}" target="_blank" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-bold">
                        Portal facturación
                    </a>
                @endif
            </div>
        </div>

        @if(! empty($metadata['inventory_message']))
            <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
                {{ $metadata['inventory_message'] }}
            </div>
        @endif
    </div>

    <div class="rounded-xl border border-gray-200">
        <div class="border-b border-gray-200 p-4">
            <div class="text-sm font-black text-gray-900">Líneas del ticket</div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
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
                        <tr class="border-t border-gray-100">
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
    </div>

    <div class="rounded-xl border border-gray-200">
        <div class="border-b border-gray-200 p-4">
            <div class="text-sm font-black text-gray-900">Pagos</div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left">Método</th>
                        <th class="px-3 py-2 text-right">Importe</th>
                        <th class="px-3 py-2 text-left">Estado</th>
                        <th class="px-3 py-2 text-left">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr class="border-t border-gray-100">
                            <td class="px-3 py-2">{{ $payment->payment_label ?? 'Pago' }}</td>
                            <td class="px-3 py-2 text-right font-bold">${{ number_format((float) ($payment->amount ?? 0), 2) }}</td>
                            <td class="px-3 py-2">{{ $payment->status ?? '—' }}</td>
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
    </div>

    @if($movement)
        <div class="rounded-xl border border-green-200 bg-green-50 p-4">
            <div class="text-sm font-black text-green-900">Salida de inventario asociada</div>
            <div class="mt-2 grid grid-cols-1 gap-2 text-sm md:grid-cols-4">
                <div><strong>Referencia:</strong> {{ $movement->reference ?? '—' }}</div>
                <div><strong>Estado:</strong> {{ $movement->status ?? '—' }}</div>
                <div><strong>Origen:</strong> {{ $movement->origin_document ?? '—' }}</div>
                <div><strong>Fecha:</strong> {{ $movement->movement_at ?? '—' }}</div>
            </div>
        </div>
    @endif
</div>
