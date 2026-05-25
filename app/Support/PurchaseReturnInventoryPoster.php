<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PurchaseReturnInventoryPoster
{
    public function createFromReceipt(int $purchaseReceiptId, string $reason, ?string $notes = null): int
    {
        if ($purchaseReceiptId <= 0) {
            throw new RuntimeException('Recepción inválida.');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException('El motivo de la devolución a proveedor es obligatorio.');
        }

        foreach (['purchase_returns', 'purchase_return_lines', 'purchase_receipts', 'purchase_receipt_lines', 'stock_movements', 'stock_movement_lines', 'stock_quants'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('Falta la tabla requerida: ' . $table . '. Ejecuta migraciones.');
            }
        }

        return DB::transaction(function () use ($purchaseReceiptId, $reason, $notes): int {
            $now = now();

            $receipt = DB::table('purchase_receipts')
                ->where('id', $purchaseReceiptId)
                ->lockForUpdate()
                ->first();

            if (! $receipt) {
                throw new RuntimeException('No se encontró la recepción de compra.');
            }

            if ((string) ($receipt->status ?? '') !== 'done') {
                throw new RuntimeException('Solo se pueden devolver recepciones validadas.');
            }

            $order = Schema::hasTable('purchase_orders')
                ? DB::table('purchase_orders')->where('id', $receipt->purchase_order_id)->first()
                : null;

            $sourceLocationId = $this->destinationStockLocationId($receipt);
            $destinationLocationId = $this->supplierLocationId($receipt);

            if (! $sourceLocationId) {
                throw new RuntimeException('No se pudo determinar la ubicación origen del inventario.');
            }

            $lines = DB::table('purchase_receipt_lines')
                ->where('purchase_receipt_id', $receipt->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw new RuntimeException('La recepción no tiene líneas para devolver.');
            }

            $returnableItems = [];

            foreach ($lines as $line) {
                $original = $this->originalReceiptMovementLine($line);

                $receivedQty = $this->decimal($line->received_base_quantity ?? $line->received_quantity ?? 0);

                if ($receivedQty <= 0 && $original && ! empty($original->done_quantity)) {
                    $receivedQty = $this->decimal($original->done_quantity);
                }

                if ($receivedQty <= 0) {
                    continue;
                }

                $alreadyReturned = (float) DB::table('purchase_return_lines as prl')
                    ->join('purchase_returns as pr', 'pr.id', '=', 'prl.purchase_return_id')
                    ->where('prl.purchase_receipt_line_id', $line->id)
                    ->where('pr.status', 'done')
                    ->sum('prl.quantity');

                $remainingQty = $this->decimal($receivedQty - $alreadyReturned);

                if ($remainingQty <= 0) {
                    continue;
                }

                if (! $original) {
                    throw new RuntimeException('No se encontró el movimiento original de recepción para ' . ($line->product_label ?? ('línea #' . $line->id)) . '.');
                }

                $lotId = ! empty($original->lot_id)
                    ? (int) $original->lot_id
                    : (! empty($line->lot_id) ? (int) $line->lot_id : null);

                $unitCost = $original->unit_cost !== null
                    ? (float) $original->unit_cost
                    : (float) ($line->unit_cost_without_tax ?? 0);

                $costingMethod = $original->costing_method ?? 'average';

                $serialRows = $this->availableSerialRowsForReceiptLine($receipt, $line, $original);

                if ($serialRows !== []) {
                    foreach ($serialRows as $serial) {
                        if ($remainingQty <= 0) {
                            break;
                        }

                        $returnableItems[] = [
                            'line' => $line,
                            'quantity' => 1.0,
                            'original_movement_line' => $original,
                            'lot_id' => ! empty($serial->lot_id) ? (int) $serial->lot_id : $lotId,
                            'serial_id' => (int) $serial->id,
                            'unit_cost' => $unitCost,
                            'costing_method' => $costingMethod,
                        ];

                        $remainingQty = $this->decimal($remainingQty - 1.0);
                    }

                    continue;
                }

                $availableQty = $this->availableQuantQuantity($receipt, $line, $lotId, $sourceLocationId);
                $qtyToReturn = min($remainingQty, $availableQty);

                if ($qtyToReturn <= 0) {
                    continue;
                }

                $returnableItems[] = [
                    'line' => $line,
                    'quantity' => $this->decimal($qtyToReturn),
                    'original_movement_line' => $original,
                    'lot_id' => $lotId,
                    'serial_id' => null,
                    'unit_cost' => $unitCost,
                    'costing_method' => $costingMethod,
                ];
            }

            if ($returnableItems === []) {
                throw new RuntimeException('No hay existencia disponible para devolver al proveedor desde esta recepción.');
            }

            $number = $this->nextPurchaseReturnNumber((int) ($receipt->company_id ?? 0));

            $purchaseReturnId = DB::table('purchase_returns')->insertGetId($this->filterTableColumns('purchase_returns', [
                'company_id' => $receipt->company_id ?? null,
                'purchase_receipt_id' => $receipt->id,
                'purchase_order_id' => $receipt->purchase_order_id ?? null,
                'supplier_contact_id' => $order->supplier_contact_id ?? null,
                'number' => $number,
                'status' => 'done',
                'return_type' => 'available',
                'warehouse_id' => $receipt->warehouse_id ?? null,
                'source_location_id' => $sourceLocationId,
                'destination_location_id' => $destinationLocationId,
                'reason' => $reason,
                'notes' => $notes,
                'created_by_user_id' => auth()->id(),
                'returned_at' => $now,
                'metadata' => json_encode([
                    'source_type' => 'purchase_receipt',
                    'source_id' => (int) $receipt->id,
                    'purchase_order_id' => (int) ($receipt->purchase_order_id ?? 0),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            $movementId = DB::table('stock_movements')->insertGetId($this->filterTableColumns('stock_movements', [
                'company_id' => $receipt->company_id ?? null,
                'warehouse_id' => $receipt->warehouse_id ?? null,
                'stock_operation_type_id' => $this->purchaseReturnOperationTypeId($receipt, $sourceLocationId, $destinationLocationId),
                'source_location_id' => $sourceLocationId,
                'destination_location_id' => $destinationLocationId,
                'reference' => $number,
                'movement_at' => $now,
                'status' => 'done',
                'origin_document' => 'purchase_return:' . $purchaseReturnId,
                'contact_id' => $order->supplier_contact_id ?? null,
                'notes' => 'Salida por devolución a proveedor desde recepción ' . ($receipt->number ?? ('#' . $receipt->id)) . '. Motivo: ' . $reason,
                'created_by' => auth()->id(),
                'confirmed_by' => auth()->id(),
                'confirmed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            foreach ($returnableItems as $item) {
                $line = $item['line'];
                $qty = (float) $item['quantity'];
                $lotId = $item['lot_id'];
                $serialId = $item['serial_id'];
                $unitCost = (float) $item['unit_cost'];
                $costingMethod = $item['costing_method'];

                $returnLineId = DB::table('purchase_return_lines')->insertGetId($this->filterTableColumns('purchase_return_lines', [
                    'purchase_return_id' => $purchaseReturnId,
                    'purchase_receipt_id' => $receipt->id,
                    'purchase_receipt_line_id' => $line->id,
                    'purchase_order_id' => $line->purchase_order_id ?? $receipt->purchase_order_id,
                    'purchase_order_line_id' => $line->purchase_order_line_id ?? null,
                    'company_id' => $receipt->company_id ?? null,
                    'product_id' => $line->product_id,
                    'product_variant_id' => $line->product_variant_id,
                    'product_label' => $line->product_label ?? null,
                    'variant_label' => $line->variant_label ?? null,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($qty * $unitCost, 6),
                    'stock_lot_id' => $lotId,
                    'lot_tracking_metadata' => $lotId ? json_encode($this->lotMetadata($receipt, $line, $lotId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'stock_serial_number_id' => $serialId,
                    'serial_tracking_metadata' => $serialId ? json_encode($this->serialMetadata($receipt, $line, $serialId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'notes' => $reason,
                    'metadata' => json_encode([
                        'original_stock_movement_line_id' => (int) $item['original_movement_line']->id,
                        'original_cost_source' => $item['original_movement_line']->cost_source ?? null,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));

                $movementLineId = DB::table('stock_movement_lines')->insertGetId($this->filterTableColumns('stock_movement_lines', [
                    'stock_movement_id' => $movementId,
                    'product_id' => $line->product_id,
                    'product_variant_id' => $line->product_variant_id,
                    'lot_id' => $lotId,
                    'stock_serial_number_id' => $serialId,
                    'requested_quantity' => $qty,
                    'done_quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($qty * $unitCost, 6),
                    'costing_method' => $costingMethod,
                    'cost_source' => 'purchase_return.original_receipt_cost',
                    'source_type' => 'purchase_return',
                    'source_id' => $purchaseReturnId,
                    'source_line_type' => 'purchase_return_line',
                    'source_line_id' => $returnLineId,
                    'notes' => 'Devolución a proveedor de ' . ($line->product_label ?? ('producto #' . $line->product_id)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));

                DB::table('purchase_return_lines')
                    ->where('id', $returnLineId)
                    ->update($this->filterTableColumns('purchase_return_lines', [
                        'stock_movement_line_id' => $movementLineId,
                        'updated_at' => $now,
                    ]));

                $this->decreaseStockQuant($receipt, $line, $sourceLocationId, $qty, $lotId);
                $this->markSerialReturnedToSupplier($serialId, $movementLineId, $purchaseReturnId, $returnLineId);
            }

            DB::table('purchase_returns')
                ->where('id', $purchaseReturnId)
                ->update($this->filterTableColumns('purchase_returns', [
                    'stock_movement_id' => $movementId,
                    'updated_at' => $now,
                ]));

            return (int) $purchaseReturnId;
        });
    }

    protected function originalReceiptMovementLine(object $line): ?object
    {
        if (! Schema::hasTable('stock_movement_lines')) {
            return null;
        }

        if (! empty($line->stock_movement_line_id)) {
            $found = DB::table('stock_movement_lines')
                ->where('id', (int) $line->stock_movement_line_id)
                ->first();

            if ($found) {
                return $found;
            }
        }

        $linked = DB::table('stock_movement_lines')
            ->where('source_type', 'purchase_receipt')
            ->where('source_line_type', 'purchase_receipt_line')
            ->where('source_line_id', $line->id)
            ->orderByDesc('id')
            ->first();

        if ($linked) {
            return $linked;
        }

        /*
         * Compatibilidad con recepciones legacy:
         * algunas líneas no tienen source_type/source_line_type ni stock_movement_line_id.
         * En ese caso buscamos dentro del movimiento de inventario de la recepción
         * por producto, variante y lote.
         */
        if (! empty($line->purchase_receipt_id) && Schema::hasTable('purchase_receipts')) {
            $receipt = DB::table('purchase_receipts')
                ->where('id', (int) $line->purchase_receipt_id)
                ->first();

            if ($receipt && ! empty($receipt->stock_movement_id)) {
                $query = DB::table('stock_movement_lines')
                    ->where('stock_movement_id', (int) $receipt->stock_movement_id)
                    ->where('product_id', (int) $line->product_id);

                if (! empty($line->product_variant_id)) {
                    $query->where('product_variant_id', (int) $line->product_variant_id);
                } else {
                    $query->whereNull('product_variant_id');
                }

                if (! empty($line->lot_id)) {
                    $query->where('lot_id', (int) $line->lot_id);
                }

                $fallback = $query->orderByDesc('id')->first();

                if ($fallback) {
                    return $fallback;
                }
            }
        }

        return null;
    }

    protected function availableSerialRowsForReceiptLine(object $receipt, object $line, object $original): array
    {
        if (! Schema::hasTable('stock_serial_numbers')) {
            return [];
        }

        $query = DB::table('stock_serial_numbers')
            ->where('company_id', $receipt->company_id)
            ->where('product_id', $line->product_id)
            ->where('status', 'available');

        if (! empty($line->product_variant_id)) {
            $query->where('product_variant_id', $line->product_variant_id);
        } else {
            $query->whereNull('product_variant_id');
        }

        if (Schema::hasColumn('stock_serial_numbers', 'purchase_receipt_id')) {
            $query->where('purchase_receipt_id', $receipt->id);
        }

        if (! empty($original->id) && Schema::hasColumn('stock_serial_numbers', 'stock_movement_line_id')) {
            $query->where('stock_movement_line_id', $original->id);
        }

        if (Schema::hasColumn('stock_serial_numbers', 'current_warehouse_id')) {
            $query->where('current_warehouse_id', $receipt->warehouse_id);
        }

        if (Schema::hasColumn('stock_serial_numbers', 'current_location_id')) {
            $query->where('current_location_id', $this->destinationStockLocationId($receipt));
        }

        if (Schema::hasColumn('purchase_return_lines', 'stock_serial_number_id')) {
            $returnedSerialIds = DB::table('purchase_return_lines as prl')
                ->join('purchase_returns as pr', 'pr.id', '=', 'prl.purchase_return_id')
                ->where('pr.status', 'done')
                ->whereNotNull('prl.stock_serial_number_id')
                ->pluck('prl.stock_serial_number_id')
                ->all();

            if ($returnedSerialIds) {
                $query->whereNotIn('id', $returnedSerialIds);
            }
        }

        return $query->orderBy('id')->get()->all();
    }

    protected function availableQuantQuantity(object $receipt, object $line, ?int $lotId, int $locationId): float
    {
        $quant = $this->stockQuant($receipt, $line, $lotId, $locationId);

        return $this->decimal($quant->quantity ?? 0);
    }

    protected function decreaseStockQuant(object $receipt, object $line, int $locationId, float $quantity, ?int $lotId): void
    {
        $quant = $this->stockQuant($receipt, $line, $lotId, $locationId, true);

        if (! $quant) {
            throw new RuntimeException('No existe existencia disponible para ' . ($line->product_label ?? ('producto #' . $line->product_id)) . '.');
        }

        $current = $this->decimal($quant->quantity ?? 0);

        if ($current + 0.000001 < $quantity) {
            throw new RuntimeException('La existencia disponible es menor a la cantidad a devolver para ' . ($line->product_label ?? ('producto #' . $line->product_id)) . '.');
        }

        DB::table('stock_quants')
            ->where('id', $quant->id)
            ->update($this->filterTableColumns('stock_quants', [
                'quantity' => $this->decimal($current - $quantity),
                'updated_at' => now(),
            ]));
    }

    protected function stockQuant(object $receipt, object $line, ?int $lotId, int $locationId, bool $lock = false): ?object
    {
        $query = DB::table('stock_quants')
            ->where('company_id', $receipt->company_id)
            ->where('warehouse_id', $receipt->warehouse_id)
            ->where('location_id', $locationId)
            ->where('product_id', $line->product_id);

        if (! empty($line->product_variant_id)) {
            $query->where('product_variant_id', $line->product_variant_id);
        } else {
            $query->whereNull('product_variant_id');
        }

        if ($lotId) {
            $query->where('lot_id', $lotId);
        } else {
            $query->whereNull('lot_id');
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query
            ->orderByDesc('quantity')
            ->orderByDesc('id')
            ->first();
    }

    protected function markSerialReturnedToSupplier(?int $serialId, int $movementLineId, int $purchaseReturnId, int $purchaseReturnLineId): void
    {
        if (! $serialId || ! Schema::hasTable('stock_serial_numbers')) {
            return;
        }

        $serial = DB::table('stock_serial_numbers')
            ->where('id', $serialId)
            ->lockForUpdate()
            ->first();

        if (! $serial) {
            throw new RuntimeException('No se encontró la serie a devolver al proveedor: #' . $serialId);
        }

        if ((string) ($serial->status ?? '') !== 'available') {
            throw new RuntimeException('La serie ' . ($serial->serial_number ?? ('#' . $serialId)) . ' no está disponible para devolver al proveedor.');
        }

        $updates = [
            'status' => 'returned_to_supplier',
            'current_warehouse_id' => null,
            'current_location_id' => null,
            'out_stock_movement_line_id' => $movementLineId,
            'out_source_type' => 'purchase_return',
            'out_source_id' => $purchaseReturnId,
            'out_source_line_type' => 'purchase_return_line',
            'out_source_line_id' => $purchaseReturnLineId,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('stock_serial_numbers', 'metadata')) {
            $metadata = [];

            if (! empty($serial->metadata)) {
                $decoded = json_decode((string) $serial->metadata, true);
                $metadata = is_array($decoded) ? $decoded : [];
            }

            $metadata['last_purchase_return'] = [
                'source_type' => 'purchase_return',
                'source_id' => $purchaseReturnId,
                'source_line_type' => 'purchase_return_line',
                'source_line_id' => $purchaseReturnLineId,
                'stock_movement_line_id' => $movementLineId,
                'returned_to_supplier_at' => now()->toDateTimeString(),
                'returned_by' => auth()->id(),
            ];

            $updates['metadata'] = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        DB::table('stock_serial_numbers')
            ->where('id', $serialId)
            ->update($this->filterTableColumns('stock_serial_numbers', $updates));
    }

    protected function destinationStockLocationId(object $receipt): ?int
    {
        $receiptLocationId = (int) ($receipt->location_id ?? 0);

        if ($receiptLocationId > 0 && Schema::hasTable('stock_locations')) {
            $exists = DB::table('stock_locations')->where('id', $receiptLocationId)->exists();

            if ($exists) {
                return $receiptLocationId;
            }
        }

        $movement = $this->receiptMovement($receipt);

        if ($movement && ! empty($movement->destination_location_id)) {
            return (int) $movement->destination_location_id;
        }

        return null;
    }

    protected function supplierLocationId(object $receipt): ?int
    {
        $movement = $this->receiptMovement($receipt);

        if ($movement && ! empty($movement->source_location_id)) {
            return (int) $movement->source_location_id;
        }

        if (! Schema::hasTable('stock_locations')) {
            return null;
        }

        $query = DB::table('stock_locations')
            ->where(function ($q): void {
                foreach (['usage', 'type', 'location_type', 'code', 'name'] as $column) {
                    if (Schema::hasColumn('stock_locations', $column)) {
                        $q->orWhere($column, 'ilike', '%proveedor%')
                            ->orWhere($column, 'ilike', '%supplier%');
                    }
                }
            });

        if (Schema::hasColumn('stock_locations', 'company_id') && ! empty($receipt->company_id)) {
            $query->where(function ($q) use ($receipt): void {
                $q->where('company_id', $receipt->company_id)
                    ->orWhereNull('company_id');
            });
        }

        return $query->value('id') ?: null;
    }

    protected function receiptMovement(object $receipt): ?object
    {
        if (! Schema::hasTable('stock_movements')) {
            return null;
        }

        if (! empty($receipt->stock_movement_id)) {
            $found = DB::table('stock_movements')
                ->where('id', (int) $receipt->stock_movement_id)
                ->first();

            if ($found) {
                return $found;
            }
        }

        return DB::table('stock_movements')
            ->where('origin_document', 'purchase_receipt:' . ($receipt->number ?? ''))
            ->orderByDesc('id')
            ->first();
    }

    protected function purchaseReturnOperationTypeId(object $receipt, int $sourceLocationId, ?int $destinationLocationId): ?int
    {
        if (! Schema::hasTable('stock_operation_types')) {
            return null;
        }

        $existing = DB::table('stock_operation_types')
            ->where('company_id', $receipt->company_id)
            ->where('warehouse_id', $receipt->warehouse_id)
            ->where('code', 'DEV_PROV')
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('stock_operation_types')->insertGetId($this->filterTableColumns('stock_operation_types', [
            'company_id' => $receipt->company_id,
            'warehouse_id' => $receipt->warehouse_id,
            'code' => 'DEV_PROV',
            'name' => 'Salida por devolución a proveedor',
            'operation_kind' => 'delivery',
            'source_location_id' => $sourceLocationId,
            'destination_location_id' => $destinationLocationId,
            'reference_prefix' => 'DEVP',
            'next_number' => 1,
            'sequence' => 96,
            'description' => 'Salida automática de inventario por devolución a proveedor desde una recepción de compra.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function nextPurchaseReturnNumber(int $companyId): string
    {
        $prefix = 'DEV-PROV-' . now()->format('Ymd') . '-';

        $last = DB::table('purchase_returns')
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($last && preg_match('/(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    protected function lotMetadata(object $receipt, object $line, int $lotId): array
    {
        $lot = Schema::hasTable('stock_lots')
            ? DB::table('stock_lots')->where('id', $lotId)->first()
            : null;

        return [
            'stock_lot_id' => $lotId,
            'lot_number' => $lot->lot_number ?? $line->lot_number ?? null,
            'source_type' => 'purchase_return',
            'purchase_receipt_id' => (int) $receipt->id,
            'purchase_receipt_line_id' => (int) $line->id,
        ];
    }

    protected function serialMetadata(object $receipt, object $line, int $serialId): array
    {
        $serial = Schema::hasTable('stock_serial_numbers')
            ? DB::table('stock_serial_numbers')->where('id', $serialId)->first()
            : null;

        return [
            'stock_serial_number_id' => $serialId,
            'serial_number' => $serial->serial_number ?? null,
            'source_type' => 'purchase_return',
            'purchase_receipt_id' => (int) $receipt->id,
            'purchase_receipt_line_id' => (int) $line->id,
        ];
    }

    protected function filterTableColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return $data;
        }

        $columns = Schema::getColumnListing($table);

        return array_intersect_key($data, array_flip($columns));
    }

    protected function decimal(mixed $value): float
    {
        return round((float) $value, 6);
    }
}
