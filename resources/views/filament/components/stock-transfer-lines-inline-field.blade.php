@php
    $record = $getRecord();
    $isDraft = ! $record || $record->status === 'draft';
@endphp

@if (! $record)
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
        Guarda primero el traslado como borrador para poder agregar productos.
    </div>
@elseif ($isDraft)
    @livewire(
        'stock-transfer-lines-inline',
        [
            'movementId' => (int) $record->id,
            'companyId' => (int) $record->company_id,
            'warehouseId' => (int) $record->warehouse_id,
            'sourceLocationId' => (int) $record->source_location_id,
        ],
        key('stock-transfer-lines-inline-' . $record->id)
    )
@else
    @php
        $record->load('lines');

        $lineIds = $record->lines
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $receivedTotals = $lineIds === []
            ? collect()
            : \App\Models\StockMovementReceiptLine::query()
                ->selectRaw('stock_movement_line_id, SUM(quantity) AS received_quantity')
                ->whereIn('stock_movement_line_id', $lineIds)
                ->groupBy('stock_movement_line_id')
                ->pluck('received_quantity', 'stock_movement_line_id');
    @endphp

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left font-medium">Producto</th>
                    <th class="px-4 py-3 text-left font-medium">SKU</th>
                    <th class="px-4 py-3 text-right font-medium">Enviado</th>
                    <th class="px-4 py-3 text-right font-medium">Recibido</th>
                    <th class="px-4 py-3 text-right font-medium">Pendiente</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($record->lines as $line)
                    @php
                        $product = \App\Models\Product::query()->find($line->product_id);

                        $sent = (float) $line->done_quantity;

                        $received = (float) (
                            $receivedTotals[(int) $line->id] ?? 0
                        );

                        $pending = max(0, $sent - $received);
                    @endphp

                    <tr>
                        <td class="px-4 py-3">
                            {{ $product?->name ?? ('Producto #' . $line->product_id) }}
                        </td>

                        <td class="px-4 py-3">
                            {{ $product?->internal_reference ?: '—' }}
                        </td>

                        <td class="px-4 py-3 text-right">
                            {{ number_format($sent, 2) }}
                        </td>

                        <td class="px-4 py-3 text-right">
                            {{ number_format($received, 2) }}
                        </td>

                        <td class="px-4 py-3 text-right font-semibold">
                            {{ number_format($pending, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($record->status === 'in_transit')
        <div class="mt-3 text-sm text-gray-600 dark:text-gray-300">
            Para registrar una recepción usa el botón
            <strong>Recibir traslado</strong>.
            La mercancía no recibida permanecerá en tránsito.
        </div>
    @endif
@endif
