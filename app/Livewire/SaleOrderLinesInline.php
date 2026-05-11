<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;
use Livewire\Component;

class SaleOrderLinesInline extends Component
{
    public int $saleOrderId;

    public array $lines = [];

    public bool $canEditLines = true;

    public string $productSearch = '';
    public ?int $productId = null;
    public ?int $variantId = null;
    public string $unitLabel = 'Pieza';
    public string $quantity = '1';
    public string $unitPriceWithoutTax = '0';
    public string $taxRate = '16';

    public ?int $editingLineId = null;

    public function mount(int $saleOrderId): void
    {
        $this->saleOrderId = $saleOrderId;
        $this->loadLines();
    }


    public function editLine(int $lineId): void
    {
        if (! $this->ensureLinesEditable()) {
            return;
        }

        if ($lineId <= 0 || ! Schema::hasTable('sales_order_lines')) {
            return;
        }

        $line = DB::table('sales_order_lines')
            ->where('sales_order_id', $this->saleOrderId)
            ->where('id', $lineId)
            ->first();

        if (! $line) {
            return;
        }

        $this->editingLineId = $lineId;
        $this->productId = ! empty($line->product_id) ? (int) $line->product_id : null;
        $this->variantId = ! empty($line->variant_id) ? (int) $line->variant_id : null;
        $this->productSearch = (string) ($line->product_label ?? '');
        $this->unitLabel = (string) ($line->unit_label ?? 'Pieza');
        $this->quantity = (string) ((float) ($line->quantity ?? 1));
        $this->unitPriceWithoutTax = (string) ((float) ($line->unit_price_without_tax ?? 0));
        $this->taxRate = (string) ((float) ($line->tax_rate ?? 0));
    }

    public function cancelEditLine(): void
    {
        $this->editingLineId = null;

        if (method_exists($this, 'resetLineForm')) {
            $this->resetLineForm();
            return;
        }

        $this->productSearch = '';
        $this->productId = null;
        $this->variantId = null;
        $this->unitLabel = 'Pieza';
        $this->quantity = '1';
        $this->unitPriceWithoutTax = '0';
        $this->taxRate = '16';
    }

    public function updateLine(): void
    {
        if (! $this->editingLineId || ! Schema::hasTable('sales_order_lines')) {
            return;
        }

        $line = DB::table('sales_order_lines')
            ->where('sales_order_id', $this->saleOrderId)
            ->where('id', $this->editingLineId)
            ->first();

        if (! $line) {
            $this->editingLineId = null;
            return;
        }

        $quantity = max(0, (float) str_replace(',', '', (string) $this->quantity));
        $price = max(0, (float) str_replace(',', '', (string) $this->unitPriceWithoutTax));
        $taxRate = max(0, (float) str_replace(',', '', (string) $this->taxRate));

        $subtotal = round($quantity * $price, 6);
        $tax = round($subtotal * ($taxRate / 100), 6);
        $total = round($subtotal + $tax, 6);

        $productLabel = $this->safeProductLabel((int) ($this->productId ?? 0));
        $variantLabel = $this->safeVariantLabel((int) ($this->variantId ?? 0));

        $margin = $this->safeMarginData((int) ($this->productId ?? 0), (int) ($this->variantId ?? 0), $price);

        $data = [
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'product_label' => $productLabel ?: ($line->product_label ?? null),
            'variant_label' => $variantLabel,
            'unit_label' => $this->unitLabel ?: 'Pieza',
            'quantity' => $quantity,
            'unit_price_without_tax' => $price,
            'tax_rate' => $taxRate,
            'line_total_without_tax' => $subtotal,
            'line_tax' => $tax,
            'line_total_with_tax' => $total,
            'margin_status' => $margin['status'],
            'margin_amount' => $margin['amount'],
            'margin_percent' => $margin['percent'],
            'updated_at' => now(),
        ];

        $columns = Schema::getColumnListing('sales_order_lines');

        $data = array_filter(
            $data,
            fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );

        DB::table('sales_order_lines')
            ->where('id', $this->editingLineId)
            ->update($data);

        $this->editingLineId = null;

        if (method_exists($this, 'recalculateOrderTotals')) {
            $this->recalculateOrderTotals();
        $this->markOrderChangedAfterApproval('Se agregó una línea a la orden de venta.');
        }

        if (method_exists($this, 'loadLines')) {
            $this->loadLines();
        }

        if (method_exists($this, 'resetLineForm')) {
            $this->resetLineForm();
        }

        $this->markOrderChangedAfterApproval('Se editó una línea de la orden de venta.');
    }

    protected function safeProductLabel(int $productId): ?string
    {
        if ($productId <= 0 || ! Schema::hasTable('products')) {
            return null;
        }

        $product = DB::table('products')->where('id', $productId)->first();

        if (! $product) {
            return null;
        }

        $code = trim((string) ($product->code ?? $product->sku ?? ''));
        $name = trim((string) ($product->name ?? $product->description ?? ''));

        if ($code !== '' && $name !== '') {
            return "{$code} - {$name}";
        }

        return $name !== '' ? $name : ($code !== '' ? $code : ('Producto #' . $productId));
    }

    protected function safeVariantLabel(int $variantId): ?string
    {
        if ($variantId <= 0 || ! Schema::hasTable('products')) {
            return null;
        }

        $variant = DB::table('products')->where('id', $variantId)->first();

        if (! $variant) {
            return null;
        }

        foreach (['variant_name', 'variant_label', 'name'] as $column) {
            if (isset($variant->{$column}) && trim((string) $variant->{$column}) !== '') {
                return trim((string) $variant->{$column});
            }
        }

        return 'Variante #' . $variantId;
    }

    protected function safeMarginData(int $productId, int $variantId, float $price): array
    {
        $cost = 0.0;
        $lookupId = $variantId > 0 ? $variantId : $productId;

        if ($lookupId > 0 && Schema::hasTable('products')) {
            $product = DB::table('products')->where('id', $lookupId)->first();

            if ($product) {
                foreach (['standard_cost', 'cost_without_tax', 'cost_price', 'cost', 'last_purchase_cost'] as $column) {
                    if (isset($product->{$column}) && (float) $product->{$column} > 0) {
                        $cost = (float) $product->{$column};
                        break;
                    }
                }
            }
        }

        $amount = round($price - $cost, 6);
        $percent = $price > 0 ? round(($amount / $price) * 100, 6) : 0;

        $status = 'healthy';

        if ($cost > 0 && $price < $cost) {
            $status = 'danger';
        } elseif ($cost > 0 && $percent < 10) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'amount' => $amount,
            'percent' => $percent,
        ];
    }



    protected function markOrderChangedAfterApproval(string $description): void
    {
        if (class_exists(\App\Support\SalesApprovalWorkflow::class)) {
            \App\Support\SalesApprovalWorkflow::markOrderChangedAfterApproval(
                $this->saleOrderId,
                $description
            );
        }
    }



    protected function updateLineEditability(): void
    {
        $this->canEditLines = class_exists(\App\Support\SalesApprovalWorkflow::class)
            ? \App\Support\SalesApprovalWorkflow::canEditSalesOrderLines($this->saleOrderId)
            : true;
    }

    protected function ensureLinesEditable(): bool
    {
        $this->updateLineEditability();

        return $this->canEditLines;
    }



    #[On('sales-order-prices-applied')]
    public function refreshAfterPriceListApplied(?int $saleOrderId = null): void
    {
        if ($saleOrderId !== null && (int) $saleOrderId !== $this->saleOrderId) {
            return;
        }

        if (method_exists($this, 'loadLines')) {
            $this->loadLines();
        }

        if (method_exists($this, 'recalculateOrderTotals')) {
            $this->recalculateOrderTotals();
        }
    }


    #[On('sales-order-prices-applied')]
    public function refreshAfterPricesApplied(?int $saleOrderId = null): void
    {
        if ($saleOrderId !== null && (int) $saleOrderId !== (int) $this->saleOrderId) {
            return;
        }

        if (method_exists($this, 'loadLines')) {
            $this->loadLines();
        }

        if (method_exists($this, 'recalculateOrderTotals')) {
            $this->recalculateOrderTotals();
        }
    }

    public function render()
    {
        return view('livewire.sale-order-lines-inline', [
            'productResults' => $this->productResults(),
            'variantOptions' => $this->variantOptions(),
            'totals' => $this->totals(),
        ]);
    }

    public function updatedProductSearch(): void
    {
        if ($this->productId && trim($this->productSearch) === '') {
            $this->resetProductSelection();
        }
    }

    public function selectProduct(int $productId): void
    {
        $this->productId = $productId;
        $this->variantId = null;

        $product = $this->productById($productId);

        if (! $product) {
            $this->resetProductSelection();
            return;
        }

        $this->productSearch = $this->productLabel($product);
        $this->unitLabel = $this->unitLabelFor($product);

        $priceListId = $this->saleOrder()?->price_list_id ? (int) $this->saleOrder()->price_list_id : 0;
        $price = $this->priceForProduct($productId, 0, $priceListId, (float) ($product->sale_price ?? 0), (float) $this->quantity);

        $this->unitPriceWithoutTax = (string) round($price, 6);
        $this->taxRate = (string) $this->saleTaxRateFor($product, null);
    }

    public function updatedVariantId(): void
    {
        if (! $this->productId) {
            return;
        }

        $product = $this->productById($this->productId);
        $variant = $this->variantId ? $this->productById((int) $this->variantId) : null;

        $priceListId = $this->saleOrder()?->price_list_id ? (int) $this->saleOrder()->price_list_id : 0;
        $fallback = $variant && $variant->sale_price !== null
            ? (float) $variant->sale_price
            : (float) ($product->sale_price ?? 0);

        $this->unitPriceWithoutTax = (string) round(
            $this->priceForProduct((int) $this->productId, (int) ($this->variantId ?? 0), $priceListId, $fallback, (float) $this->quantity),
            6
        );

        $this->taxRate = (string) $this->saleTaxRateFor($product, $variant);
    }

    public function updatedQuantity(): void
    {
        if (! $this->productId) {
            return;
        }

        $this->updatedVariantId();
    }

    public function addLine(): void
    {
        if (! $this->ensureLinesEditable()) {
            return;
        }

        $saleOrder = $this->saleOrder();

        if (! $saleOrder || ! $this->productId) {
            return;
        }

        $product = $this->productById((int) $this->productId);
        $variant = $this->variantId ? $this->productById((int) $this->variantId) : null;

        if (! $product) {
            return;
        }

        $qty = max((float) $this->quantity, 0);
        $price = max((float) $this->unitPriceWithoutTax, 0);
        $taxRate = max((float) $this->taxRate, 0);

        if ($qty <= 0) {
            return;
        }

        $calc = $this->calculateLine($qty, $price, $taxRate, (int) $this->productId, (int) ($this->variantId ?? 0));

        $data = [
            'company_id' => (int) ($saleOrder->company_id ?? 0),
            'sales_order_id' => $this->saleOrderId,
            'product_id' => (int) $this->productId,
            'product_variant_id' => $this->variantId ?: null,
            'product_label' => $this->productLabel($product),
            'variant_label' => $variant ? $this->variantLabel($variant) : null,
            'unit_label' => $this->unitLabel,
            'quantity' => $qty,
            'unit_price_without_tax' => $price,
            'unit_price_with_tax' => $calc['unit_price_with_tax'],
            'tax_rate' => $taxRate,
            'line_total_without_tax' => $calc['line_total_without_tax'],
            'line_tax' => $calc['line_tax'],
            'line_total_with_tax' => $calc['line_total_with_tax'],
            'delivered_quantity' => 0,
            'delivery_status' => 'pending',
            'estimated_unit_cost_without_tax' => $calc['unit_cost'],
            'gross_margin_amount' => $calc['gross_margin_amount'],
            'gross_margin_percent' => $calc['gross_margin_percent'],
            'margin_status' => $calc['margin_status'],
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('sales_order_lines')->insert($this->filterColumns('sales_order_lines', $data));

        $this->resetProductSelection();
        $this->loadLines();
        $this->recalculateOrderTotals();
    }





    public function cancelEdit(): void
    {
        $this->editingLineId = null;
        $this->resetProductSelection();
    }

    public function deleteLine(int $lineId): void
    {
        if (! $this->ensureLinesEditable()) {
            return;
        }

        DB::table('sales_order_lines')
            ->where('sales_order_id', $this->saleOrderId)
            ->where('id', $lineId)
            ->delete();

        $this->loadLines();
        $this->recalculateOrderTotals();
        $this->markOrderChangedAfterApproval('Se eliminó una línea de la orden de venta.');
    }

    protected function loadLines(): void
    {
        $this->lines = DB::table('sales_order_lines')
            ->where('sales_order_id', $this->saleOrderId)
            ->orderBy('id')
            ->get()
            ->map(fn ($line): array => (array) $line)
            ->all();
    }

    protected function resetProductSelection(): void
    {
        $this->productSearch = '';
        $this->productId = null;
        $this->variantId = null;
        $this->unitLabel = 'Pieza';
        $this->quantity = '1';
        $this->unitPriceWithoutTax = '0';
        $this->taxRate = '16';
    }

    protected function saleOrder(): ?object
    {
        return DB::table('sales_orders')->where('id', $this->saleOrderId)->first();
    }

    protected function productResults(): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $saleOrder = $this->saleOrder();
        $companyId = (int) ($saleOrder->company_id ?? 0);
        $search = trim($this->productSearch);

        if ($this->productId && $search === $this->productSearch) {
            return [];
        }

        $query = DB::table('products')
            ->when($companyId > 0 && Schema::hasColumn('products', 'company_id'), fn ($q) => $q->where('company_id', $companyId))
            ->when(Schema::hasColumn('products', 'is_active'), fn ($q) => $q->where('is_active', true))
            ->when(Schema::hasColumn('products', 'can_be_sold'), fn ($q) => $q->where('can_be_sold', true))
            ->where(function ($q) {
                if (Schema::hasColumn('products', 'is_variant')) {
                    $q->whereNull('is_variant')->orWhere('is_variant', false);
                }
            });

        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';

            $query->where(function ($q) use ($like) {
                foreach (['internal_reference', 'sku', 'barcode', 'name', 'model', 'brand', 'color', 'product_line'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $q->orWhereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", [$like]);
                    }
                }
            });
        }

        return $query
            ->orderByRaw("COALESCE(internal_reference, sku, barcode, '')")
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn ($product): array => [
                'id' => (int) $product->id,
                'label' => $this->productLabel($product),
                'hint' => trim((string) ($product->barcode ?? $product->sku ?? '')),
            ])
            ->all();
    }

    protected function variantOptions(): array
    {
        if (! $this->productId || ! Schema::hasTable('products')) {
            return [];
        }

        return DB::table('products')
            ->where('parent_product_id', (int) $this->productId)
            ->when(Schema::hasColumn('products', 'is_variant'), fn ($q) => $q->where('is_variant', true))
            ->when(Schema::hasColumn('products', 'is_active'), fn ($q) => $q->where('is_active', true))
            ->orderByRaw("COALESCE(variant_group, '')")
            ->orderByRaw("COALESCE(variant_value, variant_name, name, '')")
            ->get()
            ->mapWithKeys(fn ($variant): array => [(int) $variant->id => $this->variantLabel($variant)])
            ->all();
    }

    protected function productById(int $id): ?object
    {
        if ($id <= 0 || ! Schema::hasTable('products')) {
            return null;
        }

        return DB::table('products')->where('id', $id)->first();
    }

    protected function productLabel(object $product): string
    {
        $ref = trim((string) ($product->internal_reference ?? $product->sku ?? $product->barcode ?? ''));
        $name = trim((string) ($product->name ?? ('Producto #' . $product->id)));

        return trim(($ref !== '' ? $ref . ' - ' : '') . $name);
    }

    protected function variantLabel(object $variant): string
    {
        $group = trim((string) ($variant->variant_group ?? ''));
        $value = '';

        foreach (['variant_value', 'variant_name', 'color', 'name'] as $column) {
            if (property_exists($variant, $column) && trim((string) ($variant->{$column} ?? '')) !== '') {
                $value = trim((string) $variant->{$column});
                break;
            }
        }

        if ($value === '') {
            $value = 'Variante #' . $variant->id;
        }

        return $group !== '' ? "{$group}: {$value}" : $value;
    }

    protected function unitLabelFor(object $product): string
    {
        foreach (['sale_unit_label', 'unit_label', 'uom_name', 'uom', 'purchase_unit_label'] as $column) {
            if (property_exists($product, $column) && trim((string) ($product->{$column} ?? '')) !== '') {
                return trim((string) $product->{$column});
            }
        }

        return 'Pieza';
    }

    protected function saleTaxRateFor(?object $product, ?object $variant): float
    {
        if ($variant && property_exists($variant, 'sale_tax_rate') && $variant->sale_tax_rate !== null) {
            return (float) $variant->sale_tax_rate;
        }

        if ($product && property_exists($product, 'sale_tax_rate') && $product->sale_tax_rate !== null) {
            return (float) $product->sale_tax_rate;
        }

        return 16.0;
    }

    protected function priceForProduct(int $productId, int $variantId, int $priceListId, float $fallback, float $qty = 1, array $visited = []): float
    {
        if ($priceListId <= 0 || ! Schema::hasTable('sales_price_lists')) {
            return $fallback;
        }

        if (in_array($priceListId, $visited, true)) {
            return $fallback;
        }

        $visited[] = $priceListId;

        $priceList = DB::table('sales_price_lists')
            ->where('id', $priceListId)
            ->where('is_active', true)
            ->first();

        if (! $priceList) {
            return $fallback;
        }

        if ((string) ($priceList->calculation_type ?? 'items') === 'formula') {
            $basis = (string) ($priceList->formula_basis ?? 'price_list');
            $adjustment = (float) ($priceList->adjustment_percent ?? 0);

            if ($basis === 'product_cost') {
                $cost = $this->productCostWithoutTax($productId, $variantId);

                return round($cost * (1 + ($adjustment / 100)), 6);
            }

            $baseId = (int) ($priceList->base_price_list_id ?? 0);

            if ($baseId <= 0 || $baseId === $priceListId) {
                return $fallback;
            }

            $basePrice = $this->priceForProduct($productId, $variantId, $baseId, $fallback, $qty, $visited);

            return round($basePrice * (1 + ($adjustment / 100)), 6);
        }

        if (! Schema::hasTable('sales_price_list_items')) {
            return $fallback;
        }

        $today = now()->toDateString();

        $query = DB::table('sales_price_list_items')
            ->where('sales_price_list_id', $priceListId)
            ->where('is_active', true)
            ->where('product_id', $productId)
            ->where('min_quantity', '<=', $qty)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
            });

        if ($variantId > 0) {
            $specific = (clone $query)
                ->where('product_variant_id', $variantId)
                ->orderByDesc('min_quantity')
                ->first();

            if ($specific) {
                return (float) $specific->price_without_tax;
            }
        }

        $generic = $query
            ->whereNull('product_variant_id')
            ->orderByDesc('min_quantity')
            ->first();

        return $generic ? (float) $generic->price_without_tax : $fallback;
    }

    protected function productCostWithoutTax(int $productId, int $variantId = 0): float
    {
        $product = $productId > 0 ? $this->productById($productId) : null;
        $variant = $variantId > 0 ? $this->productById($variantId) : null;

        foreach ([$variant, $product] as $source) {
            if (! $source) {
                continue;
            }

            foreach (['average_cost_without_tax', 'standard_cost', 'purchase_price', 'last_purchase_cost'] as $column) {
                if (property_exists($source, $column) && $source->{$column} !== null && (float) $source->{$column} > 0) {
                    return (float) $source->{$column};
                }
            }
        }

        return 0.0;
    }

    protected function calculateLine(float $qty, float $price, float $taxRate, int $productId, int $variantId): array
    {
        $subtotal = round($qty * $price, 6);
        $tax = round($subtotal * ($taxRate / 100), 6);
        $total = round($subtotal + $tax, 6);

        $unitCost = $this->productCostWithoutTax($productId, $variantId);
        $unitMargin = round($price - $unitCost, 6);
        $marginAmount = round($unitMargin * $qty, 6);
        $marginPercent = $price > 0 ? round(($unitMargin / $price) * 100, 4) : 0;

        $status = 'no_cost';

        if ($unitCost > 0) {
            if ($price <= $unitCost) {
                $status = 'danger';
            } elseif ($marginPercent < 15) {
                $status = 'warning';
            } else {
                $status = 'success';
            }
        }

        return [
            'unit_price_with_tax' => round($price * (1 + ($taxRate / 100)), 6),
            'line_total_without_tax' => $subtotal,
            'line_tax' => $tax,
            'line_total_with_tax' => $total,
            'unit_cost' => $unitCost,
            'gross_margin_amount' => $marginAmount,
            'gross_margin_percent' => $marginPercent,
            'margin_status' => $status,
        ];
    }

    protected function recalculateOrderTotals(): void
    {
        $totals = DB::table('sales_order_lines')
            ->where('sales_order_id', $this->saleOrderId)
            ->selectRaw('COALESCE(SUM(line_total_without_tax),0) as subtotal, COALESCE(SUM(line_tax),0) as tax, COALESCE(SUM(line_total_with_tax),0) as total')
            ->first();

        $risk = DB::table('sales_order_lines')
            ->where('sales_order_id', $this->saleOrderId)
            ->whereIn('margin_status', ['warning', 'danger'])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN margin_status = 'danger' THEN 1 ELSE 0 END), 0) as danger_count,
                COALESCE(SUM(CASE WHEN margin_status = 'warning' THEN 1 ELSE 0 END), 0) as warning_count
            ")
            ->first();

        $dangerCount = (int) ($risk->danger_count ?? 0);
        $warningCount = (int) ($risk->warning_count ?? 0);
        $requiresApproval = ($dangerCount + $warningCount) > 0;

        $order = $this->saleOrder();
        $currentApprovalStatus = (string) ($order->margin_approval_status ?? 'not_required');

        $approvalStatus = $requiresApproval ? 'required' : 'not_required';

        if ($requiresApproval && $currentApprovalStatus === 'pending') {
            $approvalStatus = 'pending';
        }

        $approvalReason = $requiresApproval
            ? trim(($dangerCount > 0 ? "{$dangerCount} línea(s) con pérdida. " : '') . ($warningCount > 0 ? "{$warningCount} línea(s) con margen bajo." : ''))
            : null;

        DB::table('sales_orders')
            ->where('id', $this->saleOrderId)
            ->update($this->filterColumns('sales_orders', [
                'total_without_tax' => (float) ($totals->subtotal ?? 0),
                'total_tax' => (float) ($totals->tax ?? 0),
                'total_with_tax' => (float) ($totals->total ?? 0),
                'margin_approval_required' => $requiresApproval,
                'margin_approval_status' => $approvalStatus,
                'margin_approval_reason' => $approvalReason,
                'updated_at' => now(),
            ]));
    }


    protected function totals(): array
    {
        $totals = DB::table('sales_order_lines')
            ->where('sales_order_id', $this->saleOrderId)
            ->selectRaw('COALESCE(SUM(line_total_without_tax),0) as subtotal, COALESCE(SUM(line_tax),0) as tax, COALESCE(SUM(line_total_with_tax),0) as total')
            ->first();

        return [
            'subtotal' => (float) ($totals->subtotal ?? 0),
            'tax' => (float) ($totals->tax ?? 0),
            'total' => (float) ($totals->total ?? 0),
        ];
    }

    protected function filterColumns(string $table, array $data): array
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
}
