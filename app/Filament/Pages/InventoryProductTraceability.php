<?php

namespace App\Filament\Pages;

use App\Support\Inventory\ProductTraceabilityService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BEXIA_V57210G2_INVENTORY_NAV
class InventoryProductTraceability extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Trazabilidad de producto';

    protected static ?string $title = 'Trazabilidad de producto';

    protected static ?int $navigationSort = 67;

    protected static string $view = 'filament.pages.inventory-product-traceability';

    public ?int $company_id = null;
    public ?int $warehouse_id = null;
    public ?int $location_id = null;
    public ?int $product_id = null;
    public ?int $product_variant_id = null;
    public ?int $lot_id = null;
    public ?int $stock_serial_number_id = null;

    public string $product_search = '';
    public string $operation_kind = '';
    public string $source_group = '';
    public ?string $date_from = null;
    public ?string $date_to = null;
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
            'operation_kind' => $this->operation_kind,
            'source_group' => $this->source_group,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'limit' => $this->limit,
        ];
    }


    public function exportParams(): array
    {
        return array_filter($this->filters(), fn ($value) => $value !== null && $value !== '');
    }
    public function getRowsProperty(): Collection
    {
        return app(ProductTraceabilityService::class)->rows($this->filters());
    }

    public function getSummaryProperty(): array
    {
        return app(ProductTraceabilityService::class)->summary($this->filters());
    }

    public function resetFilters(): void
    {
        $this->company_id = $this->currentCompanyId();
        $this->warehouse_id = null;
        $this->location_id = null;
        $this->clearProduct();
        $this->operation_kind = '';
        $this->source_group = '';
        $this->date_from = null;
        $this->date_to = null;
        $this->limit = 500;
    }

    public function selectProduct(int $productId): void
    {
        $this->product_id = $productId;
        $this->product_variant_id = null;
        $this->lot_id = null;
        $this->stock_serial_number_id = null;

        $label = $this->selectedProductLabel();

        if ($label) {
            $this->product_search = $label;
        }
    }

    public function clearProduct(bool $clearSearch = true): void
    {
        $this->product_id = null;
        $this->product_variant_id = null;
        $this->lot_id = null;
        $this->stock_serial_number_id = null;

        if ($clearSearch) {
            $this->product_search = '';
        }
    }

    public function selectedProductLabel(): ?string
    {
        if (! $this->product_id || ! Schema::hasTable('products')) {
            return null;
        }

        $product = DB::table('products')->where('id', $this->product_id)->first();

        return $product ? trim(($product->name ?? 'Producto') . ' #' . $product->id) : null;
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

        return $query->orderBy('name')->limit(400)->pluck('name', 'id')->all();
    }

    public function productOptions(): array
    {
        if (! Schema::hasTable('stock_movement_lines') || ! Schema::hasTable('products')) {
            return [];
        }

        $search = trim($this->product_search);

        $query = DB::table('stock_movement_lines as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('stock_movements as m', 'm.id', '=', 'l.stock_movement_id');

        if ($this->company_id) {
            $query->where('m.company_id', $this->company_id);
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
            ->limit($search === '' ? 20 : 10)
            ->pluck('p.name', 'p.id')
            ->all();
    }

    public function variantOptions(): array
    {
        if (! $this->product_id || ! Schema::hasTable('stock_movement_lines') || ! Schema::hasTable('products')) {
            return [];
        }

        $query = DB::table('stock_movement_lines as l')
            ->join('products as v', 'v.id', '=', 'l.product_variant_id')
            ->where('l.product_id', $this->product_id)
            ->whereNotNull('l.product_variant_id');

        return $query
            ->select('v.id', 'v.name')
            ->distinct()
            ->orderBy('v.name')
            ->limit(100)
            ->pluck('v.name', 'v.id')
            ->all();
    }

    public function lotOptions(): array
    {
        if (! Schema::hasTable('stock_movement_lines') || ! Schema::hasTable('stock_lots')) {
            return [];
        }

        $query = DB::table('stock_movement_lines as l')
            ->join('stock_lots as lot', 'lot.id', '=', 'l.lot_id')
            ->whereNotNull('l.lot_id');

        if ($this->product_id) {
            $query->where('l.product_id', $this->product_id);
        }

        if ($this->product_variant_id) {
            $query->where('l.product_variant_id', $this->product_variant_id);
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
        if (! Schema::hasTable('stock_movement_lines') || ! Schema::hasTable('stock_serial_numbers')) {
            return [];
        }

        $query = DB::table('stock_movement_lines as l')
            ->join('stock_serial_numbers as s', 's.id', '=', 'l.stock_serial_number_id')
            ->whereNotNull('l.stock_serial_number_id');

        if ($this->product_id) {
            $query->where('l.product_id', $this->product_id);
        }

        if ($this->product_variant_id) {
            $query->where('l.product_variant_id', $this->product_variant_id);
        }

        if ($this->lot_id) {
            $query->where('l.lot_id', $this->lot_id);
        }

        return $query
            ->select('s.id', 's.serial_number')
            ->distinct()
            ->orderBy('s.serial_number')
            ->limit(300)
            ->pluck('s.serial_number', 's.id')
            ->all();
    }

    public function operationKindOptions(): array
    {
        return [
            'receipt' => 'Entrada',
            'delivery' => 'Salida',
            'internal_transfer' => 'Traslado',
            'inventory_adjustment' => 'Ajuste',
            'manufacturing' => 'Fabricación',
        ];
    }

    public function sourceGroupOptions(): array
    {
        return [
            'purchase_receipt' => 'Compra / recepción',
            'sale_delivery' => 'Entrega de venta',
            'pos_order' => 'Venta PDV',
            'pos_refund' => 'Devolución PDV',
            'internal_transfer' => 'Traslado interno',
            'legacy' => 'Origen legacy',
        ];
    }
public static function shouldRegisterNavigation(): bool
{
    return \App\Support\Security\BexiaAccess::inventory();
}

public static function canAccess(): bool
{
    return \App\Support\Security\BexiaAccess::inventory();
}

public static function canViewAny(): bool
{
    return \App\Support\Security\BexiaAccess::inventory();
}

}
