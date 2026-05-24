<?php

namespace App\Support;

use App\Support\Cxp\AccountPayableFromPurchaseReceiptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PurchaseReceiptInventoryPoster
{
    public function post(int $purchaseReceiptId): ?int
    {
        if ($purchaseReceiptId <= 0) {
            return null;
        }

        return DB::transaction(function () use ($purchaseReceiptId): ?int {
            $receipt = DB::table('purchase_receipts')
                ->where('id', $purchaseReceiptId)
                ->lockForUpdate()
                ->first();

            if (! $receipt) {
                throw new RuntimeException('No se encontró la recepción de compra.');
            }

            if (! Schema::hasTable('stock_movements')) {
                throw new RuntimeException('No existe la tabla stock_movements.');
            }

            if (! Schema::hasTable('stock_movement_lines')) {
                throw new RuntimeException('No existe la tabla stock_movement_lines.');
            }

            if (! Schema::hasTable('stock_quants')) {
                throw new RuntimeException('No existe la tabla stock_quants.');
            }

            $movementId = (int) ($receipt->stock_movement_id ?? 0);

            if ($movementId <= 0) {
                $existingMovement = DB::table('stock_movements')
                    ->where('reference', $receipt->number)
                    ->orWhere('origin_document', 'purchase_receipt:' . $receipt->number)
                    ->first();

                $movementId = $existingMovement
                    ? (int) $existingMovement->id
                    : $this->insertStockMovement($receipt);
            } else {
                $this->repairStockMovementHeader($movementId, $receipt);
            }

            $this->syncMovementLinesAndQuants($receipt, $movementId);
            $this->markReceiptPosted($receipt, $movementId);

            app(AccountPayableFromPurchaseReceiptService::class)
                ->createFromReceipt((int) $receipt->id, auth()->id());

            return $movementId;
        });
    }

    protected function syncMovementLinesAndQuants(object $receipt, int $movementId): void
    {
        $lines = DB::table('purchase_receipt_lines')
            ->where('purchase_receipt_id', $receipt->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($lines as $line) {
            $quantity = (float) ($line->received_base_quantity ?? $line->received_quantity ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            $movementLineId = (int) ($line->stock_movement_line_id ?? 0);

            if ($movementLineId > 0 && DB::table('stock_movement_lines')->where('id', $movementLineId)->exists()) {
                $this->updateStockMovementLine($movementLineId, $line, $quantity);
            } else {
                $movementLineId = $this->insertStockMovementLine($movementId, $line, $quantity);
            }

            /*
             * Si esta línea ya fue posteada a stock_quants con la nueva bandera,
             * no volvemos a sumar para evitar duplicar existencia.
             */
            if (empty($line->stock_quant_posted_at)) {
                $this->increaseStockQuant($receipt, $line, $quantity);
                $this->createSerialNumbersForReceiptLine($receipt, $line, $movementLineId);
            }

            $this->markReceiptLinePosted($line, $movementLineId);
            $this->updateProductCosts($line);
        }
    }

    protected function insertStockMovement(object $receipt): int
    {
        $columns = Schema::getColumnListing('stock_movements');

        $order = Schema::hasTable('purchase_orders')
            ? DB::table('purchase_orders')->where('id', $receipt->purchase_order_id)->first()
            : null;

        $sourceLocationId = $this->sourceSupplierLocationId($receipt);
        $destinationLocationId = $this->destinationStockLocationId($receipt);

        $data = [];

        $this->set($data, $columns, 'company_id', $receipt->company_id ?? null);
        $this->set($data, $columns, 'warehouse_id', $receipt->warehouse_id ?? null);
        $this->set($data, $columns, 'stock_operation_type_id', $this->receiptOperationTypeId((int) ($receipt->company_id ?? 0), (int) ($receipt->warehouse_id ?? 0)));
        $this->set($data, $columns, 'source_location_id', $sourceLocationId);
        $this->set($data, $columns, 'destination_location_id', $destinationLocationId);
        $this->set($data, $columns, 'reference', $receipt->number);
        $this->set($data, $columns, 'movement_at', $receipt->received_at ?? now());
        $this->set($data, $columns, 'status', 'done');
        $this->set($data, $columns, 'origin_document', 'purchase_receipt:' . $receipt->number);
        $this->set($data, $columns, 'contact_id', $order->supplier_contact_id ?? null);
        $this->set($data, $columns, 'notes', 'Entrada generada desde recepción de OC ' . $receipt->number);
        $this->set($data, $columns, 'created_by', auth()->id() ?: ($receipt->received_by_user_id ?? null));
        $this->set($data, $columns, 'confirmed_by', auth()->id() ?: ($receipt->received_by_user_id ?? null));
        $this->set($data, $columns, 'confirmed_at', now());
        $this->set($data, $columns, 'created_at', now());
        $this->set($data, $columns, 'updated_at', now());

        return DB::table('stock_movements')->insertGetId($data);
    }

    protected function repairStockMovementHeader(int $movementId, object $receipt): void
    {
        $columns = Schema::getColumnListing('stock_movements');

        $updates = [];

        $this->set($updates, $columns, 'warehouse_id', $receipt->warehouse_id ?? null);
        $this->set($updates, $columns, 'source_location_id', $this->sourceSupplierLocationId($receipt));
        $this->set($updates, $columns, 'destination_location_id', $this->destinationStockLocationId($receipt));
        $this->set($updates, $columns, 'status', 'done');
        $this->set($updates, $columns, 'updated_at', now());

        if ($updates) {
            DB::table('stock_movements')
                ->where('id', $movementId)
                ->update($updates);
        }
    }

    protected function insertStockMovementLine(int $movementId, object $line, float $quantity): int
    {
        $columns = Schema::getColumnListing('stock_movement_lines');

        $data = [];

        $this->set($data, $columns, 'stock_movement_id', $movementId);
        $this->set($data, $columns, 'product_id', $line->product_id ?? null);
        $this->set($data, $columns, 'product_variant_id', $line->product_variant_id ?? null);
        $this->set($data, $columns, 'lot_id', ! empty($line->lot_id) ? (int) $line->lot_id : null);
        $this->set($data, $columns, 'requested_quantity', $quantity);
        $this->set($data, $columns, 'done_quantity', $quantity);
        $this->set($data, $columns, 'unit_cost', $line->unit_cost_without_tax ?? 0);
        $this->set($data, $columns, 'notes', $line->product_label ?? null);
        $this->set($data, $columns, 'created_at', now());
        $this->set($data, $columns, 'updated_at', now());

        $movementLineId = DB::table('stock_movement_lines')->insertGetId($data);

        $this->applyCostToMovementLine($movementLineId, 'purchase_receipt.unit_cost_without_tax');

        return $movementLineId;
    }

    protected function updateStockMovementLine(int $movementLineId, object $line, float $quantity): void
    {
        $columns = Schema::getColumnListing('stock_movement_lines');

        $updates = [];

        $this->set($updates, $columns, 'product_id', $line->product_id ?? null);
        $this->set($updates, $columns, 'product_variant_id', $line->product_variant_id ?? null);
        $this->set($updates, $columns, 'lot_id', ! empty($line->lot_id) ? (int) $line->lot_id : null);
        $this->set($updates, $columns, 'requested_quantity', $quantity);
        $this->set($updates, $columns, 'done_quantity', $quantity);
        $this->set($updates, $columns, 'unit_cost', $line->unit_cost_without_tax ?? 0);
        $this->set($updates, $columns, 'notes', $line->product_label ?? null);
        $this->set($updates, $columns, 'updated_at', now());

        DB::table('stock_movement_lines')
            ->where('id', $movementLineId)
            ->update($updates);

        $this->applyCostToMovementLine($movementLineId, 'purchase_receipt.unit_cost_without_tax');
    }

    protected function increaseStockQuant(object $receipt, object $line, float $quantity): void
    {
        $columns = Schema::getColumnListing('stock_quants');

        $companyId = (int) ($receipt->company_id ?? 0);
        $warehouseId = (int) ($receipt->warehouse_id ?? 0);
        $locationId = $this->destinationStockLocationId($receipt);
        $productId = (int) ($line->product_id ?? 0);
        $variantId = (int) ($line->product_variant_id ?? 0);
        $lotId = (int) ($line->lot_id ?? 0);
        $unitCost = (float) ($line->unit_cost_without_tax ?? 0);

        if ($productId <= 0) {
            throw new RuntimeException('No se puede aumentar existencia de una línea sin producto interno: ' . ($line->product_label ?? 'producto'));
        }

        if (! $locationId) {
            throw new RuntimeException('No se encontró ubicación de inventario destino para la recepción ' . ($receipt->number ?? ''));
        }

        $query = DB::table('stock_quants')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId);

        $variantId > 0
            ? $query->where('product_variant_id', $variantId)
            : $query->whereNull('product_variant_id');

        if (in_array('lot_id', $columns, true)) {
            $lotId > 0
                ? $query->where('lot_id', $lotId)
                : $query->whereNull('lot_id');
        }

        $quant = $query->lockForUpdate()->first();

        if (! $quant) {
            $data = [];

            $this->set($data, $columns, 'company_id', $companyId);
            $this->set($data, $columns, 'warehouse_id', $warehouseId);
            $this->set($data, $columns, 'location_id', $locationId);
            $this->set($data, $columns, 'product_id', $productId);
            $this->set($data, $columns, 'product_variant_id', $variantId ?: null);
            $this->set($data, $columns, 'lot_id', $lotId > 0 ? $lotId : null);
            $this->set($data, $columns, 'quantity', round($quantity, 6));
            $this->set($data, $columns, 'reserved_quantity', 0);
            $this->set($data, $columns, 'average_cost', round($unitCost, 6));
            $this->set($data, $columns, 'created_at', now());
            $this->set($data, $columns, 'updated_at', now());

            DB::table('stock_quants')->insert($data);

            return;
        }

        $currentQuantity = (float) ($quant->quantity ?? 0);
        $currentCost = (float) ($quant->average_cost ?? 0);
        $newQuantity = round($currentQuantity + $quantity, 6);

        $newAverageCost = $newQuantity > 0
            ? round((($currentQuantity * $currentCost) + ($quantity * $unitCost)) / $newQuantity, 6)
            : $unitCost;

        $updates = [];

        $this->set($updates, $columns, 'quantity', $newQuantity);
        $this->set($updates, $columns, 'average_cost', $newAverageCost);
        $this->set($updates, $columns, 'updated_at', now());

        DB::table('stock_quants')
            ->where('id', $quant->id)
            ->update($updates);
    }

    protected function createSerialNumbersForReceiptLine(object $receipt, object $line, int $movementLineId): void
    {
        if (! Schema::hasTable('stock_serial_numbers')) {
            return;
        }

        if ((string) ($line->tracking_type ?? 'none') !== 'serial') {
            return;
        }

        $serials = $this->serialNumbersFromReceiptLine($line);

        if (! $serials) {
            return;
        }

        $columns = Schema::getColumnListing('stock_serial_numbers');
        $companyId = (int) ($receipt->company_id ?? 0);
        $productId = (int) ($line->product_id ?? 0);
        $variantId = (int) ($line->product_variant_id ?? 0);
        $warehouseId = (int) ($receipt->warehouse_id ?? 0);
        $locationId = $this->destinationStockLocationId($receipt);

        $motorNumbers = $this->motorNumbersFromReceiptLine($line);
        $serialImportRows = $this->serialImportRowsFromReceiptLine($line);

        foreach ($serials as $serialIndex => $serial) {
            $serialImportRow = $serialImportRows[$serialIndex] ?? [];
            $query = DB::table('stock_serial_numbers')
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->whereRaw('LOWER(serial_number) = LOWER(?)', [$serial]);

            $variantId > 0
                ? $query->where('product_variant_id', $variantId)
                : $query->whereNull('product_variant_id');

            $existing = $query->lockForUpdate()->first();

            $data = [];

            $this->set($data, $columns, 'company_id', $companyId);
            $this->set($data, $columns, 'product_id', $productId);
            $this->set($data, $columns, 'product_variant_id', $variantId ?: null);
            $this->set($data, $columns, 'lot_id', ! empty($line->lot_id) ? (int) $line->lot_id : null);
            $this->set($data, $columns, 'serial_number', $serial);
            $this->set($data, $columns, 'motor_number', $this->serialImportValue($serialImportRow, $line, 'motor_number', $this->motorNumberForSerial($line, $motorNumbers, $serialIndex)));
            $this->set($data, $columns, 'customs_entry_number', $this->serialImportValue($serialImportRow, $line, 'customs_entry_number'));
            $this->set($data, $columns, 'customs_entry_date', $this->serialImportValue($serialImportRow, $line, 'customs_entry_date'));
            $this->set($data, $columns, 'customs_office', $this->serialImportValue($serialImportRow, $line, 'customs_office'));
            $this->set($data, $columns, 'imported_model', $this->serialImportValue($serialImportRow, $line, 'imported_model'));
            $this->set($data, $columns, 'imported_color', $this->serialImportValue($serialImportRow, $line, 'imported_color'));
            $this->set($data, $columns, 'import_document_reference', $this->serialImportValue($serialImportRow, $line, 'import_document_reference'));
            $this->set($data, $columns, 'current_warehouse_id', $warehouseId ?: null);
            $this->set($data, $columns, 'current_location_id', $locationId ?: null);
            $this->set($data, $columns, 'status', 'available');
            $this->set($data, $columns, 'source_type', 'purchase_receipt');
            $this->set($data, $columns, 'source_id', $receipt->id);
            $this->set($data, $columns, 'purchase_order_id', $receipt->purchase_order_id ?? null);
            $this->set($data, $columns, 'purchase_receipt_id', $receipt->id);
            $this->set($data, $columns, 'stock_movement_line_id', $movementLineId);
            $this->set($data, $columns, 'metadata', json_encode([
                'receipt_number' => $receipt->number ?? null,
                'receipt_line_id' => $line->id ?? null,
                'created_from' => 'v5.53.3b4',
            ]));
            $this->set($data, $columns, 'updated_at', now());

            if ($existing) {
                DB::table('stock_serial_numbers')
                    ->where('id', $existing->id)
                    ->update($data);

                continue;
            }

            $this->set($data, $columns, 'created_at', now());

            DB::table('stock_serial_numbers')->insert($data);
        }
    }



    protected function serialImportRowsFromReceiptLine(object $line): array
    {
        $raw = $line->serial_import_rows ?? null;

        if (is_array($raw)) {
            $rows = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $rows = is_array($decoded) ? $decoded : [];
        } else {
            $rows = [];
        }

        return array_values(array_filter($rows, fn ($row): bool => is_array($row)));
    }

    protected function serialImportValue(array $row, object $line, string $fieldName, mixed $fallback = null): mixed
    {
        $value = $row[$fieldName] ?? null;

        if ($value === null || trim((string) $value) === '') {
            $value = $fallback;
        }

        if ($value === null || trim((string) $value) === '') {
            $value = $line->{$fieldName} ?? null;
        }

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $value;
    }


    protected function motorNumbersFromReceiptLine(object $line): array
    {
        $raw = $line->motor_number ?? null;

        if (is_array($raw)) {
            $values = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                $values = $decoded;
            } else {
                $values = preg_split('/[\r\n,;]+/', $raw) ?: [];
            }
        } else {
            $values = [];
        }

        return array_values(array_filter(array_map(fn ($value) => trim((string) $value), $values)));
    }

    protected function motorNumberForSerial(object $line, array $motorNumbers, int $serialIndex): ?string
    {
        if (count($motorNumbers) > 1) {
            return $motorNumbers[$serialIndex] ?? null;
        }

        if (count($motorNumbers) === 1) {
            return $motorNumbers[0];
        }

        $value = trim((string) ($line->motor_number ?? ''));

        return $value === '' ? null : $value;
    }


    protected function serialNumbersFromReceiptLine(object $line): array
    {
        $raw = $line->serial_numbers ?? null;

        if (is_array($raw)) {
            $values = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                $values = $decoded;
            } else {
                $values = preg_split('/[\r\n,;]+/', $raw) ?: [];
            }
        } else {
            $values = [];
        }

        $serials = [];
        $seen = [];

        foreach ($values as $value) {
            $serial = trim((string) $value);

            if ($serial === '') {
                continue;
            }

            $key = mb_strtolower($serial);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $serials[] = $serial;
        }

        return $serials;
    }

    protected function updateProductCosts(object $line): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $columns = Schema::getColumnListing('products');

        $targetProductId = (int) ($line->product_variant_id ?? 0);

        if ($targetProductId <= 0) {
            $targetProductId = (int) ($line->product_id ?? 0);
        }

        if ($targetProductId <= 0) {
            return;
        }

        $updates = [];

        $this->set($updates, $columns, 'last_purchase_cost', (float) ($line->unit_cost_without_tax ?? 0));
        $this->set($updates, $columns, 'last_purchase_at', now());
        $this->set($updates, $columns, 'average_cost_without_tax', $this->averageCostForProduct($targetProductId));
        $this->set($updates, $columns, 'updated_at', now());

        if ($updates) {
            DB::table('products')
                ->where('id', $targetProductId)
                ->update($updates);
        }
    }

    protected function averageCostForProduct(int $productOrVariantId): float
    {
        if (! Schema::hasTable('stock_quants')) {
            return 0.0;
        }

        $rows = DB::table('stock_quants')
            ->where(function ($q) use ($productOrVariantId): void {
                $q->where('product_id', $productOrVariantId)
                    ->orWhere('product_variant_id', $productOrVariantId);
            })
            ->get();

        $qty = 0.0;
        $total = 0.0;

        foreach ($rows as $row) {
            $rowQty = (float) ($row->quantity ?? 0);
            $rowAvg = (float) ($row->average_cost ?? 0);

            $qty += $rowQty;
            $total += $rowQty * $rowAvg;
        }

        return $qty > 0 ? round($total / $qty, 6) : 0.0;
    }

    protected function markReceiptPosted(object $receipt, int $movementId): void
    {
        $columns = Schema::getColumnListing('purchase_receipts');

        $updates = [];

        $this->set($updates, $columns, 'stock_movement_id', $movementId);
        $this->set($updates, $columns, 'inventory_posted_at', now());
        $this->set($updates, $columns, 'stock_quant_posted_at', now());
        $this->set($updates, $columns, 'updated_at', now());

        if ($updates) {
            DB::table('purchase_receipts')
                ->where('id', $receipt->id)
                ->update($updates);
        }
    }

    protected function markReceiptLinePosted(object $line, int $movementLineId): void
    {
        $columns = Schema::getColumnListing('purchase_receipt_lines');

        $updates = [];

        $this->set($updates, $columns, 'stock_movement_line_id', $movementLineId);
        $this->set($updates, $columns, 'inventory_posted_at', now());
        $this->set($updates, $columns, 'stock_quant_posted_at', now());
        $this->set($updates, $columns, 'updated_at', now());

        if ($updates) {
            DB::table('purchase_receipt_lines')
                ->where('id', $line->id)
                ->update($updates);
        }
    }

    protected function destinationStockLocationId(object $receipt): ?int
    {
        if (! Schema::hasTable('stock_locations')) {
            return null;
        }

        /*
         * En esta instalación la pantalla Existencias usa stock_locations.
         * Si purchase_receipts.location_id coincide con stock_locations.id,
         * usamos esa ubicación para que aparezca donde el usuario recibió.
         */
        $receiptLocationId = (int) ($receipt->location_id ?? 0);

        if ($receiptLocationId > 0) {
            $exists = DB::table('stock_locations')
                ->where('id', $receiptLocationId)
                ->exists();

            if ($exists) {
                return $receiptLocationId;
            }
        }

        $query = DB::table('stock_locations')
            ->where('warehouse_id', $receipt->warehouse_id)
            ->where(function ($q): void {
                $q->whereRaw('UPPER(code) IN (?, ?, ?)', ['STOCK', 'CDF', 'CEDIS'])
                    ->orWhere('name', 'like', '%CEDIS%')
                    ->orWhere('name', 'like', '%Stock%');
            });

        if (Schema::hasColumn('stock_locations', 'company_id') && ! empty($receipt->company_id)) {
            $query->where('company_id', $receipt->company_id);
        }

        $id = $query->orderBy('id')->value('id');

        if ($id) {
            return (int) $id;
        }

        $fallback = DB::table('stock_locations')
            ->where('warehouse_id', $receipt->warehouse_id)
            ->orderBy('id')
            ->value('id');

        return $fallback ? (int) $fallback : null;
    }

    protected function sourceSupplierLocationId(object $receipt): ?int
    {
        if (! Schema::hasTable('stock_locations')) {
            return null;
        }

        $query = DB::table('stock_locations')
            ->where(function ($q): void {
                $q->whereRaw('UPPER(code) IN (?, ?)', ['PROVEEDORES', 'SUPPLIERS'])
                    ->orWhereRaw('UPPER(name) IN (?, ?)', ['PROVEEDORES', 'SUPPLIERS']);
            });

        if (Schema::hasColumn('stock_locations', 'company_id') && ! empty($receipt->company_id)) {
            $query->where('company_id', $receipt->company_id);
        }

        $id = $query->value('id');

        return $id ? (int) $id : null;
    }

    protected function receiptOperationTypeId(int $companyId, int $warehouseId): ?int
    {
        if (! Schema::hasTable('stock_operation_types')) {
            return null;
        }

        $columns = Schema::getColumnListing('stock_operation_types');

        $query = DB::table('stock_operation_types');

        if ($companyId > 0 && in_array('company_id', $columns, true)) {
            $query->where('company_id', $companyId);
        }

        if ($warehouseId > 0 && in_array('warehouse_id', $columns, true)) {
            $query->where(function ($q) use ($warehouseId): void {
                $q->where('warehouse_id', $warehouseId)
                    ->orWhereNull('warehouse_id');
            });
        }

        if (in_array('code', $columns, true)) {
            $found = (clone $query)
                ->whereRaw('UPPER(code) IN (?, ?, ?, ?)', ['RECEIPT', 'IN', 'ENTRADA', 'COMPRA'])
                ->orderByDesc('warehouse_id')
                ->value('id');

            if ($found) {
                return (int) $found;
            }
        }

        if (in_array('operation_kind', $columns, true)) {
            $found = (clone $query)
                ->whereIn('operation_kind', ['receipt', 'in', 'incoming'])
                ->orderByDesc('warehouse_id')
                ->value('id');

            if ($found) {
                return (int) $found;
            }
        }

        return DB::table('stock_operation_types')->orderBy('id')->value('id');
    }

    protected function set(array &$array, array $columns, string $column, mixed $value): void
    {
        if (in_array($column, $columns, true)) {
            $array[$column] = $value;
        }
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
