<?php

namespace App\Support\Inventory;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryAsOfDateService
{
    public function rows(array $filters = []): Collection
    {
        if (! Schema::hasTable('stock_quants') || ! Schema::hasTable('stock_movement_lines') || ! Schema::hasTable('stock_movements')) {
            return collect();
        }

        $qtyColumn = $this->quantityColumn();

        if (! $qtyColumn) {
            return collect();
        }

        $cutoff = $this->cutoff($filters);
        $balances = [];

        $this->seedCurrentQuants($balances, $filters, $qtyColumn);
        $this->reverseMovementsAfterCutoff($balances, $filters, $cutoff);
        $this->reverseInterWarehouseTransfersAfterCutoff($balances, $filters, $cutoff);
        $this->reverseSameWarehouseTransfersAfterCutoff($balances, $filters, $cutoff);

        if (! empty($filters['show_zero'])) {
            $this->seedCatalogZeroProducts($balances, $filters);
        }

        $rows = collect(array_values($balances))
            ->map(function (array $row) use ($cutoff): object {
                $row['quantity_as_of'] = round((float) ($row['quantity_as_of'] ?? 0), 6);
                $row['cutoff_at'] = $cutoff->format('Y-m-d H:i:s');
                $row['quantity_label'] = number_format((float) $row['quantity_as_of'], 2);
                $row['product_label'] = trim(($row['product_name'] ?: 'Producto #' . $row['product_id']) . ($row['variant_name'] ? ' / ' . $row['variant_name'] : ''));
                $row['lot_label'] = $row['lot_number'] ?: 'Sin lote';

                return (object) $row;
            });

        if (empty($filters['show_zero'])) {
            $rows = $rows->filter(fn (object $row): bool => abs((float) $row->quantity_as_of) > 0.000001);
        }

        if (! empty($filters['only_negative'])) {
            $rows = $rows->filter(fn (object $row): bool => (float) $row->quantity_as_of < -0.000001);
        }

        return $rows
            ->sortBy([
                ['company_name', 'asc'],
                ['warehouse_name', 'asc'],
                ['location_name', 'asc'],
                ['product_name', 'asc'],
                ['variant_name', 'asc'],
                ['lot_number', 'asc'],
            ])
            ->values()
            ->take(min(5000, max(50, (int) ($filters['limit'] ?? 1000))));
    }

    public function summary(array $filters = []): array
    {
        $rows = $this->rows($filters);
        $cutoff = $this->cutoff($filters);

        return [
            'cutoff_at' => $cutoff->format('Y-m-d H:i:s'),
            'lines' => $rows->count(),
            'total_quantity' => round((float) $rows->sum('quantity_as_of'), 6),
            'positive_lines' => $rows->filter(fn (object $row): bool => (float) $row->quantity_as_of > 0.000001)->count(),
            'negative_lines' => $rows->filter(fn (object $row): bool => (float) $row->quantity_as_of < -0.000001)->count(),
            'zero_lines' => $rows->filter(fn (object $row): bool => abs((float) $row->quantity_as_of) <= 0.000001)->count(),
            'with_lot' => $rows->filter(fn (object $row): bool => ! empty($row->lot_id))->count(),
            'method' => 'Existencia actual menos movimientos posteriores al corte',
        ];
    }


    protected function seedCatalogZeroProducts(array &$balances, array $filters): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        if (! empty($filters['lot_id'])) {
            return;
        }

        $companyId = (int) ($filters['company_id'] ?? 0);

        if ($companyId <= 0) {
            return;
        }

        $context = $this->catalogZeroContext($filters);

        $query = DB::table('products as p')
            ->leftJoin('products as parent', 'parent.id', '=', 'p.parent_product_id')
            ->where('p.company_id', $companyId)
            ->select([
                'p.id',
                'p.company_id',
                'p.name',
                'p.parent_product_id',
                'p.is_variant',
                'p.variant_name',
                'p.variant_value',
                'parent.name as parent_name',
            ]);

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('p.is_active', true);
        }

        if (Schema::hasColumn('products', 'product_type')) {
            $query->whereIn('p.product_type', ['stockable', 'consumable']);
        }

        if (! empty($filters['product_variant_id'])) {
            $query->where('p.id', (int) $filters['product_variant_id']);
        } elseif (! empty($filters['product_id'])) {
            $productId = (int) $filters['product_id'];

            $query->where(function ($q) use ($productId): void {
                $q->where('p.id', $productId)
                    ->orWhere('p.parent_product_id', $productId);
            });
        }

        foreach ($query->orderBy('p.name')->limit(5000)->get() as $product) {
            $isVariant = (bool) ($product->is_variant ?? false) || ! empty($product->parent_product_id);

            $productId = $isVariant && ! empty($product->parent_product_id)
                ? (int) $product->parent_product_id
                : (int) $product->id;

            $variantId = $isVariant ? (int) $product->id : null;

            if ($this->catalogProductAlreadyRepresented($balances, $productId, $variantId, $filters)) {
                continue;
            }

            $productName = $isVariant
                ? (string) ($product->parent_name ?: $product->name)
                : (string) $product->name;

            $variantName = $isVariant
                ? (string) ($product->variant_name ?: $product->variant_value ?: $product->name)
                : null;

            $meta = [
                'company_id' => $companyId,
                'company_name' => $context['company_name'],
                'warehouse_id' => $context['warehouse_id'],
                'warehouse_name' => $context['warehouse_name'],
                'location_id' => $context['location_id'],
                'location_name' => $context['location_name'],
                'product_id' => $productId,
                'product_name' => $productName,
                'variant_id' => $variantId,
                'variant_name' => $variantName,
                'lot_id' => null,
                'lot_number' => null,
            ];

            $this->addBalance($balances, $this->keyFromMeta($meta), $meta, 0.0);
        }
    }

    protected function catalogProductAlreadyRepresented(array $balances, int $productId, ?int $variantId, array $filters): bool
    {
        $warehouseId = ! empty($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $locationId = ! empty($filters['location_id']) ? (int) $filters['location_id'] : null;

        foreach ($balances as $balance) {
            if ((int) ($balance['product_id'] ?? 0) !== $productId) {
                continue;
            }

            $balanceVariantId = ! empty($balance['variant_id']) ? (int) $balance['variant_id'] : null;

            if ($balanceVariantId !== $variantId) {
                continue;
            }

            if ($warehouseId !== null && (int) ($balance['warehouse_id'] ?? 0) !== $warehouseId) {
                continue;
            }

            if ($locationId !== null && (int) ($balance['location_id'] ?? 0) !== $locationId) {
                continue;
            }

            return true;
        }

        return false;
    }

    protected function catalogZeroContext(array $filters): array
    {
        $companyId = (int) ($filters['company_id'] ?? 0);
        $warehouseId = ! empty($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $locationId = ! empty($filters['location_id']) ? (int) $filters['location_id'] : null;

        if ($locationId && Schema::hasTable('stock_locations')) {
            $location = DB::table('stock_locations')->where('id', $locationId)->first();

            if ($location) {
                $warehouseId = $warehouseId ?: (isset($location->warehouse_id) ? (int) $location->warehouse_id : null);

                return [
                    'company_name' => $this->companyName($companyId),
                    'warehouse_id' => $warehouseId,
                    'warehouse_name' => $this->warehouseName($warehouseId),
                    'location_id' => $locationId,
                    'location_name' => (string) ($location->name ?? 'Ubicación #' . $locationId),
                ];
            }
        }

        if (! $warehouseId) {
            $warehouseId = $this->defaultWarehouseId($companyId);
        }

        if (! $locationId) {
            $locationId = $this->defaultLocationId($companyId, $warehouseId);
        }

        return [
            'company_name' => $this->companyName($companyId),
            'warehouse_id' => $warehouseId,
            'warehouse_name' => $this->warehouseName($warehouseId),
            'location_id' => $locationId,
            'location_name' => $this->locationName($locationId) ?: 'Sin ubicación',
        ];
    }

    protected function defaultWarehouseId(int $companyId): ?int
    {
        if ($companyId <= 0 || ! Schema::hasTable('warehouses')) {
            return null;
        }

        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'default_warehouse_id')) {
            $default = DB::table('companies')->where('id', $companyId)->value('default_warehouse_id');

            if ($default) {
                return (int) $default;
            }
        }

        $query = DB::table('warehouses');

        if (Schema::hasColumn('warehouses', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return ($id = $query->orderBy('id')->value('id')) ? (int) $id : null;
    }

    protected function defaultLocationId(int $companyId, ?int $warehouseId): ?int
    {
        if ($companyId <= 0 || ! Schema::hasTable('stock_locations')) {
            return null;
        }

        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'default_location_id')) {
            $default = DB::table('companies')->where('id', $companyId)->value('default_location_id');

            if ($default) {
                return (int) $default;
            }
        }

        $query = DB::table('stock_locations');

        if (Schema::hasColumn('stock_locations', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if ($warehouseId && Schema::hasColumn('stock_locations', 'warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        return ($id = $query->orderBy('id')->value('id')) ? (int) $id : null;
    }

    protected function companyName(int $companyId): ?string
    {
        if ($companyId <= 0 || ! Schema::hasTable('companies')) {
            return null;
        }

        return DB::table('companies')->where('id', $companyId)->value('name');
    }

    protected function warehouseName(?int $warehouseId): ?string
    {
        if (! $warehouseId || ! Schema::hasTable('warehouses')) {
            return 'Sin almacén';
        }

        return DB::table('warehouses')->where('id', $warehouseId)->value('name') ?: 'Almacén #' . $warehouseId;
    }

    protected function seedCurrentQuants(array &$balances, array $filters, string $qtyColumn): void
    {
        $query = DB::table('stock_quants as q')
            ->leftJoin('companies as c', 'c.id', '=', 'q.company_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'q.warehouse_id')
            ->leftJoin('stock_locations as loc', 'loc.id', '=', 'q.location_id')
            ->leftJoin('products as p', 'p.id', '=', 'q.product_id')
            ->leftJoin('products as v', 'v.id', '=', 'q.product_variant_id')
            ->leftJoin('stock_lots as lot', 'lot.id', '=', 'q.lot_id')
            ->select([
                'q.company_id',
                'c.name as company_name',
                'q.warehouse_id',
                'w.name as warehouse_name',
                'q.location_id',
                'loc.name as location_name',
                'q.product_id',
                'p.name as product_name',
                'q.product_variant_id',
                'v.name as variant_name',
                'q.lot_id',
                'lot.lot_number',
                DB::raw("sum(q.{$qtyColumn}) as quantity"),
            ])
            ->groupBy([
                'q.company_id',
                'c.name',
                'q.warehouse_id',
                'w.name',
                'q.location_id',
                'loc.name',
                'q.product_id',
                'p.name',
                'q.product_variant_id',
                'v.name',
                'q.lot_id',
                'lot.lot_number',
            ]);

        $this->applyBaseFilters($query, $filters, 'q');

        foreach ($query->get() as $row) {
            $this->addBalance($balances, $this->key($row), [
                'company_id' => $row->company_id,
                'company_name' => $row->company_name,
                'warehouse_id' => $row->warehouse_id,
                'warehouse_name' => $row->warehouse_name,
                'location_id' => $row->location_id,
                'location_name' => $row->location_name,
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'variant_id' => $row->product_variant_id,
                'variant_name' => $row->variant_name,
                'lot_id' => $row->lot_id,
                'lot_number' => $row->lot_number,
            ], (float) ($row->quantity ?? 0));
        }
    }

    protected function reverseMovementsAfterCutoff(array &$balances, array $filters, Carbon $cutoff): void
    {
        $query = DB::table('stock_movement_lines as l')
            ->leftJoin('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->leftJoin('stock_operation_types as ot', 'ot.id', '=', 'm.stock_operation_type_id')
            ->leftJoin('companies as c', 'c.id', '=', 'm.company_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'm.warehouse_id')
            ->leftJoin('warehouses as dw', 'dw.id', '=', 'm.destination_warehouse_id')
            ->leftJoin('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('products as v', 'v.id', '=', 'l.product_variant_id')
            ->leftJoin('stock_lots as lot', 'lot.id', '=', 'l.lot_id')
            ->where(DB::raw('coalesce(m.movement_at, m.created_at)'), '>', $cutoff->format('Y-m-d H:i:s'))
            ->select([
                'm.company_id',
                'c.name as company_name',
                'm.warehouse_id',
                'w.name as warehouse_name',
                'm.destination_warehouse_id',
                'dw.name as destination_warehouse_name',
                'm.transit_location_id',
                'm.source_location_id',
                'm.destination_location_id',
                'm.reference',
                'm.origin_document',
                'ot.name as operation_name',
                'ot.operation_kind',
                'l.product_id',
                'p.name as product_name',
                'l.product_variant_id',
                'v.name as variant_name',
                'l.lot_id',
                'lot.lot_number',
                'l.done_quantity',
                'l.source_type',
            ]);

        $this->applyMovementFilters($query, $filters);

        $query->where(function ($q): void {
            $q->whereNull('m.destination_warehouse_id')
                ->orWhere(function ($q): void {
                    $q->whereColumn(
                        'm.destination_warehouse_id',
                        '<>',
                        'm.warehouse_id'
                    )
                    ->whereNull('m.transit_location_id');
                });
        });

        foreach ($query->get() as $row) {
            $direction = $this->classifyDirection($row);
            $qty = abs((float) ($row->done_quantity ?? 0));

            if ($qty <= 0) {
                continue;
            }

            if ($direction === 'in') {
                $this->reverseInMovement($balances, $row, -1 * $qty);
            } elseif ($direction === 'out') {
                $this->reverseOutMovement($balances, $row, $qty);
            } elseif ($direction === 'transfer') {
                $this->reverseOutMovement($balances, $row, $qty);
                $this->reverseInMovement($balances, $row, -1 * $qty);
            }
        }
    }

    protected function reverseSameWarehouseTransfersAfterCutoff(
        array &$balances,
        array $filters,
        Carbon $cutoff
    ): void {
        $cutoffAt = $cutoff->format('Y-m-d H:i:s');

        $query = DB::table('stock_movement_lines as l')
            ->join('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->leftJoin('companies as c', 'c.id', '=', 'm.company_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'm.warehouse_id')
            ->leftJoin('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('products as v', 'v.id', '=', 'l.product_variant_id')
            ->leftJoin('stock_lots as lot', 'lot.id', '=', 'l.lot_id')
            ->whereNotNull('m.destination_warehouse_id')
            ->whereColumn('m.destination_warehouse_id', 'm.warehouse_id')
            ->whereNotNull('m.source_location_id')
            ->whereNotNull('m.destination_location_id')
            ->whereNotNull('m.received_at')
            ->where('m.received_at', '>', $cutoffAt)
            ->select([
                'm.id as stock_movement_id',
                'm.company_id',
                'c.name as company_name',
                'm.warehouse_id',
                'w.name as warehouse_name',
                'm.destination_warehouse_id',
                'm.source_location_id',
                'm.destination_location_id',
                'm.reference',
                'm.origin_document',
                'm.received_at',
                'l.id as stock_movement_line_id',
                'l.product_id',
                'p.name as product_name',
                'l.product_variant_id',
                'v.name as variant_name',
                'l.lot_id',
                'lot.lot_number',
                'l.done_quantity',
            ]);

        if (! empty($filters['company_id'])) {
            $query->where(
                'm.company_id',
                (int) $filters['company_id']
            );
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where(
                'm.warehouse_id',
                (int) $filters['warehouse_id']
            );
        }

        if (! empty($filters['location_id'])) {
            $locationId=(int) $filters['location_id'];

            $query->where(function ($q) use ($locationId): void {
                $q->where('m.source_location_id', $locationId)
                    ->orWhere('m.destination_location_id', $locationId);
            });
        }

        if (! empty($filters['product_id'])) {
            $query->where(
                'l.product_id',
                (int) $filters['product_id']
            );
        }

        if (! empty($filters['product_variant_id'])) {
            $query->where(
                'l.product_variant_id',
                (int) $filters['product_variant_id']
            );
        }

        if (! empty($filters['lot_id'])) {
            $query->where(
                'l.lot_id',
                (int) $filters['lot_id']
            );
        }

        foreach ($query
            ->orderByDesc('m.received_at')
            ->orderByDesc('m.id')
            ->get() as $row) {

            $qty=abs((float) ($row->done_quantity ?? 0));

            if ($qty <= 0) {
                continue;
            }

            /*
             * Estado actual contiene el traslado ya aplicado.
             * Para volver a antes de received_at:
             *
             * destino -qty
             * origen  +qty
             */

            $destinationLocationId=
                $row->destination_location_id ?: null;

            $destinationMeta=$this->movementMeta(
                $row,
                $destinationLocationId,
                $this->locationName($destinationLocationId),
                ! empty($row->warehouse_id)
                    ? (int) $row->warehouse_id
                    : null,
                $row->warehouse_name ?? null
            );

            if (
                $this->transferMetaMatchesFilters(
                    $destinationMeta,
                    $filters
                )
            ) {
                $this->addBalance(
                    $balances,
                    $this->keyFromMeta($destinationMeta),
                    $destinationMeta,
                    -1 * $qty
                );
            }

            $sourceLocationId=
                $row->source_location_id ?: null;

            $sourceMeta=$this->movementMeta(
                $row,
                $sourceLocationId,
                $this->locationName($sourceLocationId),
                ! empty($row->warehouse_id)
                    ? (int) $row->warehouse_id
                    : null,
                $row->warehouse_name ?? null
            );

            if (
                $this->transferMetaMatchesFilters(
                    $sourceMeta,
                    $filters
                )
            ) {
                $this->addBalance(
                    $balances,
                    $this->keyFromMeta($sourceMeta),
                    $sourceMeta,
                    $qty
                );
            }
        }
    }

    protected function reverseInterWarehouseTransfersAfterCutoff(
        array &$balances,
        array $filters,
        Carbon $cutoff
    ): void {
        if (
            ! Schema::hasTable('stock_movement_receipts')
            || ! Schema::hasTable('stock_movement_receipt_lines')
        ) {
            return;
        }

        $query = DB::table('stock_movement_lines as l')
            ->join('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->leftJoin('companies as c', 'c.id', '=', 'm.company_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'm.warehouse_id')
            ->leftJoin('warehouses as dw', 'dw.id', '=', 'm.destination_warehouse_id')
            ->leftJoin('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('products as v', 'v.id', '=', 'l.product_variant_id')
            ->leftJoin('stock_lots as lot', 'lot.id', '=', 'l.lot_id')
            ->whereNotNull('m.destination_warehouse_id')
            ->whereColumn('m.destination_warehouse_id', '<>', 'm.warehouse_id')
            ->whereNotNull('m.transit_location_id')
            ->whereNotNull('m.dispatched_at')
            ->select([
                'm.id as stock_movement_id',
                'm.company_id',
                'c.name as company_name',
                'm.warehouse_id',
                'w.name as warehouse_name',
                'm.destination_warehouse_id',
                'dw.name as destination_warehouse_name',
                'm.source_location_id',
                'm.destination_location_id',
                'm.transit_location_id',
                'm.dispatched_at',
                'm.received_at',
                'm.reference',
                'm.origin_document',
                'l.id as stock_movement_line_id',
                'l.product_id',
                'p.name as product_name',
                'l.product_variant_id',
                'v.name as variant_name',
                'l.lot_id',
                'lot.lot_number',
                'l.done_quantity',
            ]);

        if (! empty($filters['company_id'])) {
            $query->where('m.company_id', (int) $filters['company_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $warehouseId = (int) $filters['warehouse_id'];

            $query->where(function ($q) use ($warehouseId): void {
                $q->where('m.warehouse_id', $warehouseId)
                    ->orWhere('m.destination_warehouse_id', $warehouseId);
            });
        }

        if (! empty($filters['location_id'])) {
            $locationId = (int) $filters['location_id'];

            $query->where(function ($q) use ($locationId): void {
                $q->where('m.source_location_id', $locationId)
                    ->orWhere('m.destination_location_id', $locationId)
                    ->orWhere('m.transit_location_id', $locationId);
            });
        }

        if (! empty($filters['product_id'])) {
            $query->where('l.product_id', (int) $filters['product_id']);
        }

        if (! empty($filters['product_variant_id'])) {
            $query->where('l.product_variant_id', (int) $filters['product_variant_id']);
        }

        if (! empty($filters['lot_id'])) {
            $query->where('l.lot_id', (int) $filters['lot_id']);
        }

        $cutoffAt = $cutoff->format('Y-m-d H:i:s');

        foreach ($query->get() as $row) {
            $doneQty = abs((float) ($row->done_quantity ?? 0));

            if ($doneQty <= 0) {
                continue;
            }

            /*
             * Deshacer cada recepción que ocurrió después del corte:
             * destino -> tránsito.
             */
            $receipts = DB::table('stock_movement_receipt_lines as rl')
                ->join(
                    'stock_movement_receipts as r',
                    'r.id',
                    '=',
                    'rl.stock_movement_receipt_id'
                )
                ->where('r.stock_movement_id', (int) $row->stock_movement_id)
                ->where(
                    'rl.stock_movement_line_id',
                    (int) $row->stock_movement_line_id
                )
                ->where('r.received_at', '>', $cutoffAt)
                ->select([
                    'r.received_at',
                    'rl.quantity',
                ])
                ->orderByDesc('r.received_at')
                ->get();

            foreach ($receipts as $receipt) {
                $receiptQty = abs((float) ($receipt->quantity ?? 0));

                if ($receiptQty <= 0) {
                    continue;
                }

                $this->reverseTransferReceipt(
                    $balances,
                    $row,
                    $receiptQty,
                    $filters
                );
            }

            /*
             * Si el despacho también ocurrió después del corte,
             * deshacer origen -> tránsito.
             */
            if (
                ! empty($row->dispatched_at)
                && (string) $row->dispatched_at > $cutoffAt
            ) {
                $this->reverseTransferDispatch(
                    $balances,
                    $row,
                    $doneQty,
                    $filters
                );
            }
        }
    }

    protected function reverseTransferReceipt(
        array &$balances,
        object $row,
        float $qty,
        array $filters
    ): void {
        $destinationLocationId = $row->destination_location_id ?: null;
        $destinationLocationName = $this->locationName($destinationLocationId);

        $destinationMeta = $this->movementMeta(
            $row,
            $destinationLocationId,
            $destinationLocationName,
            ! empty($row->destination_warehouse_id)
                ? (int) $row->destination_warehouse_id
                : null,
            $row->destination_warehouse_name ?? null
        );

        /*
         * Deshacer entrada al destino.
         */
        if ($this->transferMetaMatchesFilters($destinationMeta, $filters)) {
            $this->addBalance(
                $balances,
                $this->keyFromMeta($destinationMeta),
                $destinationMeta,
                -1 * $qty
            );
        }

        /*
         * Regresar esa cantidad al tránsito.
         */
        $transitMeta = $this->transferTransitMeta($row);

        if ($this->transferMetaMatchesFilters($transitMeta, $filters)) {
            $this->addBalance(
                $balances,
                $this->keyFromMeta($transitMeta),
                $transitMeta,
                $qty
            );
        }
    }

    protected function reverseTransferDispatch(
        array &$balances,
        object $row,
        float $qty,
        array $filters
    ): void {
        /*
         * Deshacer entrada a tránsito.
         */
        $transitMeta = $this->transferTransitMeta($row);

        if ($this->transferMetaMatchesFilters($transitMeta, $filters)) {
            $this->addBalance(
                $balances,
                $this->keyFromMeta($transitMeta),
                $transitMeta,
                -1 * $qty
            );
        }

        /*
         * Regresar cantidad al origen.
         */
        $sourceLocationId = $row->source_location_id ?: null;
        $sourceLocationName = $this->locationName($sourceLocationId);

        $sourceMeta = $this->movementMeta(
            $row,
            $sourceLocationId,
            $sourceLocationName,
            ! empty($row->warehouse_id)
                ? (int) $row->warehouse_id
                : null,
            $row->warehouse_name ?? null
        );

        if ($this->transferMetaMatchesFilters($sourceMeta, $filters)) {
            $this->addBalance(
                $balances,
                $this->keyFromMeta($sourceMeta),
                $sourceMeta,
                $qty
            );
        }
    }

    protected function transferMetaMatchesFilters(
        array $meta,
        array $filters
    ): bool {
        if (
            ! empty($filters['company_id'])
            && (int) ($meta['company_id'] ?? 0) !== (int) $filters['company_id']
        ) {
            return false;
        }

        if (
            ! empty($filters['warehouse_id'])
            && (int) ($meta['warehouse_id'] ?? 0) !== (int) $filters['warehouse_id']
        ) {
            return false;
        }

        if (
            ! empty($filters['location_id'])
            && (int) ($meta['location_id'] ?? 0) !== (int) $filters['location_id']
        ) {
            return false;
        }

        if (
            ! empty($filters['product_id'])
            && (int) ($meta['product_id'] ?? 0) !== (int) $filters['product_id']
        ) {
            return false;
        }

        if (
            ! empty($filters['product_variant_id'])
            && (int) ($meta['variant_id'] ?? 0) !== (int) $filters['product_variant_id']
        ) {
            return false;
        }

        if (
            ! empty($filters['lot_id'])
            && (int) ($meta['lot_id'] ?? 0) !== (int) $filters['lot_id']
        ) {
            return false;
        }

        return true;
    }

    protected function transferTransitMeta(object $row): array
    {
        $locationId = $row->transit_location_id ?: null;
        $locationName = $this->locationName($locationId);

        /*
         * El tránsito pertenece operacionalmente al almacén origen
         * durante el viaje, aun cuando la ubicación no tenga
         * warehouse_id configurado.
         */
        return $this->movementMeta(
            $row,
            $locationId,
            $locationName,
            ! empty($row->warehouse_id)
                ? (int) $row->warehouse_id
                : null,
            $row->warehouse_name ?? null
        );
    }

    protected function reverseInMovement(array &$balances, object $row, float $qty): void
    {
        $locationId = $row->destination_location_id ?: null;
        $locationName = $this->locationName($locationId);

        $warehouseId = ! empty($row->destination_warehouse_id)
            ? (int) $row->destination_warehouse_id
            : (int) ($row->warehouse_id ?? 0);

        $warehouseName = ! empty($row->destination_warehouse_name)
            ? (string) $row->destination_warehouse_name
            : (string) ($row->warehouse_name ?? '');

        $meta = $this->movementMeta(
            $row,
            $locationId,
            $locationName,
            $warehouseId ?: null,
            $warehouseName ?: null
        );

        $this->addBalance($balances, $this->keyFromMeta($meta), $meta, $qty);
    }

    protected function reverseOutMovement(array &$balances, object $row, float $qty): void
    {
        $locationId = $row->source_location_id ?: null;
        $locationName = $this->locationName($locationId);

        $meta = $this->movementMeta($row, $locationId, $locationName);

        $this->addBalance($balances, $this->keyFromMeta($meta), $meta, $qty);
    }

    protected function movementMeta(
        object $row,
        ?int $locationId,
        ?string $locationName,
        ?int $warehouseId = null,
        ?string $warehouseName = null
    ): array {
        return [
            'company_id' => $row->company_id,
            'company_name' => $row->company_name,
            'warehouse_id' => $warehouseId ?? $row->warehouse_id,
            'warehouse_name' => $warehouseName ?? $row->warehouse_name,
            'location_id' => $locationId,
            'location_name' => $locationName,
            'product_id' => $row->product_id,
            'product_name' => $row->product_name,
            'variant_id' => $row->product_variant_id,
            'variant_name' => $row->variant_name,
            'lot_id' => $row->lot_id,
            'lot_number' => $row->lot_number,
        ];
    }

    protected function addBalance(array &$balances, string $key, array $meta, float $qty): void
    {
        if (! isset($balances[$key])) {
            $balances[$key] = $meta + ['quantity_as_of' => 0.0];
        }

        $balances[$key]['quantity_as_of'] += $qty;
    }

    protected function applyBaseFilters($query, array $filters, string $alias): void
    {
        foreach ([
            'company_id',
            'warehouse_id',
            'location_id',
            'product_id',
            'product_variant_id',
            'lot_id',
        ] as $field) {
            if (! empty($filters[$field])) {
                $query->where("{$alias}.{$field}", (int) $filters[$field]);
            }
        }
    }

    protected function applyMovementFilters($query, array $filters): void
    {
        if (! empty($filters['company_id'])) {
            $query->where('m.company_id', (int) $filters['company_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $warehouseId = (int) $filters['warehouse_id'];

            $query->where(function ($q) use ($warehouseId): void {
                $q->where('m.warehouse_id', $warehouseId)
                    ->orWhere('m.destination_warehouse_id', $warehouseId);
            });
        }

        if (! empty($filters['location_id'])) {
            $locationId = (int) $filters['location_id'];

            $query->where(function ($q) use ($locationId): void {
                $q->where('m.source_location_id', $locationId)
                    ->orWhere('m.destination_location_id', $locationId);
            });
        }

        if (! empty($filters['product_id'])) {
            $query->where('l.product_id', (int) $filters['product_id']);
        }

        if (! empty($filters['product_variant_id'])) {
            $query->where('l.product_variant_id', (int) $filters['product_variant_id']);
        }

        if (! empty($filters['lot_id'])) {
            $query->where('l.lot_id', (int) $filters['lot_id']);
        }
    }

    protected function classifyDirection(object $row): string
    {
        $kind = strtolower((string) ($row->operation_kind ?? ''));
        $operation = strtolower((string) ($row->operation_name ?? ''));
        $sourceType = strtolower((string) ($row->source_type ?? ''));
        $origin = strtolower((string) ($row->origin_document ?? ''));
        $reference = strtolower((string) ($row->reference ?? ''));

        if (str_contains($kind, 'internal') || str_contains($operation, 'traslado') || str_contains($reference, '/int/')) {
            return 'transfer';
        }

        if ($sourceType === 'purchase_receipt' || $sourceType === 'pos_order_refund') {
            return 'in';
        }

        if ($sourceType === 'sale_delivery' || $sourceType === 'pos_order') {
            return 'out';
        }

        if (str_contains($kind, 'receipt') || str_contains($operation, 'entrada') || str_contains($operation, 'recepción') || str_contains($operation, 'recepcion')) {
            return 'in';
        }

        if (str_contains($kind, 'delivery') || str_contains($operation, 'salida') || str_contains($operation, 'venta')) {
            return 'out';
        }

        if (str_contains($origin, 'purchase_receipt:') || str_contains($reference, '/in/')) {
            return 'in';
        }

        if (str_contains($origin, 'sale_delivery:') || str_contains($origin, 'pos_order:') || str_contains($reference, '/out/') || str_contains($reference, '/pdv/')) {
            return 'out';
        }

        if (str_starts_with(strtoupper((string) ($row->origin_document ?? '')), 'DEV-') || str_contains($reference, '/dev/')) {
            return 'in';
        }

        return 'unknown';
    }

    protected function cutoff(array $filters): Carbon
    {
        $date = (string) ($filters['cutoff_date'] ?? now()->toDateString());
        $time = (string) ($filters['cutoff_time'] ?? '23:59:59');

        if (strlen($time) === 5) {
            $time .= ':00';
        }

        return Carbon::parse(trim($date . ' ' . $time));
    }

    protected function quantityColumn(): ?string
    {
        foreach (['quantity', 'qty', 'on_hand_quantity', 'available_quantity'] as $column) {
            if (Schema::hasColumn('stock_quants', $column)) {
                return $column;
            }
        }

        return null;
    }

    protected function key(object $row): string
    {
        return implode('|', [
            $row->company_id ?: 0,
            $row->warehouse_id ?: 0,
            $row->location_id ?: 0,
            $row->product_id ?: 0,
            $row->product_variant_id ?: 0,
            $row->lot_id ?: 0,
        ]);
    }

    protected function keyFromMeta(array $meta): string
    {
        return implode('|', [
            $meta['company_id'] ?: 0,
            $meta['warehouse_id'] ?: 0,
            $meta['location_id'] ?: 0,
            $meta['product_id'] ?: 0,
            $meta['variant_id'] ?: 0,
            $meta['lot_id'] ?: 0,
        ]);
    }

    protected function locationName(?int $locationId): ?string
    {
        if (! $locationId || ! Schema::hasTable('stock_locations')) {
            return null;
        }

        return DB::table('stock_locations')->where('id', $locationId)->value('name');
    }
}
