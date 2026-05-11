<x-filament-panels::page>
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

    <style>
        @media print {
            body {
                background: white !important;
            }

            aside,
            nav,
            header,
            .fi-topbar,
            .fi-sidebar,
            .fi-header-actions,
            .fi-breadcrumbs,
            .fi-global-search {
                display: none !important;
            }

            .fi-main,
            .fi-main-ctn,
            .fi-page,
            .fi-page-content {
                margin: 0 !important;
                padding: 0 !important;
                max-width: none !important;
                width: 100% !important;
            }

            .print-card {
                box-shadow: none !important;
                border: 1px solid #111 !important;
            }

            @page {
                size: letter;
                margin: 12mm;
            }
        }
    </style>

    <div class="mx-auto max-w-4xl space-y-4 print-card rounded-xl border border-gray-300 bg-white p-6 text-gray-950">
        <div class="flex items-start justify-between border-b border-gray-300 pb-4">
            <div>
                <div class="text-2xl font-black">Salida de inventario PDV</div>
                <div class="mt-1 text-sm text-gray-600">Generada desde ticket de Punto de Venta</div>
            </div>

            <div class="text-right">
                <div class="text-xs uppercase text-gray-500">Referencia</div>
                <div class="text-xl font-black">{{ $movement->reference ?? '—' }}</div>
            </div>
        </div>

        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900 print:hidden">
            PDF generado en pantalla. Usa el botón superior <strong>Imprimir / guardar PDF</strong> o las opciones del navegador para imprimir o guardar.
        </div>

        @if(! $movement)
            <div class="rounded-lg border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-900">
                Este ticket no tiene salida de inventario asociada.
            </div>
        @else
            <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                <div>
                    <div class="text-xs font-bold uppercase text-gray-500">Ticket origen</div>
                    <div class="font-black">{{ $order->number ?? ('#' . $order->id) }}</div>
                </div>

                <div>
                    <div class="text-xs font-bold uppercase text-gray-500">Estado</div>
                    <div class="font-bold">{{ $movementStatusLabel }}</div>
                </div>

                <div>
                    <div class="text-xs font-bold uppercase text-gray-500">Fecha</div>
                    <div class="font-bold">{{ $movement->movement_at ?? '—' }}</div>
                </div>

                <div>
                    <div class="text-xs font-bold uppercase text-gray-500">Movimiento</div>
                    <div class="font-bold">#{{ $movement->id }}</div>
                </div>
            </div>

            @if(! empty($metadata['inventory_message']))
                <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900">
                    {{ $metadata['inventory_message'] }}
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-400">
                            <th class="py-2 text-left">Producto</th>
                            <th class="py-2 text-right">Solicitado</th>
                            <th class="py-2 text-right">Realizado</th>
                            <th class="py-2 text-right">Costo unitario</th>
                            <th class="py-2 text-left">Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movementLines as $line)
                            @php
                                $product = $products->get((int) ($line->product_id ?? 0));
                                $productName = $product->name ?? ('Producto #' . ($line->product_id ?? ''));
                            @endphp

                            <tr class="border-b border-gray-200">
                                <td class="py-2 pr-3">
                                    <div class="font-bold">{{ $productName }}</div>
                                    @if(! empty($product?->sku))
                                        <div class="text-xs text-gray-500">{{ $product->sku }}</div>
                                    @endif
                                </td>
                                <td class="py-2 text-right">{{ number_format((float) ($line->requested_quantity ?? 0), 2) }}</td>
                                <td class="py-2 text-right font-bold">{{ number_format((float) ($line->done_quantity ?? 0), 2) }}</td>
                                <td class="py-2 text-right">${{ number_format((float) ($line->unit_cost ?? 0), 2) }}</td>
                                <td class="py-2 pl-3">{{ $line->notes ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-gray-500">Sin líneas de salida.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-2 gap-8 pt-10 text-center text-sm">
                <div>
                    <div class="border-t border-gray-400 pt-2">Entregó</div>
                </div>

                <div>
                    <div class="border-t border-gray-400 pt-2">Recibió / Validó</div>
                </div>
            </div>
        @endif
    </div>

</x-filament-panels::page>
