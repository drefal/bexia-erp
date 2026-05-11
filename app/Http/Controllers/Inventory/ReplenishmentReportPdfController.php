<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReplenishmentReportPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($this->userCanView(), 403, 'No tienes permiso para descargar este PDF.');

        $filters = [
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
            'location_id' => $request->integer('location_id') ?: null,
            'priority' => trim((string) $request->query('priority', '')),
            'group_by' => trim((string) $request->query('group_by', 'detail')) ?: 'detail',
            'search' => trim((string) $request->query('search', '')),
            'only_shortages' => $request->boolean('only_shortages', true),
            'company_id' => $request->integer('company_id') ?: $this->currentCompanyId(),
        ];

        $rows = $this->buildRows($filters);
        $groupedRows = $this->groupRows($rows, $filters['group_by']);

        $totals = [
            'rules' => count($rows),
            'shortages' => collect($rows)->where('needs_replenishment', true)->count(),
            'suggested_total' => collect($rows)->sum('suggested'),
        ];

        $data = [
            'title' => 'Reporte de reabastecimiento',
            'generatedAt' => now()->format('d/m/Y H:i'),
            'filters' => $this->filterLabels($filters),
            'groupedRows' => $groupedRows,
            'totals' => $totals,
            'companyName' => $this->companyName((int) ($filters['company_id'] ?? 0)),
            'logoPath' => $this->logoPath((int) ($filters['company_id'] ?? 0)),
        ];

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.inventory.replenishment-report', $data)
                ->setPaper('letter', 'landscape');

            return $pdf->stream('reporte-reabastecimiento-' . now()->format('Ymd-His') . '.pdf');
        }

        if (app()->bound('dompdf.wrapper')) {
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('pdfs.inventory.replenishment-report', $data);
            $pdf->setPaper('letter', 'landscape');

            return $pdf->stream('reporte-reabastecimiento-' . now()->format('Ymd-His') . '.pdf');
        }

        abort(500, 'DOMPDF no está disponible.');
    }

    protected function buildRows(array $filters): array
    {
        if (! Schema::hasTable('stock_replenishment_rules')) {
            return [];
        }

        $companyId = $filters['company_id'] ?? $this->currentCompanyId();

        $query = DB::table('stock_replenishment_rules')
            ->where('is_active', true);

        if ($companyId && Schema::hasColumn('stock_replenishment_rules', 'company_id')) {
            $query->where('company_id', $companyId);
        } elseif (Schema::hasColumn('stock_replenishment_rules', 'company_id')) {
            $query->whereNull('company_id');
        }

        if ($filters['warehouse_id']) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if ($filters['location_id']) {
            $query->where('location_id', $filters['location_id']);
        }

        if ($filters['priority'] !== '') {
            $query->where('priority', $filters['priority']);
        }

        $rules = $query
            ->orderBy('warehouse_id')
            ->orderBy('location_id')
            ->orderBy('priority')
            ->orderBy('product_id')
            ->limit(3000)
            ->get();

        $rows = [];

        foreach ($rules as $rule) {
            $available = $this->availableQuantity($rule);
            $min = (float) ($rule->min_quantity ?? 0);
            $max = (float) ($rule->max_quantity ?? 0);

            $needsReplenishment = $available <= $min;
            $baseNeeded = max(0, $max - $available);
            $purchaseData = $this->purchaseData($rule);

            $suggested = $needsReplenishment
                ? $this->suggestedQuantity($baseNeeded, $purchaseData)
                : 0;

            $status = $this->statusFor($available, $min);

            $row = [
                'warehouse' => $this->warehouseLabel($rule->warehouse_id),
                'location' => $this->locationLabel($rule->location_id),
                'product' => $this->productLabel($rule->product_id),
                'variant' => $rule->product_variant_id ? $this->productLabel($rule->product_variant_id, true) : '-',
                'available' => $available,
                'min' => $min,
                'max' => $max,
                'base_needed' => $baseNeeded,
                'pack_units' => $purchaseData['pack_units'],
                'purchase_min' => $purchaseData['purchase_min'],
                'purchase_multiple' => $purchaseData['purchase_multiple'],
                'suggested' => $suggested,
                'priority' => (string) ($rule->priority ?: 'normal'),
                'priority_label' => $this->priorityLabel($rule->priority ?: 'normal'),
                'supplier' => $this->latestPurchaseSupplierLabel($rule),
                'status' => $status,
                'needs_replenishment' => $needsReplenishment,
            ];

            $needle = mb_strtolower(trim($filters['search']));

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

            if ($filters['only_shortages'] && ! $needsReplenishment) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    protected function groupRows(array $rows, string $groupBy): array
    {
        if ($groupBy === 'detail') {
            return ['Detalle' => $rows];
        }

        $groups = [];

        foreach ($rows as $row) {
            $key = match ($groupBy) {
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

    protected function availableQuantity(object $rule): float
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

    protected function purchaseData(object $rule): array
    {
        $variant = $rule->product_variant_id ? $this->productRow($rule->product_variant_id) : null;
        $parent = $this->productRow($rule->product_id);

        $pack = $this->productValue($variant, 'purchase_pack_units')
            ?? $this->productValue($parent, 'purchase_pack_units')
            ?? 1;

        $min = $this->productValue($variant, 'purchase_min_quantity')
            ?? $this->productValue($parent, 'purchase_min_quantity')
            ?? 0;

        $multiple = $this->productValue($variant, 'purchase_multiple_quantity')
            ?? $this->productValue($parent, 'purchase_multiple_quantity')
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

    protected function suggestedQuantity(float $baseNeeded, array $purchaseData): float
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

    protected function latestPurchaseSupplierLabel(object $rule): string
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

            $foreign = $this->firstColumn($lineTable, ['purchase_order_id', 'purchase_id', 'order_id']);

            if (! $foreign) {
                continue;
            }

            $dateColumn = $this->firstColumn($headerTable, ['purchase_date', 'order_date', 'date', 'created_at']);
            $supplierIdColumn = $this->firstColumn($headerTable, ['supplier_id', 'vendor_id', 'contact_id', 'partner_id']);
            $supplierNameColumn = $this->firstColumn($headerTable, ['supplier_name', 'vendor_name', 'partner_name']);

            $query = DB::table($lineTable)
                ->join($headerTable, $headerTable . '.id', '=', $lineTable . '.' . $foreign)
                ->where($lineTable . '.product_id', $rule->product_id);

            if ($rule->product_variant_id && Schema::hasColumn($lineTable, 'product_variant_id')) {
                $query->where($lineTable . '.product_variant_id', $rule->product_variant_id);
            }

            $dateColumn
                ? $query->orderByDesc($headerTable . '.' . $dateColumn)
                : $query->orderByDesc($lineTable . '.id');

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
                return $this->supplierLabel((int) $row->supplier_id);
            }
        }

        return 'Sin proveedor sugerido';
    }

    protected function supplierLabel(int $supplierId): string
    {
        foreach (['suppliers', 'vendors', 'contacts'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $labelColumn = $this->firstColumn($table, [
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

            $row = DB::table($table)->where('id', $supplierId)->first(['id', $labelColumn]);

            if ($row) {
                return (string) ($row->{$labelColumn} ?? ('Proveedor #' . $supplierId));
            }
        }

        return 'Proveedor #' . $supplierId;
    }

    protected function filterLabels(array $filters): array
    {
        return [
            'Almacén' => $filters['warehouse_id'] ? $this->warehouseLabel($filters['warehouse_id']) : 'Todos',
            'Ubicación' => $filters['location_id'] ? $this->locationLabel($filters['location_id']) : 'Todas',
            'Prioridad' => $filters['priority'] !== '' ? $this->priorityLabel($filters['priority']) : 'Todas',
            'Agrupado por' => match ($filters['group_by']) {
                'supplier' => 'Proveedor sugerido',
                'warehouse_location' => 'Almacén / ubicación',
                'priority' => 'Prioridad',
                default => 'Detalle',
            },
            'Solo faltantes' => $filters['only_shortages'] ? 'Sí' : 'No',
            'Búsqueda' => $filters['search'] ?: '-',
            'Empresa ID' => $filters['company_id'] ?: '-',
        ];
    }

    protected function productValue(?object $product, string $column): ?float
    {
        if (! $product || ! Schema::hasColumn('products', $column)) {
            return null;
        }

        $value = $product->{$column} ?? null;

        return ($value !== null && $value !== '') ? (float) $value : null;
    }

    protected function productRow($productId): ?object
    {
        if (! $productId || ! Schema::hasTable('products')) {
            return null;
        }

        return DB::table('products')->where('id', $productId)->first();
    }

    protected function productLabel($productId, bool $variant = false): string
    {
        $row = $this->productRow($productId);

        if (! $row) {
            return '-';
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

    protected function warehouseLabel($warehouseId): string
    {
        return $this->labelFromTable('warehouses', $warehouseId, ['code'], ['name']);
    }

    protected function locationLabel($locationId): string
    {
        return $this->labelFromTable('stock_locations', $locationId, ['code'], ['name']);
    }

    protected function labelFromTable(string $table, $id, array $codeColumns, array $nameColumns): string
    {
        if (! $id || ! Schema::hasTable($table)) {
            return '-';
        }

        $row = DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '-';
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

    protected function firstColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    protected function priorityLabel(?string $priority): string
    {
        return match ($priority) {
            'low' => 'Baja',
            'high' => 'Alta',
            'critical' => 'Crítica',
            default => 'Normal',
        };
    }

    protected function statusFor(float $available, float $min): string
    {
        if ($available <= 0) {
            return 'Crítico';
        }

        if ($available <= $min) {
            return 'Requiere reabastecimiento';
        }

        return 'Suficiente';
    }

protected function companyName(?int $companyId = null): string
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            foreach (['name', 'business_name', 'legal_name', 'razon_social', 'company_name'] as $field) {
                if (isset($tenant->{$field}) && trim((string) $tenant->{$field}) !== '') {
                    return (string) $tenant->{$field};
                }
            }
        }

        if ($companyId && Schema::hasTable('companies')) {
            $company = DB::table('companies')->where('id', $companyId)->first();

            if ($company) {
                foreach (['name', 'business_name', 'legal_name', 'razon_social', 'company_name'] as $field) {
                    if (isset($company->{$field}) && trim((string) $company->{$field}) !== '') {
                        return (string) $company->{$field};
                    }
                }
            }
        }

        return config('app.name', 'Bexia ERP');
    }

protected function logoPath(?int $companyId = null): ?string
    {
        $candidates = [];

        $tenant = Filament::getTenant();

        if ($tenant) {
            foreach ([
                'logo_path',
                'logo',
                'image_path',
                'logo_url',
                'brand_logo',
                'company_logo',
            ] as $field) {
                if (isset($tenant->{$field}) && trim((string) $tenant->{$field}) !== '') {
                    $candidates[] = (string) $tenant->{$field};
                }
            }
        }

        if ($companyId && Schema::hasTable('companies')) {
            $company = DB::table('companies')->where('id', $companyId)->first();

            if ($company) {
                foreach ([
                    'logo_path',
                    'logo',
                    'image_path',
                    'logo_url',
                    'brand_logo',
                    'company_logo',
                ] as $field) {
                    if (isset($company->{$field}) && trim((string) $company->{$field}) !== '') {
                        $candidates[] = (string) $company->{$field};
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            $path = trim($candidate);

            if ($path === '') {
                continue;
            }

            // Si viene como URL o /storage/archivo, convertirlo a ruta local.
            $path = parse_url($path, PHP_URL_PATH) ?: $path;
            $path = ltrim($path, '/');

            $possiblePaths = [
                base_path($path),
                public_path($path),
                public_path('storage/' . preg_replace('#^storage/#', '', $path)),
                storage_path('app/public/' . preg_replace('#^(storage|public)/#', '', $path)),
                storage_path('app/' . preg_replace('#^storage/#', '', $path)),
            ];

            foreach ($possiblePaths as $possiblePath) {
                if ($possiblePath && is_file($possiblePath)) {
                    return $possiblePath;
                }
            }
        }

        foreach ([
            public_path('images/logo.png'),
            public_path('logo.png'),
            public_path('favicon.png'),
            public_path('storage/logo.png'),
        ] as $fallback) {
            if (is_file($fallback)) {
                return $fallback;
            }
        }

        return null;
    }

    protected function currentCompanyId(): ?int
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

    protected function userCanView(): bool
    {
        // La ruta ya tiene middleware auth.
        // Si el usuario está autenticado en el panel, puede descargar el PDF del reporte.
        return auth()->check();
    }
}
