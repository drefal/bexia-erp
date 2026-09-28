<?php

namespace App\Filament\Pages;

use App\Support\Inventory\ProductKardexService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BEXIA_V57210L_INVENTORY_MENU_PERMISSION
class InventoryProductKardex extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Kardex por producto';

    protected static ?string $title = 'Kardex por producto';

    protected static ?int $navigationSort = 65;

    protected static string $view = 'filament.pages.inventory-product-kardex';

    public ?int $company_id = null;
    public ?int $warehouse_id = null;
    public ?int $location_id = null;
    public ?int $product_id = null;
    public ?int $product_variant_id = null;
    public ?int $lot_id = null;
    public ?int $stock_serial_number_id = null;

    public string $product_search = '';
    public string $variant_search = '';

    public ?string $date_from = null;
    public ?string $date_to = null;
    public string $status = 'done';
    public string $valuation_method = 'auto';
    public int $limit = 500;

    public function mount(): void
    {
        $this->date_from = now()->subMonths(3)->toDateString();
        $this->date_to = now()->toDateString();
        $this->company_id = \App\Support\Security\InventoryTenantScope::ensureAllowedCompanyId(
            $this->currentCompanyId()
        );
    }

    public function updatedCompanyId(): void
    {
        $this->company_id = \App\Support\Security\InventoryTenantScope::ensureAllowedCompanyId(
            (int) ($this->company_id ?? 0)
        );
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
            $this->product_id = null;
            $this->product_variant_id = null;
            $this->variant_search = '';
        }
    }

    public function updatedVariantSearch(): void
    {
        $selected = $this->selectedVariantLabel();

        if ($this->product_variant_id && $selected && trim($this->variant_search) !== $selected) {
            $this->product_variant_id = null;
        }
    }

    public function resetFilters(): void
    {
        $this->company_id = \App\Support\Security\InventoryTenantScope::ensureAllowedCompanyId(
            $this->currentCompanyId()
        );
        $this->warehouse_id = null;
        $this->location_id = null;
        $this->product_id = null;
        $this->product_variant_id = null;
        $this->lot_id = null;
        $this->stock_serial_number_id = null;
        $this->product_search = '';
        $this->variant_search = '';
        $this->date_from = now()->subMonths(3)->toDateString();
        $this->date_to = now()->toDateString();
        $this->status = 'done';
        $this->valuation_method = 'auto';
        $this->limit = 500;
    }

    public function selectProduct(int $productId): void
    {
        $this->product_id = $productId;
        $this->product_variant_id = null;
        $this->variant_search = '';

        $label = $this->selectedProductLabel();

        if ($label) {
            $this->product_search = $label;
        }

        $variants = $this->variantOptions();

        if (count($variants) === 1) {
            $variantId = (int) array_key_first($variants);
            $this->selectVariant($variantId);
        }
    }

    public function clearProduct(): void
    {
        $this->product_id = null;
        $this->product_variant_id = null;
        $this->product_search = '';
        $this->variant_search = '';
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

    public function getRowsProperty(): Collection
    {
        return app(ProductKardexService::class)->rows($this->filters());
    }

    public function getSummaryProperty(): array
    {
        return app(ProductKardexService::class)->summary($this->filters());
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
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'status' => $this->status,
            'valuation_method' => $this->valuation_method,
            'limit' => $this->limit,
        ];
    }


    public function exportParams(): array
    {
        return array_filter($this->filters(), fn ($value) => $value !== null && $value !== '');
    }
    protected function currentCompanyId(): ?int
    {
        return \App\Support\Security\InventoryTenantScope::currentCompanyId();
    }

    public function valuationMethodOptions(): array
    {
        return [
            'auto' => 'Automático según producto',
            'average' => 'Costo promedio',
            'fifo' => 'PEPS / FIFO',
            'standard' => 'Costo estándar',
            'recorded' => 'Costo registrado en movimiento',
        ];
    }

    public function valuationMethodHelp(): string
    {
        return match ($this->valuation_method) {
            'average' => 'Promedia el valor disponible conforme entran y salen productos.',
            'fifo' => 'Consume primero las entradas más antiguas.',
            'standard' => 'Usa el costo estándar configurado.',
            'recorded' => 'Usa el costo guardado en cada movimiento.',
            default => 'Usa el método configurado en producto, categoría o empresa.',
        };
    }

    public function selectedProductLabel(): ?string
    {
        if (! $this->product_id || ! Schema::hasTable('products')) {
            return null;
        }

        $product = DB::table('products')->where('id', $this->product_id)->first();

        if (! $product) {
            return null;
        }

        return trim(($product->name ?? 'Producto') . ' #' . $product->id);
    }

    public function selectedVariantLabel(): ?string
    {
        if (! $this->product_variant_id || ! Schema::hasTable('products')) {
            return null;
        }

        $variant = DB::table('products')->where('id', $this->product_variant_id)->first();

        if (! $variant) {
            return null;
        }

        return trim(($variant->name ?? 'Variante') . ' #' . $variant->id);
    }

    public function companyOptions(): array
    {
        return \App\Support\Security\InventoryTenantScope::companyOptions();
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
            $query->where('company_id', $this->company_id);
        }

        return $query->orderBy('name')->limit(300)->pluck('name', 'id')->all();
    }

    public function productOptions(): array
    {
        if (
            ! Schema::hasTable('stock_movement_lines')
            || ! Schema::hasTable('stock_movements')
            || ! Schema::hasTable('products')
        ) {
            return [];
        }

        $search = trim($this->product_search);

        /*
         * El producto puede ser compartido entre empresas.
         * La autoridad de tenancy para Kardex es el movimiento de inventario,
         * no products.company_id.
         */
        $query = DB::table('stock_movement_lines as l')
            ->join('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->join('products as p', 'p.id', '=', 'l.product_id');

        if ($this->company_id) {
            $query->where('m.company_id', $this->company_id);
        }

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $query->where(function ($q) use ($like): void {
                $q->where('p.name', 'ilike', $like);

                foreach (['sku', 'barcode', 'internal_reference', 'variant_name'] as $column) {
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
        if (! $this->product_id || ! Schema::hasTable('products')) {
            return [];
        }

        $query = DB::table('stock_movement_lines as l')
            ->join('products as v', 'v.id', '=', 'l.product_variant_id')
            ->where('l.product_id', $this->product_id)
            ->whereNotNull('l.product_variant_id');

        $search = trim($this->variant_search);

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $query->where(function ($q) use ($like): void {
                $q->where('v.name', 'ilike', $like);

                foreach (['sku', 'barcode', 'internal_reference', 'variant_name'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $q->orWhere("v.{$column}", 'ilike', $like);
                    }
                }
            });
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
        if (! Schema::hasTable('stock_lots')) {
            return [];
        }

        return DB::table('stock_movement_lines as l')
            ->join('stock_lots as lot', 'lot.id', '=', 'l.lot_id')
            ->when($this->product_id, fn ($q) => $q->where('l.product_id', $this->product_id))
            ->select('lot.id', 'lot.lot_number')
            ->distinct()
            ->orderBy('lot.lot_number')
            ->limit(300)
            ->pluck('lot.lot_number', 'lot.id')
            ->all();
    }

    public function serialOptions(): array
    {
        if (! Schema::hasTable('stock_serial_numbers')) {
            return [];
        }

        return DB::table('stock_movement_lines as l')
            ->join('stock_serial_numbers as s', 's.id', '=', 'l.stock_serial_number_id')
            ->when($this->product_id, fn ($q) => $q->where('l.product_id', $this->product_id))
            ->select('s.id', 's.serial_number')
            ->distinct()
            ->orderBy('s.serial_number')
            ->limit(300)
            ->pluck('s.serial_number', 's.id')
            ->all();
    }
public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Security\BexiaTenantPermission::can('inventory.kardex.view');
    }

public static function canAccess(): bool
    {
        return \App\Support\Security\BexiaTenantPermission::can('inventory.kardex.view');
    }

}
