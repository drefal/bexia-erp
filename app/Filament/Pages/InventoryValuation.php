<?php

namespace App\Filament\Pages;

use App\Support\Inventory\InventoryValuationService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BEXIA_V57210L_INVENTORY_MENU_PERMISSION
class InventoryValuation extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Valorización de inventario';

    protected static ?string $title = 'Valorización de inventario';

    protected static ?int $navigationSort = 66;

    protected static string $view = 'filament.pages.inventory-valuation';

    public ?int $company_id = null;
    public ?int $warehouse_id = null;
    public ?int $location_id = null;
    public ?int $product_id = null;
    public ?int $product_variant_id = null;
    public ?int $lot_id = null;
    public ?int $stock_serial_number_id = null;

    public string $product_search = '';
    public string $variant_search = '';

    public bool $only_positive = true;
    public int $limit = 500;

    public function mount(): void
    {
        $this->company_id = $this->currentCompanyId();
    }

    protected function currentCompanyId(): ?int
    {
        foreach (['company_id', 'current_company_id', 'active_company_id', 'tenant_company_id'] as $key) {
            if (session($key)) {
                return (int) session($key);
            }
        }

        return null;
    }

    public function updatedCompanyId(): void
    {
        $this->warehouse_id = null;
        $this->location_id = null;
        $this->clearProduct();
    }

    public function updatedWarehouseId(): void
    {
        $this->location_id = null;
    }

    public function updatedProductSearch(): void
    {
        $selected = $this->selectedProductLabel();

        if ($this->product_id && $selected && trim($this->product_search) !== $selected) {
            $this->clearProduct(false);
        }
    }

    public function updatedVariantSearch(): void
    {
        $selected = $this->selectedVariantLabel();

        if ($this->product_variant_id && $selected && trim($this->variant_search) !== $selected) {
            $this->product_variant_id = null;
        }
    }

    public function filters(): array
    {
        return [
            'company_id' => $this->company_id,
            'warehouse_id' => $this->warehouse_id,
            'location_id' => $this->location_id,
            'product_id' => $this->product_id,
            'product_search' => $this->product_search,
            'product_variant_id' => $this->product_variant_id,
            'lot_id' => $this->lot_id,
            'stock_serial_number_id' => $this->stock_serial_number_id,
            'only_positive' => $this->only_positive,
            'limit' => $this->limit,
        ];
    }

    public function getRowsProperty(): Collection
    {
        return app(InventoryValuationService::class)->rows($this->filters());
    }

    public function getSummaryProperty(): array
    {
        return app(InventoryValuationService::class)->summary($this->filters());
    }

    public function resetFilters(): void
    {
        $this->company_id = $this->currentCompanyId();
        $this->warehouse_id = null;
        $this->location_id = null;
        $this->clearProduct();
        $this->only_positive = true;
        $this->limit = 500;
    }

    public function selectProduct(int $productId): void
    {
        $this->product_id = $productId;
        $this->product_variant_id = null;
        $this->lot_id = null;
        $this->stock_serial_number_id = null;
        $this->variant_search = '';

        $label = $this->selectedProductLabel();

        if ($label) {
            $this->product_search = $label;
        }

        $variants = $this->variantOptions();

        if (count($variants) === 1) {
            $this->selectVariant((int) array_key_first($variants));
        }
    }

    public function clearProduct(bool $clearSearch = true): void
    {
        $this->product_id = null;
        $this->product_variant_id = null;
        $this->lot_id = null;
        $this->stock_serial_number_id = null;
        $this->variant_search = '';

        if ($clearSearch) {
            $this->product_search = '';
        }
    }

    public function selectVariant(int $variantId): void
    {
        $this->product_variant_id = $variantId;

        $label = $this->selectedVariantLabel();

        if ($label) {
            $this->variant_search = $label;
        }
    }

    public function clearVariant(): void
    {
        $this->product_variant_id = null;
        $this->variant_search = '';
    }

    public function selectedProductLabel(): ?string
    {
        if (! $this->product_id || ! Schema::hasTable('products')) {
            return null;
        }

        $product = DB::table('products')->where('id', $this->product_id)->first();

        return $product ? trim(($product->name ?? 'Producto') . ' #' . $product->id) : null;
    }

    public function selectedVariantLabel(): ?string
    {
        if (! $this->product_variant_id || ! Schema::hasTable('products')) {
            return null;
        }

        $variant = DB::table('products')->where('id', $this->product_variant_id)->first();

        return $variant ? trim(($variant->name ?? 'Variante') . ' #' . $variant->id) : null;
    }


    public function exportParams(): array
    {
        return array_filter($this->filters(), fn ($value) => $value !== null && $value !== '');
    }
    public function companyOptions(): array
    {
        if (! Schema::hasTable('companies')) {
            return [];
        }

        return DB::table('companies')->orderBy('name')->pluck('name', 'id')->all();
    }

    public function warehouseOptions(): array
    {
        if (! Schema::hasTable('warehouses')) {
            return [];
        }

        $query = DB::table('warehouses');

        if ($this->company_id && Schema::hasColumn('warehouses', 'company_id')) {
            $query->where('company_id', $this->company_id);
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }

    public function locationOptions(): array
    {
        if (! Schema::hasTable('stock_locations')) {
            return [];
        }

        $query = DB::table('stock_locations');

        if ($this->warehouse_id && Schema::hasColumn('stock_locations', 'warehouse_id')) {
            $query->where('warehouse_id', $this->warehouse_id);
        }

        if ($this->company_id && Schema::hasColumn('stock_locations', 'company_id')) {
            $query->where(function ($q): void {
                $q->where('company_id', $this->company_id)->orWhereNull('company_id');
            });
        }

        return $query->orderBy('name')->limit(300)->pluck('name', 'id')->all();
    }

    public function productOptions(): array
    {
        if (! Schema::hasTable('stock_quants') || ! Schema::hasTable('products')) {
            return [];
        }

        $search = trim($this->product_search);

        $query = DB::table('stock_quants as q')
            ->join('products as p', 'p.id', '=', 'q.product_id');

        if ($this->company_id) {
            $query->where('q.company_id', $this->company_id);
        }

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $query->where(function ($q) use ($like): void {
                $q->where('p.name', 'ilike', $like);

                foreach (['sku', 'barcode', 'internal_reference'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $q->orWhere("p.{$column}", 'ilike', $like);
                    }
                }
            });
        }

        return $query
            ->select('p.id', 'p.name')
            ->distinct()
            ->orderBy('p.name')
            ->limit($search === '' ? 20 : 8)
            ->pluck('p.name', 'p.id')
            ->all();
    }

    public function variantOptions(): array
    {
        if (! $this->product_id || ! Schema::hasTable('stock_quants') || ! Schema::hasTable('products')) {
            return [];
        }

        $query = DB::table('stock_quants as q')
            ->join('products as v', 'v.id', '=', 'q.product_variant_id')
            ->where('q.product_id', $this->product_id)
            ->whereNotNull('q.product_variant_id');

        if ($this->company_id) {
            $query->where('q.company_id', $this->company_id);
        }

        $search = trim($this->variant_search);

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $query->where('v.name', 'ilike', $like);
        }

        return $query
            ->select('v.id', 'v.name')
            ->distinct()
            ->orderBy('v.name')
            ->limit($search === '' ? 20 : 8)
            ->pluck('v.name', 'v.id')
            ->all();
    }

    public function lotOptions(): array
    {
        if (! Schema::hasTable('stock_quants') || ! Schema::hasTable('stock_lots')) {
            return [];
        }

        $query = DB::table('stock_quants as q')
            ->join('stock_lots as lot', 'lot.id', '=', 'q.lot_id')
            ->whereNotNull('q.lot_id');

        if ($this->company_id) {
            $query->where('q.company_id', $this->company_id);
        }

        if ($this->product_id) {
            $query->where('q.product_id', $this->product_id);
        }

        if ($this->product_variant_id) {
            $query->where('q.product_variant_id', $this->product_variant_id);
        }

        return $query
            ->select('lot.id', 'lot.lot_number')
            ->distinct()
            ->orderBy('lot.lot_number')
            ->limit(200)
            ->pluck('lot.lot_number', 'lot.id')
            ->all();
    }

    public function serialOptions(): array
    {
        if (! Schema::hasTable('stock_serial_numbers')) {
            return [];
        }

        $query = DB::table('stock_serial_numbers as s')
            ->where('s.status', 'available')
            ->whereNotNull('s.current_warehouse_id')
            ->whereNotNull('s.current_location_id');

        if ($this->company_id) {
            $query->where('s.company_id', $this->company_id);
        }

        if ($this->warehouse_id) {
            $query->where('s.current_warehouse_id', $this->warehouse_id);
        }

        if ($this->location_id) {
            $query->where('s.current_location_id', $this->location_id);
        }

        if ($this->product_id) {
            $query->where('s.product_id', $this->product_id);
        }

        if ($this->product_variant_id) {
            $query->where('s.product_variant_id', $this->product_variant_id);
        }

        if ($this->lot_id) {
            $query->where('s.lot_id', $this->lot_id);
        }

        return $query
            ->select('s.id', 's.serial_number')
            ->distinct()
            ->orderBy('s.serial_number')
            ->limit(300)
            ->pluck('s.serial_number', 's.id')
            ->all();
    }
    public function supportsSerialQuantFilter(): bool
    {
        return Schema::hasTable('stock_serial_numbers');
    }
public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Security\BexiaTenantPermission::can('inventory.menu.view');
    }

public static function canAccess(): bool
    {
        return \App\Support\Security\BexiaTenantPermission::can('inventory.menu.view');
    }

}
