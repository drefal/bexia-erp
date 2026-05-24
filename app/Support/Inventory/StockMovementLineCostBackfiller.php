<?php

namespace App\Support\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockMovementLineCostBackfiller
{
    public function __construct(
        protected InventoryCostingMethodResolver $resolver
    ) {
    }

    public function run(bool $dryRun = false): array
    {
        if (! Schema::hasTable('stock_movement_lines')) {
            return [
                'processed' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => ['No existe stock_movement_lines.'],
            ];
        }

        $query = DB::table('stock_movement_lines');

        $query->where(function ($q): void {
            if (Schema::hasColumn('stock_movement_lines', 'total_cost')) {
                $q->orWhereNull('total_cost');
            }

            if (Schema::hasColumn('stock_movement_lines', 'costing_method')) {
                $q->orWhereNull('costing_method');
            }

            if (Schema::hasColumn('stock_movement_lines', 'cost_source')) {
                $q->orWhereNull('cost_source');
            }
        });

        $rows = $query->orderBy('id')->get();

        $result = [
            'processed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($rows as $line) {
            $result['processed']++;

            try {
                $updates = $this->updatesForLine($line);

                if ($updates === []) {
                    $result['skipped']++;
                    continue;
                }

                if (! $dryRun) {
                    DB::table('stock_movement_lines')
                        ->where('id', $line->id)
                        ->update($updates);
                }

                $result['updated']++;
            } catch (\Throwable $e) {
                $result['errors'][] = 'line_id=' . ($line->id ?? 'NULL') . ': ' . $e->getMessage();
            }
        }

        return $result;
    }

    protected function updatesForLine(object $line): array
    {
        $columns = Schema::getColumnListing('stock_movement_lines');
        $updates = [];

        $companyId = $this->nullableInt($line->company_id ?? null);
        $productId = $this->nullableInt($line->product_id ?? null);
        $variantId = $this->nullableInt($line->product_variant_id ?? null);

        if (! $companyId || ! $productId) {
            $movement = $this->movement($this->nullableInt($line->stock_movement_id ?? null));

            if (! $companyId) {
                $companyId = $this->nullableInt($movement->company_id ?? null);
            }

            if (! $productId) {
                $productId = $this->nullableInt($movement->product_id ?? null);
            }
        }

        if (! $productId && $variantId) {
            $productId = $variantId;
        }

        $resolved = $this->resolver->resolve($companyId, $productId, $variantId);
        $unitCost = $this->nullableFloat($line->unit_cost ?? null);
        $quantity = $this->movementQuantity($line);

        if (
            in_array('total_cost', $columns, true)
            && property_exists($line, 'total_cost')
            && $line->total_cost === null
            && $unitCost !== null
            && $quantity !== null
        ) {
            $updates['total_cost'] = round(abs($quantity) * $unitCost, 6);
        }

        if (
            in_array('costing_method', $columns, true)
            && property_exists($line, 'costing_method')
            && empty($line->costing_method)
        ) {
            $updates['costing_method'] = $resolved['method'] ?? InventoryCostingMethodResolver::FALLBACK_METHOD;
        }

        if (
            in_array('cost_source', $columns, true)
            && property_exists($line, 'cost_source')
            && empty($line->cost_source)
        ) {
            $updates['cost_source'] = $this->costSource($line, $resolved);
        }

        return $updates;
    }

    protected function movementQuantity(object $line): ?float
    {
        foreach (['done_quantity', 'quantity', 'requested_quantity'] as $column) {
            if (property_exists($line, $column) && $line->{$column} !== null) {
                return (float) $line->{$column};
            }
        }

        return null;
    }

    protected function costSource(object $line, array $resolved): string
    {
        $sourceType = strtolower(trim((string) ($line->source_type ?? '')));
        $sourceLineType = strtolower(trim((string) ($line->source_line_type ?? '')));

        if ($sourceType === 'purchase_receipt' || $sourceLineType === 'purchase_receipt_line') {
            return 'purchase_receipt.unit_cost_without_tax';
        }

        if ($sourceType === 'sale_delivery' || $sourceLineType === 'sale_delivery_line') {
            return 'sale_delivery.unit_cost';
        }

        if ($sourceType === 'pos_order' || $sourceLineType === 'pos_order_line') {
            return 'pos_order.average_cost_at_sale';
        }

        if ($sourceType === 'stock_adjustment' || $sourceLineType === 'stock_adjustment_line') {
            return 'stock_adjustment.unit_cost';
        }

        $methodSource = $resolved['source'] ?? 'system';

        return 'legacy.stock_movement_line.unit_cost:' . $methodSource;
    }

    protected function movement(?int $movementId): ?object
    {
        if (! $movementId || ! Schema::hasTable('stock_movements')) {
            return null;
        }

        return DB::table('stock_movements')
            ->where('id', $movementId)
            ->first();
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    protected function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
