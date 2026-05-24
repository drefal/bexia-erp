<?php

namespace App\Support\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductKardexService
{
    public const METHOD_AUTO = 'auto';
    public const METHOD_AVERAGE = 'average';
    public const METHOD_FIFO = 'fifo';
    public const METHOD_STANDARD = 'standard';
    public const METHOD_RECORDED = 'recorded';

    public function rows(array $filters = []): Collection
    {
        if (! Schema::hasTable('stock_movement_lines') || ! Schema::hasTable('stock_movements')) {
            return collect();
        }

        $query = DB::table('stock_movement_lines as l')
            ->leftJoin('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->leftJoin('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('products as v', 'v.id', '=', 'l.product_variant_id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'p.product_category_id')
            ->leftJoin('companies as c', 'c.id', '=', 'm.company_id')
            ->leftJoin('stock_lots as lot', 'lot.id', '=', 'l.lot_id')
            ->leftJoin('stock_serial_numbers as serial', 'serial.id', '=', 'l.stock_serial_number_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'm.warehouse_id')
            ->leftJoin('stock_locations as sl_from', 'sl_from.id', '=', 'm.source_location_id')
            ->leftJoin('stock_locations as sl_to', 'sl_to.id', '=', 'm.destination_location_id')
            ->select([
                'l.id',
                'l.stock_movement_id',
                'm.company_id',
                'c.name as company_name',
                'm.warehouse_id',
                'w.name as warehouse_name',
                'm.source_location_id',
                'sl_from.name as source_location_name',
                'm.destination_location_id',
                'sl_to.name as destination_location_name',
                'm.reference',
                'm.origin_document',
                'm.status',
                'm.movement_at',
                'm.created_at as movement_created_at',

                'l.source_type',
                'l.source_id',
                'l.source_line_type',
                'l.source_line_id',
                'l.product_id',
                'p.name as product_name',
                'p.company_id as product_company_id',
                'p.product_category_id',
                'p.costing_method as product_costing_method',
                'p.standard_cost as product_standard_cost',
                'p.average_cost_without_tax as product_average_cost_without_tax',
                'p.last_purchase_cost as product_last_purchase_cost',

                'l.product_variant_id',
                'v.name as variant_name',
                'v.costing_method as variant_costing_method',
                'v.standard_cost as variant_standard_cost',
                'v.average_cost_without_tax as variant_average_cost_without_tax',
                'v.last_purchase_cost as variant_last_purchase_cost',

                'pc.costing_method as category_costing_method',
                'c.default_costing_method as company_default_costing_method',

                'l.lot_id',
                'lot.lot_number as lot_number',
                'l.stock_serial_number_id',
                'serial.serial_number as serial_number',
                'l.requested_quantity',
                'l.done_quantity',
                'l.unit_cost',
                'l.total_cost',
                'l.costing_method',
                'l.cost_source',
                'l.notes',
            ]);

        $this->applyFilters($query, $filters);

        $records = $query
            ->orderByRaw('coalesce(m.movement_at, m.created_at, l.created_at) asc')
            ->orderBy('l.id')
            ->limit((int) ($filters['limit'] ?? 1000))
            ->get();

        $selectedMethod = $this->normalizeMethod((string) ($filters['valuation_method'] ?? self::METHOD_AUTO));
        $states = [];

        return $records->map(function ($row) use (&$states, $selectedMethod) {
            $key = $this->balanceKey($row);
            $method = $selectedMethod === self::METHOD_AUTO
                ? $this->effectiveCostingMethod($row)
                : $selectedMethod;

            if (! isset($states[$key])) {
                $states[$key] = $this->initialState($row);
            }

            $direction = $this->direction($row);
            $qty = abs((float) ($row->done_quantity ?? $row->requested_quantity ?? 0));

            $valuation = match ($method) {
                self::METHOD_FIFO => $this->applyFifo($states[$key], $row, $direction, $qty),
                self::METHOD_STANDARD => $this->applyStandard($states[$key], $row, $direction, $qty),
                self::METHOD_RECORDED => $this->applyRecorded($states[$key], $row, $direction, $qty),
                default => $this->applyAverage($states[$key], $row, $direction, $qty),
            };

            return (object) [
                'id' => $row->id,
                'balance_key' => $key,
                'stock_movement_id' => $row->stock_movement_id,
                'company_id' => $row->company_id,
                'company_name' => $row->company_name,
                'date' => $row->movement_at ?: $row->movement_created_at,
                'reference' => $row->reference,
                'origin_document' => $row->origin_document,
                'status' => $row->status,
                'warehouse_name' => $row->warehouse_name,
                'source_location_name' => $row->source_location_name,
                'destination_location_name' => $row->destination_location_name,
                'source_type' => $row->source_type,
                'source_line_type' => $row->source_line_type,
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'product_variant_id' => $row->product_variant_id,
                'variant_name' => $row->variant_name,
                'lot_id' => $row->lot_id,
                'lot_number' => $row->lot_number,
                'stock_serial_number_id' => $row->stock_serial_number_id,
                'serial_number' => $row->serial_number,

                'direction' => $direction,
                'quantity' => $qty,
                'in_qty' => $direction === 'in' ? $qty : 0.0,
                'out_qty' => $direction === 'out' ? $qty : 0.0,

                'recorded_unit_cost' => (float) ($row->unit_cost ?? 0),
                'recorded_total_cost' => (float) ($row->total_cost ?? 0),
                'applied_unit_cost' => $valuation['unit_cost'],
                'movement_value' => $valuation['movement_value'],
                'signed_value' => $direction === 'out' ? -abs($valuation['movement_value']) : abs($valuation['movement_value']),
                'balance_qty' => $states[$key]['qty'],
                'balance_value' => $states[$key]['value'],
                'average_cost' => $states[$key]['average_cost'],
                'valuation_method' => $method,

                'costing_method' => $row->costing_method,
                'cost_source' => $row->cost_source,
                'notes' => $row->notes,
            ];
        });
    }

    public function summary(array $filters = []): array
    {
        $rows = $this->rows($filters);
        $lastByKey = [];

        foreach ($rows as $row) {
            $lastByKey[$row->balance_key] = $row;
        }

        $methods = $rows
            ->pluck('valuation_method')
            ->filter()
            ->unique()
            ->values();

        return [
            'rows' => $rows->count(),
            'in_qty' => round((float) $rows->sum('in_qty'), 6),
            'out_qty' => round((float) $rows->sum('out_qty'), 6),
            'balance_qty' => round((float) collect($lastByKey)->sum('balance_qty'), 6),
            'in_value' => round((float) $rows->where('direction', 'in')->sum('movement_value'), 6),
            'out_value' => round((float) $rows->where('direction', 'out')->sum('movement_value'), 6),
            'balance_value' => round((float) collect($lastByKey)->sum('balance_value'), 6),
            'valuation_method' => $methods->count() === 1 ? $methods->first() : 'mixed',
        ];
    }

    protected function applyAverage(array &$state, object $row, string $direction, float $qty): array
    {
        if ($direction === 'in') {
            $movementValue = $this->recordedLineValue($row, $qty);
            $unitCost = $qty > 0 ? round($movementValue / $qty, 6) : $this->baseUnitCost($row);

            $state['qty'] = round($state['qty'] + $qty, 6);
            $state['value'] = round($state['value'] + $movementValue, 6);
            $state['average_cost'] = $state['qty'] > 0 ? round($state['value'] / $state['qty'], 6) : 0.0;

            return [
                'unit_cost' => $unitCost,
                'movement_value' => round($movementValue, 6),
            ];
        }

        $unitCost = $state['average_cost'] > 0 ? $state['average_cost'] : $this->baseUnitCost($row);
        $movementValue = round($qty * $unitCost, 6);

        $state['qty'] = round($state['qty'] - $qty, 6);
        $state['value'] = round($state['value'] - $movementValue, 6);

        if (abs($state['qty']) < 0.000001) {
            $state['qty'] = 0.0;
            $state['value'] = 0.0;
            $state['average_cost'] = 0.0;
        } elseif ($state['qty'] > 0) {
            $state['average_cost'] = round($state['value'] / $state['qty'], 6);
        }

        return [
            'unit_cost' => $unitCost,
            'movement_value' => $movementValue,
        ];
    }

    protected function applyFifo(array &$state, object $row, string $direction, float $qty): array
    {
        if ($direction === 'in') {
            $unitCost = $this->baseUnitCost($row);
            $movementValue = round($qty * $unitCost, 6);

            if ($qty > 0) {
                $state['layers'][] = [
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                ];
            }

            $state['qty'] = round($state['qty'] + $qty, 6);
            $state['value'] = round($this->fifoLayerValue($state), 6);
            $state['average_cost'] = $state['qty'] > 0 ? round($state['value'] / $state['qty'], 6) : 0.0;

            return [
                'unit_cost' => $unitCost,
                'movement_value' => $movementValue,
            ];
        }

        $remaining = $qty;
        $movementValue = 0.0;

        foreach ($state['layers'] as $index => $layer) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $layer['qty'];
            if ($available <= 0) {
                continue;
            }

            $consume = min($available, $remaining);
            $movementValue += round($consume * (float) $layer['unit_cost'], 6);
            $state['layers'][$index]['qty'] = round($available - $consume, 6);
            $remaining = round($remaining - $consume, 6);
        }

        $state['layers'] = array_values(array_filter(
            $state['layers'],
            fn (array $layer): bool => (float) $layer['qty'] > 0.000001
        ));

        if ($remaining > 0.000001) {
            $fallbackCost = $this->baseUnitCost($row);
            $movementValue += round($remaining * $fallbackCost, 6);
        }

        $unitCost = $qty > 0 ? round($movementValue / $qty, 6) : 0.0;

        $state['qty'] = round($state['qty'] - $qty, 6);
        $state['value'] = round($this->fifoLayerValue($state), 6);
        $state['average_cost'] = $state['qty'] > 0 ? round($state['value'] / $state['qty'], 6) : 0.0;

        return [
            'unit_cost' => $unitCost,
            'movement_value' => round($movementValue, 6),
        ];
    }

    protected function applyStandard(array &$state, object $row, string $direction, float $qty): array
    {
        $unitCost = $this->standardCost($row);
        $movementValue = round($qty * $unitCost, 6);

        $state['qty'] = $direction === 'out'
            ? round($state['qty'] - $qty, 6)
            : round($state['qty'] + $qty, 6);

        $state['value'] = round($state['qty'] * $unitCost, 6);
        $state['average_cost'] = $unitCost;

        return [
            'unit_cost' => $unitCost,
            'movement_value' => $movementValue,
        ];
    }

    protected function applyRecorded(array &$state, object $row, string $direction, float $qty): array
    {
        $movementValue = $this->recordedLineValue($row, $qty);
        $unitCost = $qty > 0 ? round($movementValue / $qty, 6) : $this->baseUnitCost($row);

        $state['qty'] = $direction === 'out'
            ? round($state['qty'] - $qty, 6)
            : round($state['qty'] + $qty, 6);

        $state['value'] = $direction === 'out'
            ? round($state['value'] - $movementValue, 6)
            : round($state['value'] + $movementValue, 6);

        $state['average_cost'] = $state['qty'] > 0 ? round($state['value'] / $state['qty'], 6) : 0.0;

        return [
            'unit_cost' => $unitCost,
            'movement_value' => round($movementValue, 6),
        ];
    }

    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['company_id'])) {
            $query->where('m.company_id', (int) $filters['company_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('m.warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['location_id'])) {
            $locationId = (int) $filters['location_id'];
            $query->where(function ($q) use ($locationId): void {
                $q->where('m.source_location_id', $locationId)
                    ->orWhere('m.destination_location_id', $locationId);
            });
        }

        if (! empty($filters['product_search']) && empty($filters['product_id'])) {
            $search = trim((string) $filters['product_search']);
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $query->where(function ($q) use ($like): void {
                $q->where('p.name', 'ilike', $like);

                foreach (['sku', 'barcode', 'internal_reference', 'variant_name'] as $column) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('products', $column)) {
                        $q->orWhere("p.{$column}", 'ilike', $like);
                    }
                }
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

        if (! empty($filters['stock_serial_number_id'])) {
            $query->where('l.stock_serial_number_id', (int) $filters['stock_serial_number_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereRaw('coalesce(m.movement_at, m.created_at, l.created_at) >= ?', [$filters['date_from'] . ' 00:00:00']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereRaw('coalesce(m.movement_at, m.created_at, l.created_at) <= ?', [$filters['date_to'] . ' 23:59:59']);
        }

        if (! empty($filters['status'])) {
            $query->where('m.status', (string) $filters['status']);
        }
    }

    protected function direction(object $row): string
    {
        $sourceType = strtolower((string) ($row->source_type ?? ''));
        $sourceLineType = strtolower((string) ($row->source_line_type ?? ''));
        $reference = strtoupper((string) ($row->reference ?? ''));
        $origin = strtolower((string) ($row->origin_document ?? ''));
        $qty = (float) ($row->done_quantity ?? $row->requested_quantity ?? 0);

        if ($qty < 0) {
            return 'out';
        }

        if (in_array($sourceType, ['sale_delivery', 'pos_order'], true)) {
            return 'out';
        }

        if (in_array($sourceType, ['purchase_receipt', 'pos_order_refund'], true)) {
            return 'in';
        }

        if ($sourceType === 'stock_adjustment') {
            return $qty < 0 ? 'out' : 'in';
        }

        if (str_contains($reference, '/OUT/') || str_contains($reference, '/PDV/') || str_contains($origin, 'sale_delivery')) {
            return 'out';
        }

        if (str_contains($reference, '/IN/') || str_contains($origin, 'purchase_receipt')) {
            return 'in';
        }

        if (str_contains($sourceLineType, 'refund')) {
            return 'in';
        }

        return 'in';
    }

    protected function effectiveCostingMethod(object $row): string
    {
        foreach ([
            $row->variant_costing_method ?? null,
            $row->product_costing_method ?? null,
            $row->category_costing_method ?? null,
            $row->company_default_costing_method ?? null,
        ] as $method) {
            $normalized = $this->normalizeMethod((string) $method);

            if (! in_array($normalized, [self::METHOD_AUTO, 'inherit', ''], true)) {
                return $normalized;
            }
        }

        return self::METHOD_AVERAGE;
    }

    protected function normalizeMethod(string $method): string
    {
        $method = strtolower(trim($method));

        return match ($method) {
            'peps', 'fifo' => self::METHOD_FIFO,
            'standard', 'estandar', 'estándar' => self::METHOD_STANDARD,
            'recorded', 'registrado' => self::METHOD_RECORDED,
            'average', 'promedio' => self::METHOD_AVERAGE,
            'auto', 'inherit', '' => self::METHOD_AUTO,
            default => self::METHOD_AVERAGE,
        };
    }

    protected function balanceKey(object $row): string
    {
        return implode('|', [
            (int) ($row->company_id ?? $row->product_company_id ?? 0),
            (int) ($row->warehouse_id ?? 0),
            (int) ($row->product_id ?? 0),
            (int) ($row->product_variant_id ?? 0),
            (int) ($row->lot_id ?? 0),
            (int) ($row->stock_serial_number_id ?? 0),
        ]);
    }

    protected function initialState(object $row): array
    {
        return [
            'qty' => 0.0,
            'value' => 0.0,
            'average_cost' => 0.0,
            'layers' => [],
            'standard_cost' => $this->standardCost($row),
        ];
    }

    protected function recordedLineValue(object $row, float $qty): float
    {
        if ($row->total_cost !== null) {
            return round(abs((float) $row->total_cost), 6);
        }

        return round($qty * $this->baseUnitCost($row), 6);
    }

    protected function baseUnitCost(object $row): float
    {
        $qty = abs((float) ($row->done_quantity ?? $row->requested_quantity ?? 0));

        if ($qty > 0 && $row->total_cost !== null) {
            return round(abs((float) $row->total_cost) / $qty, 6);
        }

        return round((float) ($row->unit_cost ?? 0), 6);
    }

    protected function standardCost(object $row): float
    {
        foreach ([
            $row->variant_standard_cost ?? null,
            $row->product_standard_cost ?? null,
            $row->variant_average_cost_without_tax ?? null,
            $row->product_average_cost_without_tax ?? null,
            $row->variant_last_purchase_cost ?? null,
            $row->product_last_purchase_cost ?? null,
            $row->unit_cost ?? null,
        ] as $cost) {
            if ($cost !== null && (float) $cost > 0) {
                return round((float) $cost, 6);
            }
        }

        return 0.0;
    }

    protected function fifoLayerValue(array $state): float
    {
        $value = 0.0;

        foreach ($state['layers'] as $layer) {
            $value += round((float) $layer['qty'] * (float) $layer['unit_cost'], 6);
        }

        return round($value, 6);
    }
}
