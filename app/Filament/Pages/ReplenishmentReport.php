<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReplenishmentReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Reporte de reabastecimiento';

    protected static ?string $title = 'Reporte de reabastecimiento';

    protected static ?int $navigationSort = 75;

    protected static string $view = 'filament.pages.replenishment-report';

    public ?int $warehouseId = null;

    public ?int $locationId = null;

    public string $priority = '';

    public string $groupBy = 'detail';

    public string $search = '';

    public bool $onlyShortages = true;


    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('inventory.menu.view')
            );
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('inventory.menu.view')
            );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Ver PDF de reabastecimiento de compra')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (): string => route('inventory.replenishment-report.pdf', [
                    'warehouse_id' => $this->warehouseId,
                    'location_id' => $this->locationId,
                    'priority' => $this->priority,
                    'group_by' => $this->groupBy,
                    'search' => $this->search,
                    'only_shortages' => $this->onlyShortages ? 1 : 0,
                    'company_id' => static::currentCompanyId(),
                ]))
                ->openUrlInNewTab(),
        ];
    }

    public function updatedWarehouseId(): void
    {
        $this->locationId = null;
    }

    public function getWarehouseOptionsProperty(): array
    {
        if (! Schema::hasTable('warehouses')) {
            return [];
        }

        $query = DB::table('warehouses');

        if (Schema::hasColumn('warehouses', 'is_active')) {
            $query->where('is_active', true);
        }

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('warehouses', 'company_id')) {
            $query->where('company_id', $companyId);
        } elseif (Schema::hasColumn('warehouses', 'company_id')) {
            $query->whereNull('company_id');
        }

        return $query
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn ($warehouse): array => [
                $warehouse->id => trim(($warehouse->code ? $warehouse->code . ' - ' : '') . $warehouse->name),
            ])
            ->all();
    }

    public function getLocationOptionsProperty(): array
    {
        if (! $this->warehouseId || ! Schema::hasTable('stock_locations')) {
            return [];
        }

        $query = DB::table('stock_locations')
            ->where('warehouse_id', $this->warehouseId);

        if (Schema::hasColumn('stock_locations', 'is_active')) {
            $query->where('is_active', true);
        }

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('stock_locations', 'company_id')) {
            $query->where('company_id', $companyId);
        } elseif (Schema::hasColumn('stock_locations', 'company_id')) {
            $query->whereNull('company_id');
        }

        return $query
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn ($location): array => [
                $location->id => trim(($location->code ? $location->code . ' - ' : '') . $location->name),
            ])
            ->all();
    }

    public function getRowsProperty(): array
    {
        if (! Schema::hasTable('stock_replenishment_rules')) {
            return [];
        }

        $companyId = static::currentCompanyId();

        $query = DB::table('stock_replenishment_rules')
            ->where('is_active', true);

        if ($companyId && Schema::hasColumn('stock_replenishment_rules', 'company_id')) {
            $query->where('company_id', $companyId);
        } elseif (Schema::hasColumn('stock_replenishment_rules', 'company_id')) {
            $query->whereNull('company_id');
        }

        if ($this->warehouseId) {
            $query->where('warehouse_id', $this->warehouseId);
        }

        if ($this->locationId) {
            $query->where('location_id', $this->locationId);
        }

        if ($this->priority !== '') {
            $query->where('priority', $this->priority);
        }

        $rules = $query
            ->orderBy('warehouse_id')
            ->orderBy('location_id')
            ->orderBy('priority')
            ->orderBy('product_id')
            ->limit(2000)
            ->get();

        $rows = [];

        foreach ($rules as $rule) {
            $available = static::availableQuantity($rule);
            $min = (float) ($rule->min_quantity ?? 0);
            $max = (float) ($rule->max_quantity ?? 0);

            $needsReplenishment = $available <= $min;
            $baseNeeded = max(0, $max - $available);

            $purchaseData = static::purchaseData($rule);
            $suggested = $needsReplenishment
                ? static::suggestedQuantity($baseNeeded, $purchaseData)
                : 0;

            $product = static::productLabel($rule->product_id);
            $variant = $rule->product_variant_id
                ? static::productLabel($rule->product_variant_id, true)
                : '—';

            $supplier = static::latestPurchaseSupplierLabel($rule);

            $status = static::statusFor($available, $min);

            $row = [
                'warehouse' => static::warehouseLabel($rule->warehouse_id),
                'location' => static::locationLabel($rule->location_id),
                'product' => $product,
                'variant' => $variant,
                'available' => $available,
                'min' => $min,
                'max' => $max,
                'base_needed' => $baseNeeded,
                'pack_units' => $purchaseData['pack_units'],
                'purchase_min' => $purchaseData['purchase_min'],
                'purchase_multiple' => $purchaseData['purchase_multiple'],
                'suggested' => $suggested,
                'priority' => (string) ($rule->priority ?: 'normal'),
                'priority_label' => static::priorityLabel($rule->priority ?: 'normal'),
                'supplier' => $supplier,
                'status' => $status['label'],
                'status_color' => $status['color'],
                'needs_replenishment' => $needsReplenishment,
            ];

            $needle = mb_strtolower(trim($this->search));

            if ($needle !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    $row['warehouse'],
                    $row['location'],
                    $row['product'],
                    $row['variant'],
                    $row['supplier'],
                    $row['priority_label'],
                    $row['status'],
                ]));

                if (! str_contains($haystack, $needle)) {
                    continue;
                }
            }

            if ($this->onlyShortages && ! $needsReplenishment) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function getGroupedRowsProperty(): array
    {
        $rows = $this->rows;

        if ($this->groupBy === 'detail') {
            return [
                'Detalle' => $rows,
            ];
        }

        $groups = [];

        foreach ($rows as $row) {
            $key = match ($this->groupBy) {
                'supplier' => $row['supplier'] ?: 'Sin proveedor sugerido',
                'warehouse_location' => $row['warehouse'] . ' / ' . $row['location'],
                'priority' => $row['priority_label'],
                default => 'Detalle',
            };

            $groups[$key][] = $row;
        }

        ksort($groups);

        return $groups;
    }

    public function getTotalsProperty(): array
    {
        $rows = $this->rows;

        return [
            'rules' => count($rows),
            'shortages' => collect($rows)->where('needs_replenishment', true)->count(),
            'suggested_total' => collect($rows)->sum('suggested'),
        ];
    }

    protected static function availableQuantity(object $rule): float
    {
        if (! Schema::hasTable('stock_quants')) {
            return 0;
        }

        $quantityColumn = Schema::hasColumn('stock_quants', 'quantity')
            ? 'quantity'
            : (Schema::hasColumn('stock_quants', 'physical_quantity') ? 'physical_quantity' : null);

        if (! $quantityColumn) {
            return 0;
        }

        $reservedColumn = null;

        foreach (['reserved_quantity', 'reserved', 'reserved_qty'] as $column) {
            if (Schema::hasColumn('stock_quants', $column)) {
                $reservedColumn = $column;
                break;
            }
        }

        $query = DB::table('stock_quants')
            ->where('warehouse_id', $rule->warehouse_id)
            ->where('location_id', $rule->location_id)
            ->where('product_id', $rule->product_id);

        if (Schema::hasColumn('stock_quants', 'company_id')) {
            $rule->company_id
                ? $query->where('company_id', $rule->company_id)
                : $query->whereNull('company_id');
        }

        if (Schema::hasColumn('stock_quants', 'product_variant_id')) {
            $rule->product_variant_id
                ? $query->where('product_variant_id', $rule->product_variant_id)
                : $query->whereNull('product_variant_id');
        }

        $quantity = (float) $query->sum($quantityColumn);

        if ($reservedColumn) {
            $reserved = (float) (clone $query)->sum($reservedColumn);

            return $quantity - $reserved;
        }

        return $quantity;
    }

    protected static function purchaseData(object $rule): array
    {
        $row = null;

        if ($rule->product_variant_id) {
            $row = static::productRow($rule->product_variant_id);
        }

        $parent = static::productRow($rule->product_id);

        $pack = static::productValue($row, 'purchase_pack_units')
            ?? static::productValue($parent, 'purchase_pack_units')
            ?? 1;

        $min = static::productValue($row, 'purchase_min_quantity')
            ?? static::productValue($parent, 'purchase_min_quantity')
            ?? 0;

        $multiple = static::productValue($row, 'purchase_multiple_quantity')
            ?? static::productValue($parent, 'purchase_multiple_quantity')
            ?? 0;

        if ((float) $multiple <= 0) {
            $multiple = (float) $pack > 0 ? $pack : 1;
        }

        return [
            'pack_units' => (float) $pack,
            'purchase_min' => (float) $min,
            'purchase_multiple' => (float) $multiple,
        ];
    }

    protected static function suggestedQuantity(float $baseNeeded, array $purchaseData): float
    {
        $suggested = max(0, $baseNeeded);

        $purchaseMin = (float) ($purchaseData['purchase_min'] ?? 0);

        if ($purchaseMin > 0 && $suggested < $purchaseMin) {
            $suggested = $purchaseMin;
        }

        $multiple = (float) ($purchaseData['purchase_multiple'] ?? 0);

        if ($multiple <= 0) {
            $multiple = (float) ($purchaseData['pack_units'] ?? 1);
        }

        if ($multiple > 0) {
            $suggested = ceil($suggested / $multiple) * $multiple;
        }

        return $suggested;
    }

    protected static function latestPurchaseSupplierLabel(object $rule): string
    {
        $candidateTables = [
            ['lines' => 'purchase_order_lines', 'header' => 'purchase_orders'],
            ['lines' => 'purchase_lines', 'header' => 'purchases'],
            ['lines' => 'purchase_items', 'header' => 'purchases'],
        ];

        foreach ($candidateTables as $candidate) {
            $lineTable = $candidate['lines'];
            $headerTable = $candidate['header'];

            if (! Schema::hasTable($lineTable) || ! Schema::hasTable($headerTable)) {
                continue;
            }

            if (! Schema::hasColumn($lineTable, 'product_id')) {
                continue;
            }

            $foreign = static::firstColumn($lineTable, [
                'purchase_order_id',
                'purchase_id',
                'order_id',
            ]);

            if (! $foreign) {
                continue;
            }

            $dateColumn = static::firstColumn($headerTable, [
                'purchase_date',
                'order_date',
                'date',
                'created_at',
            ]);

            $supplierIdColumn = static::firstColumn($headerTable, [
                'supplier_id',
                'vendor_id',
                'contact_id',
                'partner_id',
            ]);

            $supplierNameColumn = static::firstColumn($headerTable, [
                'supplier_name',
                'vendor_name',
                'partner_name',
            ]);

            $query = DB::table($lineTable)
                ->join($headerTable, $headerTable . '.id', '=', $lineTable . '.' . $foreign)
                ->where($lineTable . '.product_id', $rule->product_id);

            if (
                $rule->product_variant_id
                && Schema::hasColumn($lineTable, 'product_variant_id')
            ) {
                $query->where($lineTable . '.product_variant_id', $rule->product_variant_id);
            }

            if ($dateColumn) {
                $query->orderByDesc($headerTable . '.' . $dateColumn);
            } else {
                $query->orderByDesc($lineTable . '.id');
            }

            $select = [];

            if ($supplierIdColumn) {
                $select[] = $headerTable . '.' . $supplierIdColumn . ' as supplier_id';
            }

            if ($supplierNameColumn) {
                $select[] = $headerTable . '.' . $supplierNameColumn . ' as supplier_name';
            }

            if (empty($select)) {
                continue;
            }

            $row = $query->first($select);

            if (! $row) {
                continue;
            }

            if (! empty($row->supplier_name)) {
                return (string) $row->supplier_name;
            }

            if (! empty($row->supplier_id)) {
                return static::supplierLabel((int) $row->supplier_id);
            }
        }

        return 'Sin proveedor sugerido';
    }

    protected static function supplierLabel(int $supplierId): string
    {
        foreach (['suppliers', 'vendors', 'contacts'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $labelColumn = static::firstColumn($table, [
                'name',
                'business_name',
                'legal_name',
                'commercial_name',
                'razon_social',
                'company_name',
            ]);

            if (! $labelColumn) {
                continue;
            }

            $row = DB::table($table)
                ->where('id', $supplierId)
                ->first(['id', $labelColumn]);

            if ($row) {
                return (string) ($row->{$labelColumn} ?? ('Proveedor #' . $supplierId));
            }
        }

        return 'Proveedor #' . $supplierId;
    }

    protected static function productValue(?object $product, string $column): ?float
    {
        if (! $product || ! Schema::hasColumn('products', $column)) {
            return null;
        }

        $value = $product->{$column} ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    protected static function productRow($productId): ?object
    {
        if (! $productId || ! Schema::hasTable('products')) {
            return null;
        }

        return DB::table('products')->where('id', $productId)->first();
    }

    protected static function productLabel($productId, bool $variant = false): string
    {
        $row = static::productRow($productId);

        if (! $row) {
            return '—';
        }

        $reference = '';

        foreach (['internal_reference', 'sku', 'barcode', 'code'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                $value = trim((string) ($row->{$column} ?? ''));

                if ($value !== '') {
                    $reference = $value;
                    break;
                }
            }
        }

        if ($variant) {
            $group = Schema::hasColumn('products', 'variant_group') ? trim((string) ($row->variant_group ?? '')) : '';
            $value = Schema::hasColumn('products', 'variant_value') ? trim((string) ($row->variant_value ?? '')) : '';
            $variantText = $group && $value ? $group . ': ' . $value : ($value ?: (string) ($row->name ?? ''));

            return trim(($reference ? $reference . ' - ' : '') . ($variantText ?: 'Variante #' . $productId));
        }

        $name = Schema::hasColumn('products', 'name') ? trim((string) ($row->name ?? '')) : '';

        return trim(($reference ? $reference . ' - ' : '') . ($name ?: 'Producto #' . $productId));
    }

    protected static function warehouseLabel($warehouseId): string
    {
        return static::labelFromTable('warehouses', $warehouseId, ['code'], ['name']);
    }

    protected static function locationLabel($locationId): string
    {
        return static::labelFromTable('stock_locations', $locationId, ['code'], ['name']);
    }

    protected static function labelFromTable(string $table, $id, array $codeColumns, array $nameColumns): string
    {
        if (! $id || ! Schema::hasTable($table)) {
            return '—';
        }

        $row = DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '—';
        }

        $code = '';
        $name = '';

        foreach ($codeColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $value = trim((string) ($row->{$column} ?? ''));

                if ($value !== '') {
                    $code = $value;
                    break;
                }
            }
        }

        foreach ($nameColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $value = trim((string) ($row->{$column} ?? ''));

                if ($value !== '') {
                    $name = $value;
                    break;
                }
            }
        }

        return trim(($code ? $code . ' - ' : '') . ($name ?: ('#' . $id)));
    }

    protected static function firstColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    protected static function priorityLabel(?string $priority): string
    {
        return match ($priority) {
            'low' => 'Baja',
            'high' => 'Alta',
            'critical' => 'Crítica',
            default => 'Normal',
        };
    }

    protected static function statusFor(float $available, float $min): array
    {
        if ($available <= 0) {
            return [
                'label' => 'Crítico',
                'color' => 'danger',
            ];
        }

        if ($available <= $min) {
            return [
                'label' => 'Requiere reabastecimiento',
                'color' => 'warning',
            ];
        }

        return [
            'label' => 'Suficiente',
            'color' => 'success',
        ];
    }

    protected static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        $user = auth()->user();

        if ($user && isset($user->company_id)) {
            return (int) $user->company_id;
        }

        return null;
    }

protected static function userCanView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (
            method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole([
                'super_admin',
                'Super Admin',
                'Super Administrador',
                'admin',
                'Administrador',
                'Admin Empresa',
                'Admin Grupo',
                'Inventarios',
            ])
        ) {
            return true;
        }

        return method_exists($user, 'can')
            ? $user->can('inventory.view_replenishment_report') || $user->can('reports.inventory')
            : false;
    }
}
