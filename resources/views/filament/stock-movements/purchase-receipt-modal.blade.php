@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $receiptId = (int) ($receiptId ?? 0);
    $tenantId = (int) ($tenantId ?? request()->route('tenant') ?? 0);

    $receipt = Schema::hasTable('purchase_receipts')
        ? DB::table('purchase_receipts')->where('id', $receiptId)->first()
        : null;

    $order = null;
    $lines = collect();
    $movement = null;
    $warehouse = null;
    $location = null;
    $receivedBy = null;

    if ($receipt) {
        if (Schema::hasTable('purchase_orders') && $receipt->purchase_order_id) {
            $order = DB::table('purchase_orders')->where('id', $receipt->purchase_order_id)->first();
        }

        if (Schema::hasTable('purchase_receipt_lines')) {
            $lines = DB::table('purchase_receipt_lines')
                ->where('purchase_receipt_id', $receipt->id)
                ->orderBy('id')
                ->get();
        }

        if (Schema::hasTable('stock_movements') && $receipt->stock_movement_id) {
            $movement = DB::table('stock_movements')->where('id', $receipt->stock_movement_id)->first();
        }

        if (Schema::hasTable('warehouses') && $receipt->warehouse_id) {
            $warehouse = DB::table('warehouses')->where('id', $receipt->warehouse_id)->first();
        }

        if (Schema::hasTable('stock_locations') && $receipt->location_id) {
            $location = DB::table('stock_locations')->where('id', $receipt->location_id)->first();
        }

        if (Schema::hasTable('users') && $receipt->received_by_user_id) {
            $receivedBy = DB::table('users')->where('id', $receipt->received_by_user_id)->first();
        }
    }

    $money = fn ($v) => '$' . number_format((float) $v, 2);
    $qty = fn ($v) => number_format((float) $v, 6);
@endphp

@if(! $receipt)
    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        No se encontró la recepción.
    </div>
@else
    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Recepción</div>
                <div class="mt-1 text-base font-black text-gray-900">{{ $receipt->number ?? ('REC #' . $receipt->id) }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Origen</div>
                <div class="mt-1 text-base font-black text-gray-900">{{ $order->number ?? '—' }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Movimiento</div>
                <div class="mt-1 text-base font-black text-gray-900">{{ $movement->reference ?? '—' }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Fecha</div>
                <div class="mt-1 text-sm font-bold text-gray-900">
                    {{ $receipt->received_at ? \Carbon\Carbon::parse($receipt->received_at)->format('d/m/Y H:i') : '—' }}
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Almacén / ubicación</div>
                <div class="mt-1 text-sm font-bold text-gray-900">
                    {{ $warehouse->name ?? '—' }} / {{ $location->name ?? '—' }}
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Recibió</div>
                <div class="mt-1 text-sm font-bold text-gray-900">
                    {{ $receivedBy->name ?? $receivedBy->email ?? '—' }}
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-black text-gray-700">Producto</th>
                        <th class="px-3 py-2 text-left font-black text-gray-700">Variante</th>
                        <th class="px-3 py-2 text-right font-black text-gray-700">Cantidad</th>
                        <th class="px-3 py-2 text-right font-black text-gray-700">Costo</th>
                        <th class="px-3 py-2 text-right font-black text-gray-700">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $line)
                        <tr class="border-t border-gray-100">
                            <td class="px-3 py-2 font-bold text-gray-900">{{ $line->product_label ?? 'Producto' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $line->variant_label ?? '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ $qty($line->received_quantity ?? 0) }}
                                <div class="text-xs text-gray-500">{{ $line->purchase_unit_label ?? '' }}</div>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $money($line->unit_cost_without_tax ?? 0) }}</td>
                            <td class="px-3 py-2 text-right font-black tabular-nums">{{ $money($line->line_total_with_tax ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex justify-end">
            <div class="w-full max-w-sm rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="flex justify-between py-1 text-sm">
                    <span>Subtotal</span>
                    <strong>{{ $money($receipt->total_without_tax ?? 0) }}</strong>
                </div>
                <div class="flex justify-between py-1 text-sm">
                    <span>IVA</span>
                    <strong>{{ $money($receipt->total_tax ?? 0) }}</strong>
                </div>
                <div class="mt-2 flex justify-between border-t border-gray-200 pt-2 text-base">
                    <span class="font-black">Total</span>
                    <strong>{{ $money($receipt->total_with_tax ?? 0) }}</strong>
                </div>
            </div>
        </div>
    </div>
@endif
