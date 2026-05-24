<?php

namespace App\Support\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryValuationService
{
    public function rows(array $filters = []): Collection
    {
        if (! empty($filters['stock_serial_number_id'])) {
            return $this->serialRows($filters);
        }

        if (! Schema::hasTable('stock_quants')) {
            return collect();
        }

        $qtyColumn = Schema::hasColumn('stock_quants', 'quantity') ? 'quantity' : 'qty';

        $query = DB::table('stock_quants as q')
            ->leftJoin('companies as c', 'c.id', '=', 'q.company_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'q.warehouse_id')
            ->leftJoin('stock_locations as sl', 'sl.id', '=', 'q.location_id')
            ->leftJoin('products as p', 'p.id', '=', 'q.product_id')
            ->leftJoin('products as v', 'v.id', '=', 'q.product_variant_id')
            ->leftJoin('stock_lots as lot', 'lot.id', '=', 'q.lot_id')
            ->selectRaw("
                q.company_id,
                max(c.name) as company_name,
                q.warehouse_id,
                max(w.name) as warehouse_name,
                q.location_id,
                max(sl.name) as location_name,
                q.product_id,
                max(p.name) as product_name,
                q.product_variant_id,
                max(v.name) as variant_name,
                q.lot_id,
                max(lot.lot_number) as lot_number,
                null as stock_serial_number_id,
                null as serial_number,
                sum(q.{$qtyColumn}) as quantity,
                avg(coalesce(q.average_cost, 0)) as quant_average_cost,
                max(coalesce(v.average_cost_without_tax, p.average_cost_without_tax, 0)) as product_average_cost,
                max(coalesce(v.standard_cost, p.standard_cost, 0)) as standard_cost
            ");

        if (! empty($filters['company_id'])) {
            $query->where('q.company_id', (int) $filters['company_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('q.warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('q.location_id', (int) $filters['location_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('q.product_id', (int) $filters['product_id']);
        } elseif (! empty($filters['product_search'])) {
            $search = trim((string) $filters['product_search']);
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $query->where(function ($q) use ($like): void {
                $q->where('p.name', 'ilike', $like)
                    ->orWhere('v.name', 'ilike', $like);

                foreach (['sku', 'barcode', 'internal_reference'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $q->orWhere("p.{$column}", 'ilike', $like)
                            ->orWhere("v.{$column}", 'ilike', $like);
                    }
                }
            });
        }

        if (! empty($filters['product_variant_id'])) {
            $query->where('q.product_variant_id', (int) $filters['product_variant_id']);
        }

        if (! empty($filters['lot_id'])) {
            $query->where('q.lot_id', (int) $filters['lot_id']);
        }

        $query->groupBy(
            'q.company_id',
            'q.warehouse_id',
            'q.location_id',
            'q.product_id',
            'q.product_variant_id',
            'q.lot_id'
        );

        if (($filters['only_positive'] ?? true) !== false) {
            $query->havingRaw("sum(q.{$qtyColumn}) > 0");
        }

        $limit = min(5000, max(50, (int) ($filters['limit'] ?? 500)));

        return $query
            ->orderBy('company_name')
            ->orderBy('warehouse_name')
            ->orderBy('location_name')
            ->orderBy('product_name')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->decorateRow($row));
    }

    protected function serialRows(array $filters = []): Collection
    {
        if (! Schema::hasTable('stock_serial_numbers')) {
            return collect();
        }

        $query = DB::table('stock_serial_numbers as s')
            ->leftJoin('companies as c', 'c.id', '=', 's.company_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 's.current_warehouse_id')
            ->leftJoin('stock_locations as sl', 'sl.id', '=', 's.current_location_id')
            ->leftJoin('products as p', 'p.id', '=', 's.product_id')
            ->leftJoin('products as v', 'v.id', '=', 's.product_variant_id')
            ->leftJoin('stock_lots as lot', 'lot.id', '=', 's.lot_id')
            ->leftJoin('stock_movement_lines as l', 'l.id', '=', 's.stock_movement_line_id')
            ->selectRaw("
                s.company_id,
                c.name as company_name,
                s.current_warehouse_id as warehouse_id,
                w.name as warehouse_name,
                s.current_location_id as location_id,
                sl.name as location_name,
                s.product_id,
                p.name as product_name,
                s.product_variant_id,
                v.name as variant_name,
                s.lot_id,
                lot.lot_number as lot_number,
                s.id as stock_serial_number_id,
                s.serial_number,
                1 as quantity,
                coalesce(l.unit_cost, 0) as serial_unit_cost,
                coalesce(v.average_cost_without_tax, p.average_cost_without_tax, 0) as product_average_cost,
                coalesce(v.standard_cost, p.standard_cost, 0) as standard_cost
            ")
            ->where('s.status', 'available');

        if (! empty($filters['stock_serial_number_id'])) {
            $query->where('s.id', (int) $filters['stock_serial_number_id']);
        }

        if (! empty($filters['company_id'])) {
            $query->where('s.company_id', (int) $filters['company_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('s.current_warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('s.current_location_id', (int) $filters['location_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('s.product_id', (int) $filters['product_id']);
        }

        if (! empty($filters['product_variant_id'])) {
            $query->where('s.product_variant_id', (int) $filters['product_variant_id']);
        }

        if (! empty($filters['lot_id'])) {
            $query->where('s.lot_id', (int) $filters['lot_id']);
        }

        $limit = min(5000, max(50, (int) ($filters['limit'] ?? 500)));

        return $query
            ->orderBy('company_name')
            ->orderBy('warehouse_name')
            ->orderBy('location_name')
            ->orderBy('product_name')
            ->orderBy('s.serial_number')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $row->quant_average_cost = (float) ($row->serial_unit_cost ?? 0);
                return $this->decorateRow($row, true);
            });
    }

    protected function decorateRow(object $row, bool $isSerial = false): object
    {
        $costInfo = $this->resolveCost($row, $isSerial);
        $quantity = (float) $row->quantity;
        $unitCost = (float) $costInfo['unit_cost'];
        $totalValue = round($quantity * $unitCost, 6);

        $alerts = [];

        if ($quantity > 0 && $unitCost <= 0) {
            $alerts[] = 'Sin costo';
        }

        if (! $isSerial && (float) ($row->quant_average_cost ?? 0) <= 0 && $unitCost > 0) {
            $alerts[] = 'Costo tomado de respaldo';
        }

        return (object) [
            'company_id' => $row->company_id,
            'company_name' => $row->company_name,
            'warehouse_id' => $row->warehouse_id,
            'warehouse_name' => $row->warehouse_name,
            'location_id' => $row->location_id,
            'location_name' => $row->location_name,
            'product_id' => $row->product_id,
            'product_name' => $row->product_name,
            'product_variant_id' => $row->product_variant_id,
            'variant_name' => $row->variant_name,
            'lot_id' => $row->lot_id,
            'lot_number' => $row->lot_number,
            'stock_serial_number_id' => $row->stock_serial_number_id ?? null,
            'serial_number' => $row->serial_number ?? null,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_value' => $totalValue,
            'cost_source' => $costInfo['source'],
            'costing_method' => $costInfo['method'],
            'alerts' => implode(', ', $alerts),
        ];
    }

    public function summary(array $filters = []): array
    {
        $rows = $this->rows($filters);

        return [
            'lines' => $rows->count(),
            'quantity' => round((float) $rows->sum('quantity'), 6),
            'value' => round((float) $rows->sum('total_value'), 6),
            'without_cost' => $rows->filter(fn ($row) => (float) $row->unit_cost <= 0 && (float) $row->quantity > 0)->count(),
            'with_alerts' => $rows->filter(fn ($row) => trim((string) $row->alerts) !== '')->count(),
        ];
    }

    protected function resolveCost(object $row, bool $isSerial = false): array
    {
        $method = 'average';

        if (class_exists(InventoryCostingMethodResolver::class)) {
            try {
                $resolved = app(InventoryCostingMethodResolver::class)->resolveForProduct(
                    companyId: $row->company_id ? (int) $row->company_id : null,
                    productId: $row->product_id ? (int) $row->product_id : null
                );

                $method = $resolved['method'] ?? 'average';
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $serialCost = (float) ($row->serial_unit_cost ?? 0);
        $quantAverage = (float) ($row->quant_average_cost ?? 0);
        $productAverage = (float) ($row->product_average_cost ?? 0);
        $standardCost = (float) ($row->standard_cost ?? 0);

        if ($isSerial && $serialCost > 0) {
            return ['unit_cost' => $serialCost, 'source' => 'Costo de recepción de serie', 'method' => $this->methodLabel($method)];
        }

        if ($method === 'standard' && $standardCost > 0) {
            return ['unit_cost' => $standardCost, 'source' => 'Costo estándar', 'method' => 'Costo estándar'];
        }

        if ($quantAverage > 0) {
            return ['unit_cost' => $quantAverage, 'source' => 'Costo promedio de existencia', 'method' => $this->methodLabel($method)];
        }

        if ($productAverage > 0) {
            return ['unit_cost' => $productAverage, 'source' => 'Costo promedio del producto', 'method' => $this->methodLabel($method)];
        }

        if ($standardCost > 0) {
            return ['unit_cost' => $standardCost, 'source' => 'Costo estándar de respaldo', 'method' => $this->methodLabel($method)];
        }

        $latestCost = $this->latestMovementCost(
            $row->product_id ? (int) $row->product_id : null,
            $row->product_variant_id ? (int) $row->product_variant_id : null,
            $row->company_id ? (int) $row->company_id : null
        );

        if ($latestCost > 0) {
            return ['unit_cost' => $latestCost, 'source' => 'Último costo registrado', 'method' => $this->methodLabel($method)];
        }

        return ['unit_cost' => 0, 'source' => 'Sin costo disponible', 'method' => $this->methodLabel($method)];
    }

    protected function latestMovementCost(?int $productId, ?int $variantId, ?int $companyId): float
    {
        if (! $productId || ! Schema::hasTable('stock_movement_lines')) {
            return 0.0;
        }

        $query = DB::table('stock_movement_lines as l')
            ->leftJoin('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->where('l.product_id', $productId)
            ->whereNotNull('l.unit_cost')
            ->where('l.unit_cost', '>', 0);

        if ($variantId) {
            $query->where('l.product_variant_id', $variantId);
        }

        if ($companyId) {
            $query->where('m.company_id', $companyId);
        }

        return (float) ($query
            ->orderByDesc('m.movement_at')
            ->orderByDesc('l.id')
            ->value('l.unit_cost') ?? 0);
    }

    protected function methodLabel(string $method): string
    {
        return match ($method) {
            'fifo' => 'PEPS / FIFO',
            'standard' => 'Costo estándar',
            'recorded' => 'Registrado',
            'average' => 'Costo promedio',
            default => 'Costo promedio',
        };
    }
}
