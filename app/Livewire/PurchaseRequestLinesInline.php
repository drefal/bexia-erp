<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class PurchaseRequestLinesInline extends Component
{

    public ?int $deleteLineId = null;

    public string $deleteLineLabel = '';

    public int $purchaseRequestId;

    public string $productSearch = '';
    public string $variantSearch = '';
    public string $lineSearch = '';

    public ?int $editingLineId = null;
    public ?int $product_id = null;
    public ?int $product_variant_id = null;

    public string|float|null $requested_quantity = 1;
    public string $purchase_unit_type = 'piece';
    public string|float|null $purchase_unit_factor = 1;
    public string|float|null $unit_cost_without_tax = 0;
    public string|float|null $tax_rate = '16';

    public function mount(int $purchaseRequestId): void
    {
        $this->purchaseRequestId = $purchaseRequestId;
    }

    public function updatedProductId($value): void
    {
        $this->selectProduct($value);
    }

    public function updatedProductVariantId($value): void
    {
        $this->selectVariant($value);
    }

    public function updatedPurchaseUnitType($value): void
    {
        $this->purchase_unit_type = $value ?: 'piece';
        $this->applyPurchaseUnitDefaults();
    }

    public function selectProduct($productId): void
    {
        $productId = $productId ? (int) $productId : null;

        $this->product_id = $productId;
        $this->product_variant_id = null;
        $this->variantSearch = '';

        if (! $productId) {
            $this->productSearch = '';
            $this->purchase_unit_type = 'piece';
            $this->purchase_unit_factor = 1;
            $this->unit_cost_without_tax = 0;
            $this->tax_rate = '16';

            return;
        }

        $this->productSearch = $this->productLabel($productId);
        $this->purchase_unit_type = 'piece';
        $this->purchase_unit_factor = 1;
        $this->unit_cost_without_tax = $this->productCostWithoutTax($productId);
        $this->tax_rate = $this->normalizeTaxRateOptionKey($this->productPurchaseTaxRate($productId));
    }

    public function clearProduct(): void
    {
        $this->selectProduct(null);
    }

    public function selectVariant($variantId): void
    {
        $variantId = $variantId ? (int) $variantId : null;

        $this->product_variant_id = $variantId;

        if (! $variantId) {
            $this->variantSearch = '';
            $this->applyPurchaseUnitDefaults();

            return;
        }

        $this->variantSearch = $this->productLabel($variantId, true);
        $this->unit_cost_without_tax = $this->productCostWithoutTax($variantId) * (float) $this->currentPurchaseUnitFactor();
        $this->tax_rate = $this->normalizeTaxRateOptionKey($this->productPurchaseTaxRate($variantId));
    }

    public function clearVariant(): void
    {
        $this->selectVariant(null);
    }

    public function saveLine(): void
    {
        $this->validate([
            'product_id' => ['required', 'integer'],
            'requested_quantity' => ['required', 'numeric', 'min:0.000001'],
            'purchase_unit_type' => ['required', 'string'],
            'unit_cost_without_tax' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0'],
        ]);

        $data = $this->normalizedLineData();

        if ($this->editingLineId) {
            DB::table('purchase_request_lines')
                ->where('id', $this->editingLineId)
                ->where('purchase_request_id', $this->purchaseRequestId)
                ->update($data + [
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('purchase_request_lines')->insert($data + [
                'purchase_request_id' => $this->purchaseRequestId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->recalculateTotals();
        $this->resetLineForm();
    }

    public function editLine(int $lineId): void
    {
        $line = DB::table('purchase_request_lines')
            ->where('purchase_request_id', $this->purchaseRequestId)
            ->where('id', $lineId)
            ->first();

        if (! $line) {
            return;
        }

        $this->editingLineId = (int) $line->id;
        $this->product_id = $line->product_id ? (int) $line->product_id : null;
        $this->product_variant_id = $line->product_variant_id ? (int) $line->product_variant_id : null;
        $this->requested_quantity = (float) $line->requested_quantity;
        $this->purchase_unit_type = $line->purchase_unit_type ?? 'piece';
        $this->purchase_unit_factor = (float) ($line->purchase_unit_factor ?? 1);
        $this->unit_cost_without_tax = (float) $line->unit_cost_without_tax;
        $this->tax_rate = $this->normalizeTaxRateOptionKey($line->tax_rate ?? 16);
        $this->productSearch = $this->product_id ? $this->productLabel($this->product_id) : '';
        $this->variantSearch = $this->product_variant_id ? $this->productLabel($this->product_variant_id, true) : '';
    }

    public function deleteLine(int $lineId): void
    {
        DB::table('purchase_request_lines')
            ->where('purchase_request_id', $this->purchaseRequestId)
            ->where('id', $lineId)
            ->delete();

        $this->recalculateTotals();
    }

    public function cancelEdit(): void
    {
        $this->resetLineForm();
    }

    public function getLinesProperty()
    {
        $query = DB::table('purchase_request_lines')
            ->where('purchase_request_id', $this->purchaseRequestId);

        $search = trim($this->lineSearch);

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                foreach (['product_label', 'variant_label', 'purchase_unit_label', 'cost_source', 'priority_label'] as $column) {
                    if (Schema::hasColumn('purchase_request_lines', $column)) {
                        $query->orWhere($column, 'ilike', '%' . $search . '%');
                    }
                }
            });
        }

        return $query
            ->orderBy('id')
            ->get();
    }

    public function getProductOptionsProperty(): array
    {
        return $this->productSearchOptions($this->productSearch, $this->product_id);
    }

    public function getVariantOptionsProperty(): array
    {
        return $this->variantSearchOptions($this->product_id, $this->variantSearch, $this->product_variant_id);
    }

    public function getTotalsProperty(): array
    {
        $row = DB::table('purchase_requests')
            ->where('id', $this->purchaseRequestId)
            ->first(['total_without_tax', 'total_tax', 'total_with_tax']);

        return [
            'subtotal' => (float) ($row?->total_without_tax ?? 0),
            'tax' => (float) ($row?->total_tax ?? 0),
            'total' => (float) ($row?->total_with_tax ?? 0),
        ];
    }

    public function purchaseTaxOptions(): array
    {
        return [
            $this->normalizeTaxRateOptionKey(0) => 'Exento (0%)',
            $this->normalizeTaxRateOptionKey(8) => 'IVA 8% (8.00%)',
            $this->normalizeTaxRateOptionKey(16) => 'IVA 16% (16.00%)',
        ];
    }
    public function purchaseUnitOptions(): array
    {
        $options = [
            'piece' => 'Pieza [H87]',
        ];

        foreach ($this->purchaseUnitRowsForCurrentProduct() as $unit) {
            $factor = rtrim(rtrim(number_format((float) $unit->factor, 4, '.', ''), '0'), '.');
            $sat = $unit->sat_unit_key ? ' [' . $unit->sat_unit_key . ']' : '';
            $options['unit:' . $unit->id] = $unit->name . $sat . ' (' . $factor . ' base)';
        }

        if (count($options) === 1) {
            $factor = $this->purchasePackageFactorForCurrentProduct();

            if ($factor > 1) {
                $label = rtrim(rtrim(number_format($factor, 4, '.', ''), '0'), '.');
                $options['box'] = 'Caja [XBX] (' . $label . ' base)';
            }
        }

        return $options;
    }


    protected function normalizedLineData(): array
    {
        $quantity = (float) ($this->requested_quantity ?? 0);
        $factor = (float) $this->currentPurchaseUnitFactor();
        $baseQuantity = round($quantity * $factor, 6);
        $unitWithoutTax = (float) ($this->unit_cost_without_tax ?? 0);
        $taxRate = (float) ($this->tax_rate ?? 0);

        $unitWithTax = round($unitWithoutTax * (1 + ($taxRate / 100)), 6);
        $lineWithoutTax = round($quantity * $unitWithoutTax, 6);
        $lineWithTax = round($quantity * $unitWithTax, 6);

        $request = DB::table('purchase_requests')->where('id', $this->purchaseRequestId)->first();

        $data = [
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'product_label' => $this->productLabel($this->product_id),
            'variant_label' => $this->product_variant_id ? $this->productLabel($this->product_variant_id, true) : '—',
            'requested_quantity' => $quantity,

            'purchase_unit_type' => $this->purchase_unit_type ?: 'piece',
            'purchase_unit_label' => $this->purchaseUnitLabel(),
            'purchase_unit_factor' => $factor,
            'base_quantity' => $baseQuantity,
            'sat_unit_key' => $this->currentSatUnitKey(),
            'sat_unit_name' => $this->currentSatUnitName(),

            'available_quantity' => 0,
            'suggested_quantity' => 0,
            'pending_quantity' => 0,
            'unit_cost_without_tax' => $unitWithoutTax,
            'tax_rate' => $taxRate,
            'unit_cost_with_tax' => $unitWithTax,
            'line_total_without_tax' => $lineWithoutTax,
            'line_tax' => max(0, round($lineWithTax - $lineWithoutTax, 6)),
            'line_total_with_tax' => $lineWithTax,
            'priority' => 'normal',
            'priority_label' => 'Normal',
            'cost_source' => 'Manual',
        ];

        foreach (['company_id', 'warehouse_id', 'location_id', 'warehouse_label', 'location_label'] as $column) {
            if (Schema::hasColumn('purchase_request_lines', $column) && $request && property_exists($request, $column)) {
                $data[$column] = $request->{$column};
            }
        }

        return array_filter(
            $data,
            fn ($value, $key) => Schema::hasColumn('purchase_request_lines', $key),
            ARRAY_FILTER_USE_BOTH
        );
    }

    protected function resetLineForm(): void
    {
        $this->editingLineId = null;
        $this->product_id = null;
        $this->product_variant_id = null;
        $this->productSearch = '';
        $this->variantSearch = '';
        $this->requested_quantity = 1;
        $this->purchase_unit_type = 'piece';
        $this->purchase_unit_factor = 1;
        $this->unit_cost_without_tax = 0;
        $this->tax_rate = '16';
    }

    protected function recalculateTotals(): void
    {
        $totals = DB::table('purchase_request_lines')
            ->where('purchase_request_id', $this->purchaseRequestId)
            ->selectRaw('
                COALESCE(SUM(line_total_without_tax), 0) as subtotal,
                COALESCE(SUM(line_tax), 0) as tax,
                COALESCE(SUM(line_total_with_tax), 0) as total
            ')
            ->first();

        DB::table('purchase_requests')
            ->where('id', $this->purchaseRequestId)
            ->update([
                'total_without_tax' => (float) ($totals->subtotal ?? 0),
                'total_tax' => (float) ($totals->tax ?? 0),
                'total_with_tax' => (float) ($totals->total ?? 0),
                'updated_at' => now(),
            ]);
    }

    protected function applyPurchaseUnitDefaults(): void
    {
        $factor = (float) $this->currentPurchaseUnitFactor();
        $this->purchase_unit_factor = $factor;

        $productId = $this->product_variant_id ?: $this->product_id;

        if ($productId) {
            $this->unit_cost_without_tax = round($this->productCostWithoutTax((int) $productId) * $factor, 4);
        }
    }
    protected function currentPurchaseUnitFactor(): float
    {
        $type = $this->purchase_unit_type ?: 'piece';

        if ($type === 'piece') {
            return 1.0;
        }

        $unit = $this->purchaseUnitRowByType($type);

        if ($unit && is_numeric($unit->factor) && (float) $unit->factor > 0) {
            return (float) $unit->factor;
        }

        if ($type === 'box') {
            return max(1.0, (float) $this->purchasePackageFactorForCurrentProduct());
        }

        return max(1.0, (float) ($this->purchase_unit_factor ?: 1));
    }


    protected function purchasePackageFactorForCurrentProduct(): float
    {
        foreach ([$this->product_variant_id, $this->product_id] as $productId) {
            if (! $productId) {
                continue;
            }

            $factor = $this->productPurchaseUnitFactor((int) $productId);

            if ($factor > 1) {
                return $factor;
            }
        }

        return 1.0;
    }
    protected function purchaseUnitLabel(): string
    {
        $type = $this->purchase_unit_type ?: 'piece';

        if ($type === 'piece') {
            return 'Pieza';
        }

        $unit = $this->purchaseUnitRowByType($type);

        if ($unit) {
            $factor = rtrim(rtrim(number_format((float) $unit->factor, 4, '.', ''), '0'), '.');

            return $unit->name . ' (' . $factor . ' base)';
        }

        if ($type === 'box') {
            $factor = $this->currentPurchaseUnitFactor();
            $label = rtrim(rtrim(number_format($factor, 4, '.', ''), '0'), '.');

            return 'Caja (' . $label . ' base)';
        }

        return 'Unidad';
    }


    protected function productSearchOptions(string $search = '', ?int $selectedId = null): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $search = trim($search);

        if ($search === '' && ! $selectedId) {
            return [];
        }

        $query = DB::table('products');

        if (Schema::hasColumn('products', 'parent_product_id')) {
            $query->whereNull('parent_product_id');
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                foreach (['internal_reference', 'name', 'sku', 'barcode', 'code'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $query->orWhereRaw("CAST({$column} AS TEXT) ILIKE ?", ['%' . $search . '%']);
                    }
                }
            });
        }

        $orderColumn = Schema::hasColumn('products', 'internal_reference') ? 'internal_reference' : 'name';

        $options = $query
            ->orderBy($orderColumn)
            ->limit(25)
            ->get()
            ->mapWithKeys(fn ($product): array => [
                $product->id => $this->productLabelFromRow($product),
            ])
            ->all();

        if ($selectedId && ! isset($options[$selectedId])) {
            $options[$selectedId] = $this->productLabel($selectedId);
        }

        return $options;
    }

    protected function variantSearchOptions(?int $productId, string $search = '', ?int $selectedId = null): array
    {
        if (! $productId || ! Schema::hasTable('products') || ! Schema::hasColumn('products', 'parent_product_id')) {
            return [];
        }

        $query = DB::table('products')
            ->where('parent_product_id', $productId);

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        $search = trim($search);

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                foreach (['internal_reference', 'name', 'sku', 'barcode', 'variant_group', 'variant_value'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $query->orWhereRaw("CAST({$column} AS TEXT) ILIKE ?", ['%' . $search . '%']);
                    }
                }
            });
        }

        $orderColumn = Schema::hasColumn('products', 'variant_value') ? 'variant_value' : 'name';

        $options = $query
            ->orderBy($orderColumn)
            ->limit(30)
            ->get()
            ->mapWithKeys(fn ($product): array => [
                $product->id => $this->productLabelFromRow($product, true),
            ])
            ->all();

        if ($selectedId && ! isset($options[$selectedId])) {
            $options[$selectedId] = $this->productLabel($selectedId, true);
        }

        return $options;
    }

    protected function productLabel(?int $productId, bool $variant = false): string
    {
        if (! $productId || ! Schema::hasTable('products')) {
            return '—';
        }

        $product = DB::table('products')->where('id', $productId)->first();

        return $product ? $this->productLabelFromRow($product, $variant) : 'Producto #' . $productId;
    }

    protected function productLabelFromRow(object $product, bool $variant = false): string
    {
        $reference = '';

        foreach (['internal_reference', 'reference', 'code', 'sku'] as $column) {
            if (property_exists($product, $column) && trim((string) $product->{$column}) !== '') {
                $reference = trim((string) $product->{$column});
                break;
            }
        }

        if ($variant) {
            foreach (['variant_value', 'value', 'attribute_value'] as $column) {
                if (property_exists($product, $column) && trim((string) $product->{$column}) !== '') {
                    return trim((string) $product->{$column});
                }
            }

            $name = property_exists($product, 'name') ? trim((string) $product->name) : '';

            if ($name !== '') {
                $parts = array_values(array_filter(array_map('trim', preg_split('/[-|\/]/', $name))));

                if (! empty($parts)) {
                    return end($parts);
                }

                return $name;
            }

            return 'Variante #' . $product->id;
        }

        $name = property_exists($product, 'name') ? trim((string) $product->name) : '';

        return trim(($reference ? $reference . ' - ' : '') . ($name ?: 'Producto #' . $product->id));
    }

    protected function productCostWithoutTax(?int $productId): float
    {
        if (! $productId || ! Schema::hasTable('products')) {
            return 0.0;
        }

        $product = DB::table('products')->where('id', $productId)->first();

        if (! $product) {
            return 0.0;
        }

        foreach ([
            'average_cost_without_tax',
            'current_average_cost_without_tax',
            'avg_cost_without_tax',
            'purchase_cost_without_tax',
            'purchase_price_without_tax',
            'purchase_cost',
            'purchase_price',
            'cost',
            'standard_price',
        ] as $column) {
            if (property_exists($product, $column) && is_numeric($product->{$column})) {
                return (float) $product->{$column};
            }
        }

        return 0.0;
    }

    protected function productPurchaseTaxRate(?int $productId): float
    {
        if (! $productId || ! Schema::hasTable('products')) {
            return 16.0;
        }

        $product = DB::table('products')->where('id', $productId)->first();

        if (! $product) {
            return 16.0;
        }

        foreach (['purchase_tax_rate', 'tax_purchase_rate', 'purchase_vat_rate', 'iva_compra', 'tax_rate'] as $column) {
            if (property_exists($product, $column) && is_numeric($product->{$column})) {
                return (float) $product->{$column};
            }
        }

        return 16.0;
    }

    protected function productPurchaseUnitFactor(?int $productId): float
    {
        if (! $productId || ! Schema::hasTable('products')) {
            return 1.0;
        }

        $product = DB::table('products')->where('id', $productId)->first();

        if (! $product) {
            return 1.0;
        }

        foreach ([
            'uxes_per_package',
            'uxes',
            'purchase_units_per_package',
            'units_per_purchase_package',
            'units_per_package',
            'units_per_pack',
            'purchase_package_quantity',
            'package_quantity',
            'pack_quantity',
            'units_per_box',
            'box_units',
            'pieces_per_box',
            'purchase_unit_factor',
            'purchase_uom_factor',
        ] as $column) {
            if (property_exists($product, $column) && is_numeric($product->{$column}) && (float) $product->{$column} > 0) {
                return (float) $product->{$column};
            }
        }

        return 1.0;
    }

    protected function normalizeTaxRateOptionKey($rate): string
    {
        $rate = is_numeric($rate) ? (float) $rate : 0.0;

        return rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.') ?: '0';
    }

    protected function purchaseUnitRowsForCurrentProduct()
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('product_purchase_units')) {
            return collect();
        }

        $productIds = [];

        if ($this->product_variant_id) {
            $productIds[] = (int) $this->product_variant_id;

            $parentId = $this->parentProductId((int) $this->product_variant_id);
            if ($parentId) {
                $productIds[] = $parentId;
            }
        }

        if ($this->product_id) {
            $productIds[] = (int) $this->product_id;
        }

        $productIds = array_values(array_unique(array_filter($productIds)));

        foreach ($productIds as $productId) {
            $rows = \Illuminate\Support\Facades\DB::table('product_purchase_units')
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();

            if ($rows->isNotEmpty()) {
                return $rows;
            }
        }

        return collect();
    }


    protected function purchaseUnitRowByType(?string $type): ?object
    {
        if (! $type || ! str_starts_with($type, 'unit:') || ! \Illuminate\Support\Facades\Schema::hasTable('product_purchase_units')) {
            return null;
        }

        $id = (int) str_replace('unit:', '', $type);

        if ($id <= 0) {
            return null;
        }

        return \Illuminate\Support\Facades\DB::table('product_purchase_units')
            ->where('id', $id)
            ->first();
    }


    protected function currentSatUnitKey(): ?string
    {
        $type = $this->purchase_unit_type ?: 'piece';

        if ($type === 'piece') {
            return 'H87';
        }

        $unit = $this->purchaseUnitRowByType($type);

        if ($unit && ! empty($unit->sat_unit_key)) {
            return $unit->sat_unit_key;
        }

        if ($type === 'box') {
            return 'XBX';
        }

        return null;
    }


    protected function currentSatUnitName(): ?string
    {
        $type = $this->purchase_unit_type ?: 'piece';

        if ($type === 'piece') {
            return 'Pieza';
        }

        $unit = $this->purchaseUnitRowByType($type);

        if ($unit && ! empty($unit->sat_unit_name)) {
            return $unit->sat_unit_name;
        }

        if ($type === 'box') {
            return 'Caja';
        }

        return null;
    }


    protected function parentProductId(?int $productId): ?int
    {
        if (! $productId || ! \Illuminate\Support\Facades\Schema::hasTable('products') || ! \Illuminate\Support\Facades\Schema::hasColumn('products', 'parent_product_id')) {
            return null;
        }

        $parentId = \Illuminate\Support\Facades\DB::table('products')
            ->where('id', $productId)
            ->value('parent_product_id');

        return $parentId ? (int) $parentId : null;
    }


    public function render()
    {
        return view('livewire.purchase-request-lines-inline');
    }
    public function confirmDeleteLine(int $lineId): void
    {
        $line = \Illuminate\Support\Facades\DB::table('purchase_request_lines')
            ->where('id', $lineId)
            ->first();

        $this->deleteLineId = $lineId;
        $this->deleteLineLabel = trim((string) ($line->product_label ?? 'este producto'));

        if ($this->deleteLineLabel === '') {
            $this->deleteLineLabel = 'este producto';
        }
    }

    public function cancelDeleteLine(): void
    {
        $this->deleteLineId = null;
        $this->deleteLineLabel = '';
    }

    public function deleteConfirmedLine(): void
    {
        $lineId = (int) ($this->deleteLineId ?? 0);

        $this->cancelDeleteLine();

        if ($lineId <= 0) {
            return;
        }

        $this->deleteLine($lineId);
    }


}
