<?php

namespace App\Filament\Pages;

use App\Support\Inventory\InventoryAsOfDateService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryAsOfDate extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Inventario a fecha';

    protected static ?string $title = 'Inventario a fecha';

    protected static ?int $navigationSort = 68;

    protected static string $view = 'filament.pages.inventory-as-of-date';

    public ?int $company_id = null;
    public ?int $warehouse_id = null;
    public ?int $location_id = null;
    public ?int $product_id = null;
    public ?int $product_variant_id = null;
    public ?int $lot_id = null;

    public string $product_search = '';

    // Fecha visible en filtros
    public string $cutoff_date = '';
    public string $cutoff_time = '23:59';

    // Fecha realmente aplicada al cálculo
    public string $applied_cutoff_date = '';
    public string $applied_cutoff_time = '23:59';

    public int $limit = 1000;
    public bool $show_zero = false;
    public bool $only_negative = false;

    public int $refresh_token = 0;
    public ?string $last_calculated_at = null;

    public function mount(): void
    {
        $today = now()->toDateString();

        $this->company_id = $this->currentCompanyId();

        $this->cutoff_date = $today;
        $this->cutoff_time = '23:59';

        $this->applied_cutoff_date = $today;
        $this->applied_cutoff_time = '23:59';

        $this->last_calculated_at = now()->format('Y-m-d H:i:s');
    }

    public function recalculate(): void
    {
        if (trim($this->cutoff_date) === '') {
            $this->cutoff_date = now()->toDateString();
        }

        if (trim($this->cutoff_time) === '') {
            $this->cutoff_time = '23:59';
        }

        $this->applied_cutoff_date = $this->cutoff_date;
        $this->applied_cutoff_time = $this->cutoff_time;

        $this->refresh_token++;
        $this->last_calculated_at = now()->format('Y-m-d H:i:s');
    }

    public function filters(): array
    {
        return [
            'company_id' => $this->company_id,
            'warehouse_id' => $this->warehouse_id,
            'location_id' => $this->location_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'lot_id' => $this->lot_id,
            'cutoff_date' => $this->applied_cutoff_date ?: $this->cutoff_date,
            'cutoff_time' => $this->applied_cutoff_time ?: $this->cutoff_time,
            'limit' => $this->limit,
            'show_zero' => $this->show_zero,
            'only_negative' => $this->only_negative,
            'refresh_token' => $this->refresh_token,
        ];
    }


    public function exportParams(): array
    {
        $params = $this->filters();

        unset($params['refresh_token']);

        return array_filter($params, fn ($value) => $value !== null && $value !== '');
    }

    public function getRowsProperty(): Collection
    {
        return app(InventoryAsOfDateService::class)->rows($this->filters());
    }

    public function getSummaryProperty(): array
    {
        return app(InventoryAsOfDateService::class)->summary($this->filters());
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

    public function selectProduct(int $productId): void
    {
        $this->product_id = $productId;
        $this->product_variant_id = null;
        $this->lot_id = null;

        $label = $this->selectedProductLabel();

        if ($label) {
            $this->product_search = $label;
        }

        $this->recalculate();
    }

    public function clearProduct(bool $clearSearch = true): void
    {
        $this->product_id = null;
        $this->product_variant_id = null;
        $this->lot_id = null;

        if ($clearSearch) {
            $this->product_search = '';
        }
    }

    public function resetFilters(): void
    {
        $today = now()->toDateString();

        $this->company_id = $this->currentCompanyId();
        $this->warehouse_id = null;
        $this->location_id = null;
        $this->clearProduct();

        $this->cutoff_date = $today;
        $this->cutoff_time = '23:59';
        $this->applied_cutoff_date = $today;
        $this->applied_cutoff_time = '23:59';

        $this->limit = 1000;
        $this->show_zero = false;
        $this->only_negative = false;

        $this->refresh_token++;
        $this->last_calculated_at = now()->format('Y-m-d H:i:s');
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

        return $query->orderBy('name')->limit(500)->pluck('name', 'id')->all();
    }

    public function productOptions(): array
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('stock_quants')) {
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
            ->limit($search === '' ? 20 : 12)
            ->pluck('p.name', 'p.id')
            ->all();
    }

    public function variantOptions(): array
    {
        if (! $this->product_id || ! Schema::hasTable('stock_quants') || ! Schema::hasTable('products')) {
            return [];
        }

        return DB::table('stock_quants as q')
            ->join('products as v', 'v.id', '=', 'q.product_variant_id')
            ->where('q.product_id', $this->product_id)
            ->whereNotNull('q.product_variant_id')
            ->select('v.id', 'v.name')
            ->distinct()
            ->orderBy('v.name')
            ->limit(200)
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
            ->limit(300)
            ->pluck('lot.lot_number', 'lot.id')
            ->all();
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
}
