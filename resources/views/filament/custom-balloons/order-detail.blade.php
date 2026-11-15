<div class="space-y-5">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <div class="text-xs text-gray-500">Cliente</div>
            <div class="font-semibold">
                {{ $order->customer_name_snapshot }}
            </div>
            <div class="text-sm">
                {{ $order->customer_phone_snapshot ?: 'Sin teléfono' }}
            </div>
        </div>

        <div>
            <div class="text-xs text-gray-500">
                Fecha solicitada de entrega
            </div>
            <div class="font-semibold">
                {{ $order->promised_at
                    ? $order->promised_at
                        ->timezone('America/Mexico_City')
                        ->format('d/m/Y H:i')
                    : 'No especificada' }}
            </div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="p-3 text-left">Producto / diseño</th>
                    <th class="p-3 text-right">Cantidad</th>
                    <th class="p-3 text-right">Precio</th>
                    <th class="p-3 text-right">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->lines as $line)
                    @php
                        $metadata = is_array($line->metadata)
                            ? $line->metadata
                            : [];
                    @endphp
                    <tr class="border-t dark:border-gray-700">
                        <td class="p-3">
                            <div class="font-semibold">
                                {{ $metadata['product_name'] ?? $line->model_name_snapshot }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $line->size_name_snapshot }}
                                @if (! empty($metadata['inflation']))
                                    · {{ $metadata['inflation'] }}
                                @endif
                            </div>
                            @if ($line->model_id)
                                <div class="text-xs">
                                    Diseño: {{ $line->model_name_snapshot }}
                                </div>
                            @endif
                            @if ($line->personalization_text)
                                <div class="text-xs">
                                    Texto: {{ $line->personalization_text }}
                                </div>
                            @endif
                        </td>
                        <td class="p-3 text-right">
                            {{ number_format($line->quantity, 0) }}
                        </td>
                        <td class="p-3 text-right">
                            ${{ number_format($line->unit_price, 2) }}
                        </td>
                        <td class="p-3 text-right font-semibold">
                            ${{ number_format($line->subtotal, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($order->notes)
        <div>
            <div class="text-xs text-gray-500">Observaciones</div>
            <p class="text-sm">{{ $order->notes }}</p>
        </div>
    @endif

    <div class="flex justify-end gap-6 border-t pt-4 dark:border-gray-700">
        <div>
            <div class="text-xs text-gray-500">Total</div>
            <div class="text-lg font-bold">
                ${{ number_format($order->total, 2) }}
            </div>
        </div>
        <div>
            <div class="text-xs text-gray-500">Saldo</div>
            <div class="text-lg font-bold">
                ${{ number_format($order->balance_amount, 2) }}
            </div>
        </div>
    </div>

    <div class="text-xs text-gray-500">
        @if ($order->pos_order_id)
            Ticket PDV relacionado: {{ $order->pos_order_id }}
        @else
            Pedido pendiente de envío al PDV.
        @endif
    </div>
</div>
