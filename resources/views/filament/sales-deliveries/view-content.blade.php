@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $delivery = $record;

    $order = DB::table('sales_orders')
        ->where('id', $delivery->sales_order_id)
        ->first();

    $lines = DB::table('sale_delivery_lines')
        ->where('sale_delivery_id', $delivery->id)
        ->orderBy('id')
        ->get();

    $movement = null;
    $movementLines = collect();

    if (! empty($delivery->stock_movement_id)) {
        $movement = DB::table('stock_movements')
            ->where('id', $delivery->stock_movement_id)
            ->first();

        $movementLines = DB::table('stock_movement_lines')
            ->where('stock_movement_id', $delivery->stock_movement_id)
            ->orderBy('id')
            ->get();
    }

    $warehouse = null;
    $sourceLocation = null;
    $destinationLocation = null;

    if (Schema::hasTable('warehouses') && ! empty($delivery->warehouse_id)) {
        $warehouse = DB::table('warehouses')
            ->where('id', $delivery->warehouse_id)
            ->first();
    }

    if (Schema::hasTable('stock_locations')) {
        if (! empty($delivery->source_location_id)) {
            $sourceLocation = DB::table('stock_locations')
                ->where('id', $delivery->source_location_id)
                ->first();
        }

        if (! empty($delivery->destination_location_id)) {
            $destinationLocation = DB::table('stock_locations')
                ->where('id', $delivery->destination_location_id)
                ->first();
        }
    }

    $statusLabel = match ((string) $delivery->status) {
        'draft' => 'Borrador',
        'done' => 'Validada',
        'cancelled' => 'Cancelada',
        default => $delivery->status ?: 'Sin estado',
    };

    $orderStatusLabel = match ((string) ($order->status ?? '')) {
        'draft' => 'Borrador',
        'quotation' => 'Cotización',
        'sent' => 'Enviada',
        'confirmed' => 'Orden de venta',
        'partially_delivered' => 'Parcialmente entregada',
        'delivered' => 'Entregada',
        'cancelled' => 'Cancelada',
        default => $order->status ?? '—',
    };

    $movementStatusLabel = match ((string) ($movement->status ?? '')) {
        'draft' => 'Borrador',
        'confirmed' => 'Confirmado',
        'done' => 'Hecho',
        'cancelled' => 'Cancelado',
        default => $movement->status ?? '—',
    };

    $typeLabel = match ((string) $delivery->delivery_type) {
        'complete' => 'Completa',
        'partial' => 'Parcial',
        default => $delivery->delivery_type ?: 'Sin tipo',
    };

    $originDocumentLabel = function ($origin) use ($delivery) {
        $origin = trim((string) ($origin ?? ''));

        if ($origin === '') {
            return '—';
        }

        if (str_starts_with($origin, 'sale_delivery:')) {
            return 'Entrega de venta ' . ($delivery->number ?: ('#' . $delivery->id));
        }

        if (str_starts_with($origin, 'sale_order:')) {
            return 'Orden de venta #' . substr($origin, strlen('sale_order:'));
        }

        if (str_starts_with($origin, 'purchase_receipt:')) {
            return 'Recepción de compra #' . substr($origin, strlen('purchase_receipt:'));
        }

        if (str_starts_with($origin, 'stock_adjustment:')) {
            return 'Ajuste de inventario #' . substr($origin, strlen('stock_adjustment:'));
        }

        return $origin;
    };

    $locationLabel = function ($location) {
        if (! $location) {
            return '—';
        }

        $name = trim((string) ($location->name ?? ''));
        $code = trim((string) ($location->code ?? ''));

        if ($name !== '' && $code !== '') {
            return $name . ' (' . $code . ')';
        }

        return $name !== '' ? $name : ($code !== '' ? $code : '—');
    };

    $warehouseLabel = function ($warehouse) {
        if (! $warehouse) {
            return '—';
        }

        $name = trim((string) ($warehouse->name ?? ''));
        $code = trim((string) ($warehouse->code ?? ''));

        if ($name !== '' && $code !== '') {
            return $name . ' (' . $code . ')';
        }

        return $name !== '' ? $name : ($code !== '' ? $code : '—');
    };

    $productLabel = function ($productId) {
        if (! $productId || ! Schema::hasTable('products')) {
            return '—';
        }

        $product = DB::table('products')
            ->where('id', $productId)
            ->first();

        if (! $product) {
            return '—';
        }

        $code = '';

        foreach (['internal_reference', 'sku', 'barcode', 'code'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                $value = trim((string) ($product->{$column} ?? ''));

                if ($value !== '') {
                    $code = $value;
                    break;
                }
            }
        }

        $name = Schema::hasColumn('products', 'name')
            ? trim((string) ($product->name ?? ''))
            : '';

        if ($code !== '' && $name !== '') {
            return $code . ' - ' . $name;
        }

        return $name !== '' ? $name : ($code !== '' ? $code : '—');
    };

    $variantLabel = function ($variantId) {
        if (! $variantId || ! Schema::hasTable('products')) {
            return '—';
        }

        $variant = DB::table('products')
            ->where('id', $variantId)
            ->first();

        if (! $variant) {
            return '—';
        }

        $reference = '';

        foreach (['internal_reference', 'sku', 'barcode', 'code'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                $value = trim((string) ($variant->{$column} ?? ''));

                if ($value !== '') {
                    $reference = $value;
                    break;
                }
            }
        }

        $group = Schema::hasColumn('products', 'variant_group')
            ? trim((string) ($variant->variant_group ?? ''))
            : '';

        $value = Schema::hasColumn('products', 'variant_value')
            ? trim((string) ($variant->variant_value ?? ''))
            : '';

        $variantText = '';

        if ($group !== '' && $value !== '') {
            $variantText = $group . ': ' . $value;
        } elseif ($value !== '') {
            $variantText = $value;
        } elseif (Schema::hasColumn('products', 'variant_name')) {
            $variantText = trim((string) ($variant->variant_name ?? ''));
        } elseif (Schema::hasColumn('products', 'name')) {
            $variantText = trim((string) ($variant->name ?? ''));
        }

        if ($reference !== '' && $variantText !== '') {
            return $reference . ' - ' . $variantText;
        }

        return $variantText !== '' ? $variantText : ($reference !== '' ? $reference : '—');
    };

    $deliveryLineByMovementLine = $lines
        ->filter(fn ($line) => ! empty($line->stock_movement_line_id))
        ->keyBy('stock_movement_line_id');
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-3 text-sm font-bold text-gray-950">
                Datos de la entrega
            </div>

            <div class="space-y-2 text-sm text-gray-700">
                <div><span class="font-semibold text-gray-500">Folio:</span> {{ $delivery->number }}</div>
                <div><span class="font-semibold text-gray-500">Estado:</span> {{ $statusLabel }}</div>
                <div><span class="font-semibold text-gray-500">Tipo:</span> {{ $typeLabel }}</div>
                <div><span class="font-semibold text-gray-500">Fecha de creación:</span> {{ $delivery->created_at }}</div>
                <div><span class="font-semibold text-gray-500">Fecha de validación:</span> {{ $delivery->delivered_at ?: 'No validada' }}</div>
                <div><span class="font-semibold text-gray-500">Movimiento de inventario:</span> {{ $delivery->stock_movement_id ? ('Movimiento #' . $delivery->stock_movement_id) : 'Sin movimiento' }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-3 text-sm font-bold text-gray-950">
                Orden relacionada
            </div>

            <div class="space-y-2 text-sm text-gray-700">
                <div><span class="font-semibold text-gray-500">Orden:</span> {{ $order->number ?? ('#' . $delivery->sales_order_id) }}</div>
                <div><span class="font-semibold text-gray-500">Cliente:</span> {{ $order->customer_name ?? '—' }}</div>
                <div><span class="font-semibold text-gray-500">Estado de la orden:</span> {{ $orderStatusLabel }}</div>
                <div><span class="font-semibold text-gray-500">Almacén:</span> {{ $warehouseLabel($warehouse) }}</div>
                <div><span class="font-semibold text-gray-500">Ubicación origen:</span> {{ $locationLabel($sourceLocation) }}</div>
                <div><span class="font-semibold text-gray-500">Ubicación destino:</span> {{ $locationLabel($destinationLocation) }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="mb-3 text-sm font-bold text-gray-950">
            Productos de la entrega
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-3 py-2">Producto</th>
                        <th class="px-3 py-2">Variante</th>
                        <th class="px-3 py-2 text-right">Cantidad</th>
                        <th class="px-3 py-2">Línea de movimiento</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $line)
                        <tr class="border-t border-gray-100">
                            <td class="px-3 py-2 font-medium text-gray-950">{{ $line->product_label }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $line->variant_label ?: '—' }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $line->quantity, 2) }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $line->stock_movement_line_id ? ('Línea #' . $line->stock_movement_line_id) : 'Pendiente' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($movement)
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-3 text-sm font-bold text-gray-950">
                Movimiento de inventario
            </div>

            <div class="mb-4 grid grid-cols-1 gap-2 text-sm text-gray-700 md:grid-cols-2">
                <div><span class="font-semibold text-gray-500">Movimiento:</span> #{{ $movement->id }}</div>
                <div><span class="font-semibold text-gray-500">Referencia:</span> {{ $movement->reference }}</div>
                <div><span class="font-semibold text-gray-500">Estado:</span> {{ $movementStatusLabel }}</div>
                <div><span class="font-semibold text-gray-500">Documento origen:</span> {{ $originDocumentLabel($movement->origin_document ?? '') }}</div>
                <div><span class="font-semibold text-gray-500">Ubicación origen:</span> {{ $locationLabel($sourceLocation) }}</div>
                <div><span class="font-semibold text-gray-500">Ubicación destino:</span> {{ $locationLabel($destinationLocation) }}</div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-2">Producto</th>
                            <th class="px-3 py-2">Variante</th>
                            <th class="px-3 py-2 text-right">Cantidad solicitada</th>
                            <th class="px-3 py-2 text-right">Cantidad realizada</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($movementLines as $line)
                            @php
                                $deliveryLine = $deliveryLineByMovementLine[$line->id] ?? null;
                            @endphp

                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2 font-medium text-gray-950">
                                    {{ $deliveryLine->product_label ?? $productLabel($line->product_id) }}
                                </td>
                                <td class="px-3 py-2 text-gray-700">
                                    {{ $deliveryLine->variant_label ?? $variantLabel($line->product_variant_id) }}
                                </td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $line->requested_quantity, 2) }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $line->done_quantity, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($delivery->notes)
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-3 text-sm font-bold text-gray-950">
                Notas
            </div>

            <div class="text-sm text-gray-700">
                {{ $delivery->notes }}
            </div>
        </div>
    @endif
</div>
