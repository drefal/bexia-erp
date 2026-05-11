<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesPriceListUpdater
{
    public static function shouldShowUpdatePricesButton(object|int|null $order): bool
    {
        return false;
    }

    public static function canUpdatePrices(object|int|null $order): array
    {
        $order = static::resolveOrder($order);

        if (! $order) {
            return ['ok' => false, 'message' => 'Guarda primero el encabezado de la cotización.'];
        }

        $status = (string) ($order->status ?? '');
        $approvalStatus = (string) ($order->margin_approval_status ?? '');
        $invoiceStatus = (string) ($order->invoice_status ?? 'not_invoiced');
        $paymentStatus = (string) ($order->payment_status ?? 'unpaid');
        $deliveredQty = (float) ($order->delivered_total_quantity ?? 0);

        if (in_array($status, ['cancelled', 'canceled', 'delivered'], true)) {
            return ['ok' => false, 'message' => 'No se pueden actualizar precios en documentos cancelados o entregados.'];
        }

        if ($approvalStatus === 'pending') {
            return ['ok' => false, 'message' => 'No se pueden actualizar precios mientras está pendiente de aprobación.'];
        }

        if ($deliveredQty > 0 || in_array($status, ['partially_delivered', 'partial_delivered'], true)) {
            return ['ok' => false, 'message' => 'No se pueden actualizar precios porque la orden ya tiene entregas.'];
        }

        if (! in_array($invoiceStatus, ['not_invoiced', '', null], true)) {
            return ['ok' => false, 'message' => 'No se pueden actualizar precios porque la orden ya tiene facturación.'];
        }

        if (! in_array($paymentStatus, ['unpaid', '', null], true)) {
            return ['ok' => false, 'message' => 'No se pueden actualizar precios porque la orden ya tiene pagos.'];
        }

        if (! in_array($status, ['draft', 'confirmed'], true)) {
            return ['ok' => false, 'message' => 'El estado actual no permite actualización de precios.'];
        }

        return ['ok' => true, 'message' => 'Puede actualizar precios.'];
    }

    public static function updateFromSelectedPriceList(object|int $order, int $selectedPriceListId): array
    {
        $order = static::resolveOrder($order);

        if (! $order) {
            throw new \RuntimeException('No se encontró la cotización/orden.');
        }

        if ($selectedPriceListId <= 0) {
            throw new \RuntimeException('Selecciona una lista de precios válida.');
        }

        $permission = static::canUpdatePrices($order);

        if (! ($permission['ok'] ?? false)) {
            throw new \RuntimeException($permission['message'] ?? 'No se pueden actualizar precios.');
        }

        $changes = [];
        $skipped = [];

        DB::transaction(function () use ($order, $selectedPriceListId, &$changes, &$skipped): void {
            $lines = Schema::hasTable('sales_order_lines')
                ? DB::table('sales_order_lines')->where('sales_order_id', $order->id)->orderBy('id')->get()
                : collect();

            foreach ($lines as $line) {
                $productId = (int) ($line->product_id ?? 0);
                $variantId = (int) ($line->variant_id ?? 0);

                $newPrice = static::resolvePrice($selectedPriceListId, $productId, $variantId);

                if ($newPrice === null) {
                    $skipped[] = [
                        'line_id' => $line->id,
                        'product' => $line->product_label ?? ('Producto #' . $productId),
                        'reason' => 'Sin precio calculable',
                    ];
                    continue;
                }

                $oldPrice = (float) ($line->unit_price_without_tax ?? 0);

                if (round($oldPrice, 6) === round($newPrice, 6)) {
                    continue;
                }

                static::updateLinePrice($line, $newPrice);

                $changes[] = [
                    'line_id' => $line->id,
                    'product' => $line->product_label ?? ('Producto #' . $productId),
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                ];
            }

            static::recalculateOrderTotals((int) $order->id);

            DB::table('sales_orders')
                ->where('id', $order->id)
                ->update(static::filterColumns('sales_orders', [
                    'price_list_id' => $selectedPriceListId,
                    'price_list_applied_id' => $selectedPriceListId,
                    'price_list_applied_at' => now(),
                    'updated_at' => now(),
                ]));

            static::reevaluateApprovalAfterPriceUpdate((int) $order->id, $changes, $skipped);
        });

        return [
            'changed_count' => count($changes),
            'skipped_count' => count($skipped),
            'changes' => $changes,
            'skipped' => $skipped,
            'message' => static::summaryMessage($changes, $skipped),
        ];
    }

    public static function priceListOptions(): array
    {
        $table = static::priceListTable();

        if (! $table) {
            return [];
        }

        $columns = Schema::getColumnListing($table);
        $nameColumn = static::firstExistingColumn($columns, ['name', 'title', 'description', 'code']);

        if (! $nameColumn) {
            return [];
        }

        $query = DB::table($table);

        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', true);
        }

        if (in_array('active', $columns, true)) {
            $query->where('active', true);
        }

        $rows = $query
            ->select(['id', $nameColumn])
            ->orderBy($nameColumn)
            ->orderByDesc('id')
            ->get();

        $options = [];
        $seen = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row->{$nameColumn} ?? ''));

            if ($name === '') {
                continue;
            }

            $key = mb_strtolower(preg_replace('/\s+/', ' ', $name));

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $options[(int) $row->id] = $name;
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    protected static function resolvePrice(int $priceListId, int $productId, int $variantId = 0, array $visited = []): ?float
    {
        if ($priceListId <= 0 || in_array($priceListId, $visited, true)) {
            return null;
        }

        $visited[] = $priceListId;

        $direct = static::findDirectPrice($priceListId, $productId, $variantId);

        if ($direct !== null) {
            return $direct;
        }

        $table = static::priceListTable();

        if (! $table) {
            return null;
        }

        $list = DB::table($table)->where('id', $priceListId)->first();

        if (! $list) {
            return null;
        }

        $columns = Schema::getColumnListing($table);
        $text = static::rowText($list, $columns);

        $basePrice = null;

        $baseListColumn = static::firstExistingColumn($columns, [
            'base_price_list_id',
            'parent_price_list_id',
            'source_price_list_id',
            'price_list_base_id',
            'derived_from_price_list_id',
            'reference_price_list_id',
            'base_sales_price_list_id',
            'source_sales_price_list_id',
        ]);

        if ($baseListColumn && ! empty($list->{$baseListColumn})) {
            $basePrice = static::resolvePrice((int) $list->{$baseListColumn}, $productId, $variantId, $visited);
        }

        if ($basePrice === null && static::textContains($text, ['cost', 'costo', 'coste'])) {
            $basePrice = static::productBasePrice($productId, $variantId, 'cost');
        }

        if ($basePrice === null) {
            $basePrice = static::productBasePrice($productId, $variantId, 'sale');
        }

        if ($basePrice === null) {
            return null;
        }

        $percent = static::firstNumericValue($list, $columns, [
            'adjustment_percent',
            'adjustment_percentage',
            'percentage',
            'percent',
            'formula_percentage',
            'discount_percent',
            'discount_percentage',
            'markup_percent',
            'increase_percent',
            'factor_percent',
        ]);

        $factor = static::firstNumericValue($list, $columns, [
            'factor',
            'multiplier',
            'formula_factor',
        ]);

        $amount = static::firstNumericValue($list, $columns, [
            'adjustment_amount',
            'amount_delta',
            'fixed_adjustment',
            'formula_amount',
        ]);

        if ($factor !== null && $factor > 0 && $percent === null) {
            return round($basePrice * $factor, 6);
        }

        if ($amount !== null && $percent === null) {
            return static::textContains($text, ['discount', 'descuento', 'minus', 'resta', 'subtract', '-'])
                ? round(max(0, $basePrice - $amount), 6)
                : round($basePrice + $amount, 6);
        }

        if ($percent === null) {
            return round($basePrice, 6);
        }

        if (static::textContains($text, ['discount', 'descuento', 'minus', 'resta', 'subtract', 'rebaja'])) {
            return round(max(0, $basePrice * (1 - ($percent / 100))), 6);
        }

        return round($basePrice * (1 + ($percent / 100)), 6);
    }

    protected static function priceListTable(): ?string
    {
        foreach (['sales_price_lists', 'price_lists'] as $table) {
            if (Schema::hasTable($table)) {
                return $table;
            }
        }

        return null;
    }

    protected static function findDirectPrice(int $priceListId, int $productId, int $variantId = 0): ?float
    {
        foreach (['sales_price_list_items', 'price_list_items', 'price_list_lines'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $price = static::findDirectPriceInTable($table, $priceListId, $productId, $variantId);

            if ($price !== null) {
                return $price;
            }
        }

        return null;
    }

    protected static function findDirectPriceInTable(string $table, int $priceListId, int $productId, int $variantId = 0): ?float
    {
        $columns = Schema::getColumnListing($table);

        $priceListColumn = static::firstExistingColumn($columns, ['sales_price_list_id', 'price_list_id', 'pricelist_id']);
        $priceColumn = static::firstExistingColumn($columns, ['price_without_tax', 'unit_price_without_tax', 'fixed_price', 'price', 'sale_price', 'list_price', 'amount']);
        $productColumn = static::firstExistingColumn($columns, ['product_id', 'item_id']);
        $variantColumn = static::firstExistingColumn($columns, ['variant_id', 'product_variant_id']);

        if (! $priceListColumn || ! $priceColumn) {
            return null;
        }

        $query = DB::table($table)->where($priceListColumn, $priceListId);

        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', true);
        }

        if (in_array('active', $columns, true)) {
            $query->where('active', true);
        }

        $candidates = [];

        if ($variantId > 0 && $variantColumn) {
            $candidates[] = [$variantColumn, $variantId];
        }

        if ($variantId > 0 && $productColumn) {
            $candidates[] = [$productColumn, $variantId];
        }

        if ($productId > 0 && $productColumn) {
            $candidates[] = [$productColumn, $productId];
        }

        foreach ($candidates as [$column, $id]) {
            $row = (clone $query)
                ->where($column, $id)
                ->orderByDesc(static::sortColumn($columns))
                ->first();

            if ($row && is_numeric($row->{$priceColumn} ?? null)) {
                return (float) $row->{$priceColumn};
            }
        }

        return null;
    }

    protected static function productBasePrice(int $productId, int $variantId, string $source): ?float
    {
        if (! Schema::hasTable('products')) {
            return null;
        }

        $lookupIds = array_values(array_filter([$variantId, $productId]));

        foreach ($lookupIds as $id) {
            $product = DB::table('products')->where('id', $id)->first();

            if (! $product) {
                continue;
            }

            $columns = $source === 'cost'
                ? ['standard_cost', 'cost_without_tax', 'cost_price', 'cost', 'last_purchase_cost', 'purchase_cost']
                : ['sale_price_without_tax', 'price_without_tax', 'list_price', 'sale_price', 'price', 'sales_price'];

            foreach ($columns as $column) {
                if (isset($product->{$column}) && is_numeric($product->{$column}) && (float) $product->{$column} > 0) {
                    return (float) $product->{$column};
                }
            }
        }

        return null;
    }

    protected static function updateLinePrice(object $line, float $newPrice): void
    {
        if (! Schema::hasTable('sales_order_lines')) {
            return;
        }

        $quantity = (float) ($line->quantity ?? 0);
        $taxRate = (float) ($line->tax_rate ?? 0);

        $subtotal = round($quantity * $newPrice, 6);
        $tax = round($subtotal * ($taxRate / 100), 6);
        $total = round($subtotal + $tax, 6);
        $unitPriceWithTax = round($newPrice * (1 + ($taxRate / 100)), 6);

        $margin = static::marginData(
            (int) ($line->product_id ?? 0),
            (int) ($line->variant_id ?? $line->product_variant_id ?? 0),
            $newPrice,
            $quantity
        );

        $data = [
            // Precio unitario sin impuestos
            'unit_price_without_tax' => $newPrice,
            'price_without_tax' => $newPrice,
            'unit_price' => $newPrice,
            'price' => $newPrice,
            'sale_price' => $newPrice,

            // Precio unitario con impuestos
            'unit_price_with_tax' => $unitPriceWithTax,
            'price_with_tax' => $unitPriceWithTax,

            // Subtotal / total sin impuestos
            'line_total_without_tax' => $subtotal,
            'total_without_tax' => $subtotal,
            'subtotal_without_tax' => $subtotal,
            'line_subtotal' => $subtotal,
            'subtotal' => $subtotal,

            // Impuestos
            'line_tax' => $tax,
            'tax_amount' => $tax,
            'tax_total' => $tax,
            'amount_tax' => $tax,

            // Total con impuestos
            'line_total_with_tax' => $total,
            'total_with_tax' => $total,
            'line_total' => $total,
            'amount_total' => $total,
            'total' => $total,

            // Margen
            'margin_status' => $margin['status'],
            'margin_amount' => $margin['unit_amount'],
            'margin_percent' => $margin['percent'],
            'gross_margin_amount' => $margin['total_amount'],
            'gross_margin_percent' => $margin['percent'],

            'updated_at' => now(),
        ];

        DB::table('sales_order_lines')
            ->where('id', $line->id)
            ->update(static::filterColumns('sales_order_lines', $data));
    }


    protected static function recalculateOrderTotals(int $orderId): void
    {
        if (! Schema::hasTable('sales_order_lines') || ! Schema::hasTable('sales_orders')) {
            return;
        }

        $totals = DB::table('sales_order_lines')
            ->where('sales_order_id', $orderId)
            ->selectRaw('
                COALESCE(SUM(line_total_without_tax), 0) as subtotal,
                COALESCE(SUM(line_tax), 0) as tax,
                COALESCE(SUM(line_total_with_tax), 0) as total
            ')
            ->first();

        $subtotal = round((float) ($totals->subtotal ?? 0), 6);
        $tax = round((float) ($totals->tax ?? 0), 6);
        $total = round((float) ($totals->total ?? 0), 6);

        DB::table('sales_orders')
            ->where('id', $orderId)
            ->update(static::filterColumns('sales_orders', [
                // Totales sin impuestos
                'total_without_tax' => $subtotal,
                'subtotal_without_tax' => $subtotal,
                'amount_untaxed' => $subtotal,
                'subtotal' => $subtotal,

                // Impuestos
                'tax_total' => $tax,
                'amount_tax' => $tax,
                'total_tax' => $tax,

                // Totales con impuestos
                'total_with_tax' => $total,
                'amount_total' => $total,
                'total' => $total,

                'updated_at' => now(),
            ]));
    }

    protected static function reevaluateApprovalAfterPriceUpdate(int $orderId, array $changes, array $skipped): void
    {
        $order = static::resolveOrder($orderId);

        if (! $order) {
            return;
        }

        if (class_exists(\App\Support\SalesApprovalWorkflow::class)) {
            \App\Support\SalesApprovalWorkflow::logEvent(
                $order,
                'prices_updated_from_price_list',
                'Precios actualizados desde lista',
                static::historyDescription($changes, $skipped),
                ['changes' => array_slice($changes, 0, 50), 'skipped' => array_slice($skipped, 0, 50)],
                auth()->id()
            );
        }

        if ((string) ($order->status ?? '') === 'confirmed') {
            if (class_exists(\App\Support\SalesApprovalWorkflow::class)) {
                \App\Support\SalesApprovalWorkflow::markOrderChangedAfterApproval(
                    $orderId,
                    'Se actualizaron precios desde la lista de precios.'
                );
            }

            return;
        }

        if ((string) ($order->status ?? '') === 'draft') {
            $summary = class_exists(\App\Support\SalesApprovalWorkflow::class)
                ? \App\Support\SalesApprovalWorkflow::approvalRequirementSummary($order)
                : ['requires_approval' => false, 'reason' => null];

            DB::table('sales_orders')
                ->where('id', $orderId)
                ->update(static::filterColumns('sales_orders', [
                    'margin_approval_required' => (bool) ($summary['requires_approval'] ?? false),
                    'margin_approval_status' => (bool) ($summary['requires_approval'] ?? false) ? 'required' : 'not_required',
                    'margin_approval_reason' => $summary['reason'] ?? null,
                    'margin_approval_requested_at' => null,
                    'margin_approved_by_user_id' => null,
                    'margin_approved_at' => null,
                    'margin_rejected_by_user_id' => null,
                    'margin_rejected_at' => null,
                    'margin_rejection_reason' => null,
                    'updated_at' => now(),
                ]));
        }
    }

    protected static function marginData(int $productId, int $variantId, float $price, float $quantity = 1): array
    {
        $cost = static::productBasePrice($productId, $variantId, 'cost') ?? 0.0;

        $unitAmount = round($price - $cost, 6);
        $totalAmount = round($unitAmount * $quantity, 6);
        $percent = $price > 0 ? round(($unitAmount / $price) * 100, 6) : 0;

        $status = 'healthy';

        if ($cost <= 0) {
            $status = 'no_cost';
        } elseif ($price < $cost) {
            $status = 'danger';
        } elseif ($percent < 10) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'unit_amount' => $unitAmount,
            'total_amount' => $totalAmount,
            'amount' => $unitAmount,
            'percent' => $percent,
        ];
    }

    protected static function historyDescription(array $changes, array $skipped): string
    {
        $parts = [];

        if (count($changes) > 0) {
            $parts[] = count($changes) . ' línea(s) actualizada(s).';

            foreach (array_slice($changes, 0, 8) as $change) {
                $parts[] = ($change['product'] ?? 'Producto')
                    . ': $' . number_format((float) $change['old_price'], 4)
                    . ' → $' . number_format((float) $change['new_price'], 4);
            }
        } else {
            $parts[] = 'No hubo cambios de precio.';
        }

        if (count($skipped) > 0) {
            $parts[] = count($skipped) . ' línea(s) sin precio calculable.';
        }

        return implode("\n", $parts);
    }

    protected static function summaryMessage(array $changes, array $skipped): string
    {
        return count($changes) . ' línea(s) actualizada(s). '
            . count($skipped) . ' línea(s) sin precio calculable.';
    }

    protected static function resolveOrder(object|int|null $order): ?object
    {
        if (! $order) {
            return null;
        }

        if (is_object($order)) {
            if (! empty($order->id) && Schema::hasTable('sales_orders')) {
                return DB::table('sales_orders')->where('id', $order->id)->first() ?: $order;
            }

            return $order;
        }

        return Schema::hasTable('sales_orders')
            ? DB::table('sales_orders')->where('id', (int) $order)->first()
            : null;
    }

    protected static function rowText(object $row, array $columns): string
    {
        $parts = [];

        foreach ($columns as $column) {
            $value = $row->{$column} ?? null;

            if (is_string($value) && trim($value) !== '') {
                $parts[] = mb_strtolower($column . ' ' . $value);
            }
        }

        return implode(' ', $parts);
    }

    protected static function textContains(string $text, array $needles): bool
    {
        $text = mb_strtolower($text);

        foreach ($needles as $needle) {
            if (str_contains($text, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    protected static function firstNumericValue(object $row, array $columns, array $candidates): ?float
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true) && isset($row->{$candidate}) && is_numeric($row->{$candidate})) {
                return (float) $row->{$candidate};
            }
        }

        return null;
    }

    protected static function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    protected static function sortColumn(array $columns): string
    {
        foreach (['priority', 'sort_order', 'id'] as $column) {
            if (in_array($column, $columns, true)) {
                return $column;
            }
        }

        return $columns[0] ?? 'id';
    }

    protected static function filterColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);

        return array_filter(
            $data,
            fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }
    public static function priceListName(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        $table = static::priceListTable();

        if (! $table) {
            return null;
        }

        $columns = Schema::getColumnListing($table);
        $nameColumn = static::firstExistingColumn($columns, ['name', 'title', 'description', 'code']);

        if (! $nameColumn) {
            return null;
        }

        $row = DB::table($table)->where('id', $id)->first();

        return $row ? (string) ($row->{$nameColumn} ?? null) : null;
    }


}
