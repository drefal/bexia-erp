@php
    $movement = $movement ?? null;

    $refund = null;
    $order = null;
    $source = null;
    $destination = null;
    $lines = collect();

    try {
        if ($movement) {
            if (! empty($movement->source_location_id) && \Illuminate\Support\Facades\Schema::hasTable('stock_locations')) {
                $source = \Illuminate\Support\Facades\DB::table('stock_locations')
                    ->where('id', $movement->source_location_id)
                    ->first();
            }

            if (! empty($movement->destination_location_id) && \Illuminate\Support\Facades\Schema::hasTable('stock_locations')) {
                $destination = \Illuminate\Support\Facades\DB::table('stock_locations')
                    ->where('id', $movement->destination_location_id)
                    ->first();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('stock_movement_lines')) {
                $lines = \Illuminate\Support\Facades\DB::table('stock_movement_lines')
                    ->where('stock_movement_id', $movement->id)
                    ->orderBy('id')
                    ->get();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')) {
                $refund = \Illuminate\Support\Facades\DB::table('pos_order_refunds')
                    ->where('stock_movement_id', $movement->id)
                    ->orWhere('number', (string) ($movement->origin_document ?? ''))
                    ->orderByDesc('id')
                    ->first();
            }

            if ($refund && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')) {
                $order = \Illuminate\Support\Facades\DB::table('pos_orders')
                    ->where('id', $refund->pos_order_id)
                    ->first();
            }
        }
    } catch (\Throwable $e) {
        //
    }

    $refundTypeLabel = match ((string) ($refund->type ?? '')) {
        'partial' => 'Parcial',
        'total' => 'Total',
        default => 'Devolución',
    };

    $movementStatusLabel = match ((string) ($movement->status ?? '')) {
        'done' => 'Hecho',
        'draft' => 'Borrador',
        'cancelled' => 'Cancelado',
        default => ucfirst((string) ($movement->status ?? '—')),
    };
@endphp

<div class="space-y-4">
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Movimiento</div>
            <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                {{ $movement->reference ?? '—' }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Tipo</div>
            <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                Entrada por devolución {{ strtolower($refundTypeLabel) }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Origen</div>
            <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                {{ $movement->origin_document ?? '—' }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Estado</div>
            <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                {{ $movementStatusLabel }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Desde</div>
            <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                {{ trim(($source->code ?? '') . ' - ' . ($source->name ?? '')) ?: '—' }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">A</div>
            <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                {{ trim(($destination->code ?? '') . ' - ' . ($destination->name ?? '')) ?: '—' }}
            </div>
        </div>
    </div>

    @if($refund)
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">
                Datos de devolución
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Folio devolución</div>
                    <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $refund->number }}</div>
                </div>

                <div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Tipo</div>
                    <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $refundTypeLabel }}</div>
                </div>

                <div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total devuelto</div>
                    <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                        ${{ number_format((float) ($refund->payment_total ?? $refund->total ?? 0), 2) }}
                    </div>
                </div>

                <div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Ticket original</div>
                    <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $order->number ?? '—' }}</div>
                </div>

                <div class="md:col-span-2">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Motivo</div>
                    <div class="mt-1 text-sm text-gray-950 dark:text-white">{{ $refund->reason ?? '—' }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <div class="text-sm font-semibold text-gray-950 dark:text-white">
                Líneas del movimiento
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Producto
                        </th>
                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Solicitado
                        </th>
                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Hecho
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                    @forelse($lines as $line)
                        <tr>
                            <td class="px-4 py-2 text-gray-950 dark:text-white">
                                {{ $line->notes ?? ('Producto #' . ($line->product_id ?? '')) }}
                            </td>
                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">
                                {{ number_format((float) ($line->requested_quantity ?? 0), 6) }}
                            </td>
                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">
                                {{ number_format((float) ($line->done_quantity ?? 0), 6) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">
                                Sin líneas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
