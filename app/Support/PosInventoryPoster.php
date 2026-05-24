<?php

namespace App\Support;

use App\Support\Inventory\OutboundSerialNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PosInventoryPoster
{
    public function postPaidOrder(int $orderId): array
    {
        if ($orderId <= 0) {
            return [
                'ok' => false,
                'status' => 'invalid_order',
                'message' => 'Orden inválida.',
            ];
        }

        if (
            ! Schema::hasTable('pos_orders')
            || ! Schema::hasTable('pos_order_lines')
            || ! Schema::hasTable('stock_movements')
            || ! Schema::hasTable('stock_movement_lines')
            || ! Schema::hasTable('stock_quants')
        ) {
            return $this->markOrderInventoryStatus($orderId, [
                'inventory_status' => 'pending_tables',
                'inventory_message' => 'Faltan tablas de inventario para generar la salida.',
            ]);
        }

        try {
            return DB::transaction(function () use ($orderId): array {
                $order = DB::table('pos_orders')
                    ->where('id', $orderId)
                    ->lockForUpdate()
                    ->first();

                if (! $order) {
                    return [
                        'ok' => false,
                        'status' => 'not_found',
                        'message' => 'No se encontró el ticket PDV.',
                    ];
                }

                $metadata = $this->metadataArray($order->metadata ?? null);

                if (! empty($metadata['stock_movement_id'])) {
                    return [
                        'ok' => true,
                        'status' => 'already_posted',
                        'stock_movement_id' => (int) $metadata['stock_movement_id'],
                        'message' => 'El ticket ya tenía salida de inventario.',
                    ];
                }

                if ((string) ($order->status ?? '') !== 'paid') {
                    return $this->updateOrderMetadata($order, [
                        'inventory_status' => 'pending_payment',
                        'inventory_message' => 'El ticket todavía no está pagado.',
                    ]);
                }

                app(\App\Support\PosStockReservationService::class)->releaseOrder($orderId, 'paid');

                $pos = null;

                if (! empty($order->pos_point_id) && Schema::hasTable('pos_points')) {
                    $pos = DB::table('pos_points')
                        ->where('id', (int) $order->pos_point_id)
                        ->first();
                }

                $companyId = (int) ($order->company_id ?? $pos->company_id ?? 0);
                $warehouseId = (int) ($pos->warehouse_id ?? 0);
                $sourceLocationId = (int) ($pos->stock_source_location_id ?? $pos->stock_location_id ?? 0);

                if ($companyId <= 0 || $warehouseId <= 0 || $sourceLocationId <= 0) {
                    return $this->updateOrderMetadata($order, [
                        'inventory_status' => 'pending_configuration',
                        'inventory_message' => 'Falta configurar empresa, almacén o ubicación de stock del PDV.',
                    ]);
                }

                $operationType = $this->deliveryOperationType($companyId, $warehouseId, $pos);
                $destinationLocationId = $operationType
                    ? (int) ($operationType->destination_location_id ?? 0)
                    : 0;

                if ($destinationLocationId <= 0) {
                    $destinationLocationId = $this->customerLocationId($companyId);
                }

                if (! $operationType || $destinationLocationId <= 0) {
                    return $this->updateOrderMetadata($order, [
                        'inventory_status' => 'pending_configuration',
                        'inventory_message' => 'No se encontró tipo de operación de salida o ubicación destino Clientes.',
                    ]);
                }

                $lines = DB::table('pos_order_lines')
                    ->where('pos_order_id', $order->id)
                    ->orderBy('id')
                    ->get();

                if ($lines->isEmpty()) {
                    return $this->updateOrderMetadata($order, [
                        'inventory_status' => 'no_lines',
                        'inventory_message' => 'El ticket no tiene líneas para inventario.',
                    ]);
                }

                $stockLines = $this->stockableLines($lines);

                if ($stockLines->isEmpty()) {
                    return $this->updateOrderMetadata($order, [
                        'inventory_status' => 'no_stockable_products',
                        'inventory_message' => 'El ticket solo contiene servicios o productos sin control de inventario.',
                        'inventory_delivered_at' => now()->toDateTimeString(),
                    ]);
                }

                $lockedQuants = [];

                foreach ($stockLines as $line) {
                    $qty = round((float) ($line->quantity ?? 0), 6);
                    $lineProduct = $this->normalizePosLineProduct($line);
                    $productId = (int) $lineProduct['product_id'];
                    $productVariantId = $lineProduct['product_variant_id'];
                    $serialNumberId = ! empty($line->stock_serial_number_id) ? (int) $line->stock_serial_number_id : null;
                    $lotId = ! empty($line->stock_lot_id) ? (int) $line->stock_lot_id : null;

                    if ($qty <= 0 || $productId <= 0) {
                        continue;
                    }

                    if ($this->lineRequiresLotNumber($companyId, $productId, $productVariantId)) {
                        if (! $lotId) {
                            return $this->updateOrderMetadata($order, [
                                'inventory_status' => 'pending_lot_required',
                                'inventory_message' => 'Selecciona lote para ' . ($line->product_name ?? ('producto #' . $productId)) . '.',
                            ]);
                        }
                    }

                    if ($this->lineRequiresSerialNumber($companyId, $productId, $productVariantId)) {
                        if (! $serialNumberId) {
                            return $this->updateOrderMetadata($order, [
                                'inventory_status' => 'pending_serial_required',
                                'inventory_message' => 'Selecciona número de serie para ' . ($line->product_name ?? ('producto #' . $productId)) . '.',
                            ]);
                        }

                        if (abs($qty - 1.0) > 0.000001) {
                            return $this->updateOrderMetadata($order, [
                                'inventory_status' => 'pending_serial_quantity',
                                'inventory_message' => 'El producto ' . ($line->product_name ?? ('producto #' . $productId)) . ' usa número de serie y debe venderse con cantidad 1 por línea.',
                            ]);
                        }

                        app(OutboundSerialNumberService::class)->assertSerialAvailable(
                            $serialNumberId,
                            $this->serialContextForPosLine($order, $line, $companyId, null)
                        );
                    }

                    $quantQuery = DB::table('stock_quants')
                        ->where('company_id', $companyId)
                        ->where('warehouse_id', $warehouseId)
                        ->where('location_id', $sourceLocationId)
                        ->where('product_id', $productId);

                    $productVariantId
                        ? $quantQuery->where('product_variant_id', $productVariantId)
                        : $quantQuery->whereNull('product_variant_id');

                    if ($lotId) {
                        $quantQuery->where('lot_id', $lotId);
                    } elseif ($this->lineRequiresLotNumber($companyId, $productId, $productVariantId)) {
                        $quantQuery->whereNotNull('lot_id');
                    } else {
                        $quantQuery->whereNull('lot_id');
                    }

                    $quant = $quantQuery
                        ->lockForUpdate()
                        ->first();

                    if ($quant && ! $lotId && ! empty($quant->lot_id)) {
                        $lotId = (int) $quant->lot_id;
                    }

                    if (! $quant) {
                        return $this->updateOrderMetadata($order, [
                            'inventory_status' => 'pending_no_quant',
                            'inventory_message' => 'No hay existencia registrada para ' . ($line->product_name ?? ('producto #' . $productId)) . '.',
                        ]);
                    }

                    $physical = (float) ($quant->quantity ?? 0);

                    if ($physical < $qty) {
                        return $this->updateOrderMetadata($order, [
                            'inventory_status' => 'pending_insufficient_stock',
                            'inventory_message' => 'Existencia insuficiente para ' . ($line->product_name ?? ('producto #' . $productId)) . '. Existencia: ' . number_format($physical, 2) . ', requerido: ' . number_format($qty, 2) . '.',
                        ]);
                    }

                    $lockedQuants[(int) $line->id] = [
                        'quant' => $quant,
                        'lot_id' => $lotId,
                    ];
                }

                $reference = $this->nextMovementReference($operationType, $companyId, $warehouseId, $sourceLocationId, $pos);

                $movementId = DB::table('stock_movements')->insertGetId($this->filterTableColumns('stock_movements', [
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'stock_operation_type_id' => (int) $operationType->id,
                    'source_location_id' => $sourceLocationId,
                    'destination_location_id' => $destinationLocationId,
                    'reference' => $reference,
                    'movement_at' => now(),
                    'status' => 'done',
                    'origin_document' => 'pos_order:' . $order->id,
                    'contact_id' => $order->customer_id ?: null,
                    'notes' => 'Salida automática por cobro PDV ' . ($order->number ?? ('#' . $order->id)),
                    'created_by' => auth()->id(),
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));

                foreach ($stockLines as $line) {
                    $qty = round((float) ($line->quantity ?? 0), 6);
                    $lineProduct = $this->normalizePosLineProduct($line);
                    $productId = (int) $lineProduct['product_id'];
                    $productVariantId = $lineProduct['product_variant_id'];
                    $serialNumberId = ! empty($line->stock_serial_number_id) ? (int) $line->stock_serial_number_id : null;
                    $lockedQuant = $lockedQuants[(int) $line->id] ?? null;
                    $quant = is_array($lockedQuant) ? ($lockedQuant['quant'] ?? null) : $lockedQuant;
                    $lotId = is_array($lockedQuant) ? ($lockedQuant['lot_id'] ?? null) : (! empty($quant->lot_id) ? (int) $quant->lot_id : null);

                    if ($qty <= 0 || $productId <= 0) {
                        continue;
                    }

                    if (! $quant) {
                        continue;
                    }

                    $unitCost = $this->lineUnitCost($line, $quant);

                    $movementLineId = DB::table('stock_movement_lines')->insertGetId($this->filterTableColumns('stock_movement_lines', [
                        'stock_movement_id' => $movementId,
                        'product_id' => $productId,
                        'product_variant_id' => $productVariantId,
                        'lot_id' => $lotId,
                        'stock_serial_number_id' => $serialNumberId,
                        'source_type' => 'pos_order',
                        'source_id' => $order->id,
                        'source_line_type' => 'pos_order_line',
                        'source_line_id' => $line->id,
                        'requested_quantity' => $qty,
                        'done_quantity' => $qty,
                        'unit_cost' => $unitCost,
                        'notes' => trim((string) ($line->product_reference ?? '') . ' - ' . (string) ($line->product_name ?? '')),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));

                    $this->applyCostToMovementLine((int) $movementLineId, 'pos_order.average_cost_at_sale');

                    if ($lotId) {
                        $this->markPosLineLotTracking($line, (int) $lotId, $order, (int) $movementLineId);
                    }

                    if ($serialNumberId) {
                        app(OutboundSerialNumberService::class)->markSold(
                            $serialNumberId,
                            $this->serialContextForPosLine($order, $line, $companyId, (int) $movementLineId)
                        );
                    }

                    DB::table('stock_quants')
                        ->where('id', $quant->id)
                        ->update([
                            'quantity' => round(((float) $quant->quantity) - $qty, 6),
                            'updated_at' => now(),
                        ]);
                }

                return $this->updateOrderMetadata($order, [
                    'inventory_status' => 'delivered',
                    'inventory_message' => 'Salida de inventario generada al cobrar.',
                    'inventory_delivered_at' => now()->toDateTimeString(),
                    'stock_movement_id' => $movementId,
                    'stock_movement_reference' => $reference,
                ]);
            });
        } catch (Throwable $e) {
            report($e);

            return $this->markOrderInventoryStatus($orderId, [
                'inventory_status' => 'pending_error',
                'inventory_message' => Str::limit($e->getMessage(), 240),
            ]);
        }
    }


    protected function normalizePosLineProduct(object $line): array
    {
        $productId = (int) ($line->product_id ?? 0);
        $productVariantId = ! empty($line->product_variant_id) ? (int) $line->product_variant_id : null;

        if (
            ! $productVariantId
            && $productId > 0
            && Schema::hasTable('products')
            && Schema::hasColumn('products', 'parent_product_id')
        ) {
            $product = DB::table('products')->where('id', $productId)->first();

            if ($product && ! empty($product->parent_product_id)) {
                $productVariantId = $productId;
                $productId = (int) $product->parent_product_id;
            }
        }

        return [
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
        ];
    }

    protected function lineRequiresLotNumber(int $companyId, int $productId, ?int $productVariantId = null): bool
    {
        if ($companyId <= 0 || $productId <= 0) {
            return false;
        }

        if (Schema::hasTable('products')) {
            foreach (array_values(array_unique(array_filter([$productId, $productVariantId]))) as $id) {
                $product = DB::table('products')->where('id', (int) $id)->first();

                if (! $product) {
                    continue;
                }

                foreach (['tracking', 'advanced_tracking_mode'] as $column) {
                    $value = strtolower(trim((string) ($product->{$column} ?? '')));

                    if ($value !== '' && (str_contains($value, 'lot') || str_contains($value, 'lote'))) {
                        return true;
                    }
                }
            }
        }

        if (Schema::hasTable('stock_lots')) {
            $query = DB::table('stock_lots')
                ->where('company_id', $companyId)
                ->where('product_id', $productId);

            $productVariantId
                ? $query->where('product_variant_id', $productVariantId)
                : $query->whereNull('product_variant_id');

            return $query->exists();
        }

        return false;
    }
    protected function lineRequiresSerialNumber(int $companyId, int $productId, ?int $productVariantId = null): bool
    {
        if (Schema::hasTable('stock_serial_numbers') && $companyId > 0 && $productId > 0) {
            $serialQuery = DB::table('stock_serial_numbers')
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->where('status', 'available');

            $productVariantId
                ? $serialQuery->where('product_variant_id', $productVariantId)
                : $serialQuery->whereNull('product_variant_id');

            if ($serialQuery->exists()) {
                return true;
            }
        }

        if (! Schema::hasTable('products')) {
            return false;
        }

        foreach (array_values(array_filter([$productId, $productVariantId])) as $id) {
            $product = DB::table('products')->where('id', (int) $id)->first();

            if (! $product) {
                continue;
            }

            foreach (['tracking', 'advanced_tracking_mode'] as $column) {
                $value = strtolower(trim((string) ($product->{$column} ?? '')));

                if ($value !== '' && (str_contains($value, 'serial') || str_contains($value, 'serie'))) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function lotContextForPosLine(object $order, object $line, int $lotId, ?int $movementLineId = null): array
    {
        $lot = Schema::hasTable('stock_lots')
            ? DB::table('stock_lots')->where('id', $lotId)->first()
            : null;

        $lineProduct = $this->normalizePosLineProduct($line);

        return [
            'stock_lot_id' => $lotId,
            'lot_number' => $lot->lot_number ?? null,
            'product_id' => (int) $lineProduct['product_id'],
            'product_variant_id' => $lineProduct['product_variant_id'],
            'stock_movement_line_id' => $movementLineId,
            'source_type' => 'pos_order',
            'source_id' => (int) ($order->id ?? 0),
            'source_line_type' => 'pos_order_line',
            'source_line_id' => (int) ($line->id ?? 0),
            'user_id' => auth()->id(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    protected function markPosLineLotTracking(object $line, int $lotId, object $order, int $movementLineId): void
    {
        if (! Schema::hasTable('pos_order_lines')) {
            return;
        }

        $updates = [
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('pos_order_lines', 'stock_lot_id')) {
            $updates['stock_lot_id'] = $lotId;
        }

        if (Schema::hasColumn('pos_order_lines', 'lot_tracking_metadata')) {
            $updates['lot_tracking_metadata'] = json_encode(
                $this->lotContextForPosLine($order, $line, $lotId, $movementLineId),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        DB::table('pos_order_lines')
            ->where('id', (int) ($line->id ?? 0))
            ->update($this->filterTableColumns('pos_order_lines', $updates));
    }
    protected function serialContextForPosLine(object $order, object $line, int $companyId, ?int $movementLineId = null): array
    {
        $lineProduct = $this->normalizePosLineProduct($line);

        return [
            'company_id' => $companyId,
            'product_id' => (int) $lineProduct['product_id'],
            'product_variant_id' => $lineProduct['product_variant_id'],
            'stock_movement_line_id' => $movementLineId,
            'source_type' => 'pos_order',
            'source_id' => (int) ($order->id ?? 0),
            'source_line_type' => 'pos_order_line',
            'source_line_id' => (int) ($line->id ?? 0),
            'user_id' => auth()->id(),
        ];
    }

    protected function stockableLines($lines)
    {
        $productIds = collect($lines)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($productIds->isEmpty() || ! Schema::hasTable('products')) {
            return collect();
        }

        $products = DB::table('products')
            ->whereIn('id', $productIds)
            ->get(['id', 'product_type', 'tracking', 'standard_cost', 'average_cost_without_tax', 'allow_out_of_stock_sales'])
            ->keyBy('id');

        return collect($lines)
            ->filter(function ($line) use ($products): bool {
                $productId = (int) ($line->product_id ?? 0);
                $qty = (float) ($line->quantity ?? 0);

                if ($productId <= 0 || $qty <= 0) {
                    return false;
                }

                // BEXIA_V5543C2_SERIAL_LINE_ALWAYS_STOCKABLE
                if (! empty($line->stock_serial_number_id)) {
                    return true;
                }

                $product = $products->get($productId);

                if (! $product) {
                    return false;
                }

                $type = (string) ($product->product_type ?? 'stockable');

                if ($type === 'service') {
                    return false;
                }

                return in_array($type, ['stockable', 'consumable'], true);
            })
            ->values();
    }

    protected function deliveryOperationType(int $companyId, int $warehouseId, ?object $pos): ?object
    {
        if (! Schema::hasTable('stock_operation_types')) {
            return null;
        }

        if (! empty($pos?->operation_type_id)) {
            $row = DB::table('stock_operation_types')
                ->where('id', (int) $pos->operation_type_id)
                ->first();

            if ($row) {
                return $row;
            }
        }

        return DB::table('stock_operation_types')
            ->where('operation_kind', 'delivery')
            ->where('is_active', true)
            ->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            })
            ->where(function ($query) use ($warehouseId): void {
                $query->where('warehouse_id', $warehouseId)
                    ->orWhereNull('warehouse_id');
            })
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN warehouse_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sequence')
            ->first();
    }

    protected function customerLocationId(int $companyId): ?int
    {
        if (! Schema::hasTable('stock_locations')) {
            return null;
        }

        $query = DB::table('stock_locations')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->where('code', 'CLIENTES')
                    ->orWhere('name', 'like', '%Cliente%');
            });

        return $query->orderBy('id')->value('id');
    }

    protected function nextMovementReference(object $operationType, int $companyId, int $warehouseId, int $sourceLocationId, ?object $pos = null): string
    {
        // V5.47.2: mantener base por ubicación/almacén y cambiar solo OUT por PDV.
        // Formato esperado: CDF/PDV/000001.
        $prefix = 'PDV';

        $locationCode = Schema::hasTable('stock_locations')
            ? DB::table('stock_locations')->where('id', $sourceLocationId)->value('code')
            : null;

        $warehouseCode = Schema::hasTable('warehouses')
            ? DB::table('warehouses')->where('id', $warehouseId)->value('code')
            : null;

        $posCode = null;

        if ($pos && isset($pos->code) && trim((string) $pos->code) !== '') {
            $posCode = trim((string) $pos->code);
        }

        $base = trim((string) ($locationCode ?: $warehouseCode ?: $posCode ?: 'PDV'));

        $operation = DB::table('stock_operation_types')
            ->where('id', (int) $operationType->id)
            ->lockForUpdate()
            ->first();

        $next = max(1, (int) ($operation->next_number ?? 1));

        do {
            $reference = $base . '/' . $prefix . '/' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            $exists = DB::table('stock_movements')->where('reference', $reference)->exists();
            $next++;
        } while ($exists);

        DB::table('stock_operation_types')
            ->where('id', (int) $operationType->id)
            ->update([
                'next_number' => $next,
                'updated_at' => now(),
            ]);

        return $reference;
    }

    protected function lineUnitCost(object $line, object $quant): float
    {
        if (! empty($quant->average_cost)) {
            return round((float) $quant->average_cost, 6);
        }

        if (Schema::hasTable('products') && ! empty($line->product_id)) {
            $product = DB::table('products')
                ->where('id', (int) $line->product_id)
                ->first(['standard_cost', 'average_cost_without_tax']);

            if ($product) {
                $cost = (float) ($product->average_cost_without_tax ?? $product->standard_cost ?? 0);

                return round($cost, 6);
            }
        }

        return 0.0;
    }

    protected function updateOrderMetadata(object $order, array $changes): array
    {
        $metadata = $this->metadataArray($order->metadata ?? null);

        foreach ($changes as $key => $value) {
            $metadata[$key] = $value;
        }

        DB::table('pos_orders')
            ->where('id', $order->id)
            ->update([
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);

        return [
            'ok' => in_array(($metadata['inventory_status'] ?? null), ['delivered', 'no_stockable_products', 'already_posted'], true),
            'status' => (string) ($metadata['inventory_status'] ?? 'unknown'),
            'stock_movement_id' => $metadata['stock_movement_id'] ?? null,
            'message' => (string) ($metadata['inventory_message'] ?? ''),
        ];
    }

    protected function markOrderInventoryStatus(int $orderId, array $changes): array
    {
        $order = DB::table('pos_orders')->where('id', $orderId)->first();

        if (! $order) {
            return [
                'ok' => false,
                'status' => 'not_found',
                'message' => 'No se encontró el ticket PDV.',
            ];
        }

        return $this->updateOrderMetadata($order, $changes);
    }

    protected function metadataArray(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && trim($metadata) !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function filterTableColumns(string $table, array $data): array
    {
        $columns = Schema::getColumnListing($table);

        return array_intersect_key($data, array_flip($columns));
    }
    protected function applyCostToMovementLine(int $movementLineId, string $costSource): void
    {
        try {
            app(\App\Support\Inventory\StockMovementLineCostBackfiller::class)
                ->applyToLineId($movementLineId, $costSource);
        } catch (\Throwable $e) {
            report($e);
        }
    }

}
