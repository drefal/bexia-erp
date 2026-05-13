<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SuggestedPurchaseList extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Lista sugerida de compra';

    protected static ?string $title = 'Lista sugerida de compra';

    protected static ?int $navigationSort = 76;

    protected static string $view = 'filament.pages.suggested-purchase-list';

    public ?int $warehouseId = null;

    public ?int $locationId = null;

    public string $priority = '';

    public string $search = '';

    public bool $onlyShortages = true;

    public string|float|null $budgetAmount = null;


    public function mount(): void
    {
        $request = request();

        $this->warehouseId = $request->integer('warehouse_id') ?: null;
        $this->locationId = $request->integer('location_id') ?: null;
        $this->priority = trim((string) $request->query('priority', ''));
        $this->search = trim((string) $request->query('search', ''));

        if ($request->has('only_shortages')) {
            $this->onlyShortages = $request->boolean('only_shortages');
        }

        if ($request->has('budget_amount')) {
            $this->budgetAmount = $request->query('budget_amount');
        }
    }

    public function updatedWarehouseId(): void
    {
        $this->locationId = null;
    }

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
            Action::make('generatePurchaseRequests')
                ->label('Generar solicitud de compra')
                ->icon('heroicon-o-document-plus')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Generar solicitudes de compra')
                ->modalDescription('Se crearán solicitudes agrupadas por proveedor con las cantidades de “Compra sugerida ahora”. No afecta inventario.')
                ->modalSubmitActionLabel('Generar solicitudes')
                ->action(fn () => $this->generatePurchaseRequests()),

            Action::make('viewPdf')
                ->label('Ver PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (): string => route('inventory.suggested-purchase-list.pdf', [
                    'warehouse_id' => $this->warehouseId,
                    'location_id' => $this->locationId,
                    'priority' => $this->priority,
                    'search' => $this->search,
                    'only_shortages' => $this->onlyShortages ? 1 : 0,
                    'budget_amount' => $this->budgetAmount,
                    'company_id' => static::currentCompanyId(),
                ]))
                ->openUrlInNewTab(),
        ];
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
        $rows = static::buildRows([
            'company_id' => static::currentCompanyId(),
            'warehouse_id' => $this->warehouseId,
            'location_id' => $this->locationId,
            'priority' => $this->priority,
            'search' => $this->search,
            'only_shortages' => $this->onlyShortages,
        ]);

        return static::applyBudget($rows, static::normalizeBudgetAmount($this->budgetAmount));
    }

    public function getGroupedRowsProperty(): array
    {
        $groups = [];

        foreach ($this->rows as $row) {
            $key = $row['supplier'] ?: 'Sin proveedor sugerido';
            $groups[$key][] = $row;
        }

        ksort($groups);

        return $groups;
    }

    public function getTotalsProperty(): array
    {
        $rows = $this->rows;

        return [
            'lines' => count($rows),
            'included_lines' => collect($rows)->filter(fn (array $row): bool => (float) ($row['approved_quantity'] ?? 0) > 0)->count(),
            'out_lines' => collect($rows)->filter(fn (array $row): bool => (float) ($row['pending_quantity'] ?? 0) > 0)->count(),
            'full_total' => collect($rows)->sum(fn (array $row): float => (float) ($row['full_estimated_total'] ?? $row['estimated_total'] ?? 0)),
            'included_total' => collect($rows)->sum(fn (array $row): float => (float) ($row['approved_total'] ?? 0)),
            'out_total' => collect($rows)->sum(fn (array $row): float => (float) ($row['pending_total'] ?? 0)),
            'budget' => static::normalizeBudgetAmount($this->budgetAmount),
        ];
    }



    public static function buildRows(array $filters): array
    {
        if (! Schema::hasTable('stock_replenishment_rules')) {
            return [];
        }

        $companyId = $filters['company_id'] ?? static::currentCompanyId();

        $query = DB::table('stock_replenishment_rules')
            ->where('is_active', true);

        if ($companyId && Schema::hasColumn('stock_replenishment_rules', 'company_id')) {
            $query->where('company_id', $companyId);
        } elseif (Schema::hasColumn('stock_replenishment_rules', 'company_id')) {
            $query->whereNull('company_id');
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (($filters['priority'] ?? '') !== '') {
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
            $available = static::availableQuantity($rule);
            $min = (float) ($rule->min_quantity ?? 0);
            $max = (float) ($rule->max_quantity ?? 0);

            $needsReplenishment = $available <= $min;

            if (($filters['only_shortages'] ?? true) && ! $needsReplenishment) {
                continue;
            }

            $baseNeeded = max(0, $max - $available);
            $purchaseData = static::purchaseData($rule);

            $suggestedQuantity = $needsReplenishment
                ? static::suggestedQuantity($baseNeeded, $purchaseData)
                : 0;

            if ($suggestedQuantity <= 0) {
                continue;
            }

            $costData = static::estimatedCostData($rule);

            // Los costos base se manejan sin IVA. Para presupuesto usamos importe con IVA.
            $unitCostWithoutTax = (float) ($costData['unit_cost'] ?? 0);
            $purchaseTaxRate = static::purchaseTaxRate($rule);
            $unitCostWithTax = $unitCostWithoutTax * (1 + ($purchaseTaxRate / 100));

            $estimatedTotalWithoutTax = $suggestedQuantity * $unitCostWithoutTax;
            $estimatedTotal = $suggestedQuantity * $unitCostWithTax;

            $status = static::statusFor($available, $min);
            $urgencyScore = static::urgencyScore($available, $min, (string) ($rule->priority ?: 'normal'));

            $row = [
                'company_id' => $rule->company_id ?? static::currentCompanyId(),
                'warehouse_id' => $rule->warehouse_id,
                'location_id' => $rule->location_id,
                'product_id' => $rule->product_id,
                'product_variant_id' => $rule->product_variant_id,
                'warehouse' => static::warehouseLabel($rule->warehouse_id),
                'location' => static::locationLabel($rule->location_id),
                'product' => static::productLabel($rule->product_id),
                'variant' => $rule->product_variant_id ? static::productLabel($rule->product_variant_id, true) : '—',
                'available' => $available,
                'min' => $min,
                'max' => $max,
                'base_needed' => $baseNeeded,
                'pack_units' => $purchaseData['pack_units'],
                'purchase_min' => $purchaseData['purchase_min'],
                'purchase_multiple' => $purchaseData['purchase_multiple'],
                'suggested' => $suggestedQuantity,
                'unit_cost_without_tax' => $unitCostWithoutTax,
                'purchase_tax_rate' => $purchaseTaxRate,
                'unit_cost' => $unitCostWithTax,
                'estimated_total_without_tax' => $estimatedTotalWithoutTax,
                'estimated_total' => $estimatedTotal,
                'cost_source' => $costData['source'],
                'supplier' => static::latestPurchaseSupplierLabel($rule),
                'priority' => (string) ($rule->priority ?: 'normal'),
                'priority_label' => static::priorityLabel($rule->priority ?: 'normal'),
                'status' => $status,
                'urgency_score' => $urgencyScore,
                'included_in_budget' => false,
            ];

            $needle = mb_strtolower(trim((string) ($filters['search'] ?? '')));

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

            $rows[] = $row;
        }

        usort($rows, function (array $a, array $b): int {
            if ($a['urgency_score'] === $b['urgency_score']) {
                return $b['estimated_total'] <=> $a['estimated_total'];
            }

            return $b['urgency_score'] <=> $a['urgency_score'];
        });

        return $rows;
    }


    public static function normalizeBudgetAmount($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_string($value)) {
            $value = str_replace(['$', ',', ' '], '', trim($value));
        }

        return is_numeric($value) ? max(0, (float) $value) : 0;
    }

    public static function applyBudget(array $rows, float $budget): array
    {
        foreach ($rows as &$row) {
            $suggested = (float) ($row['suggested'] ?? 0);
            $unitCost = (float) ($row['unit_cost'] ?? 0);
            $fullTotal = $suggested * $unitCost;

            $row['full_suggested'] = $suggested;
            $row['full_estimated_total'] = $fullTotal;

            $row['approved_quantity'] = $budget > 0 ? 0 : $suggested;
            $row['approved_total'] = $budget > 0 ? 0 : $fullTotal;

            $row['pending_quantity'] = $budget > 0 ? $suggested : 0;
            $row['pending_total'] = $budget > 0 ? $fullTotal : 0;

            $row['budget_status'] = $budget > 0 ? 'Pendiente' : 'Sugerido';
            $row['allocation_weight'] = static::purchaseAllocationWeight($row);
        }
        unset($row);

        if ($budget <= 0 || empty($rows)) {
            return $rows;
        }

        $eligibleIndexes = [];

        foreach ($rows as $index => $row) {
            if (
                (float) ($row['full_suggested'] ?? 0) > 0
                && (float) ($row['unit_cost'] ?? 0) > 0
            ) {
                $eligibleIndexes[] = $index;
            }
        }

        if (empty($eligibleIndexes)) {
            return $rows;
        }

        $totalWeight = 0;

        foreach ($eligibleIndexes as $index) {
            $totalWeight += max(0.01, (float) ($rows[$index]['allocation_weight'] ?? 0.01));
        }

        $used = 0.0;

        foreach ($eligibleIndexes as $index) {
            $row = $rows[$index];

            $unitCost = (float) ($row['unit_cost'] ?? 0);
            $suggested = (float) ($row['full_suggested'] ?? 0);
            $multiple = static::quantityMultipleForRow($row);

            if ($unitCost <= 0 || $suggested <= 0) {
                continue;
            }

            $weight = max(0.01, (float) ($row['allocation_weight'] ?? 0.01));
            $targetMoney = $budget * ($weight / max($totalWeight, 0.01));
            $targetQuantity = min($suggested, $targetMoney / $unitCost);
            $quantity = static::roundDownToMultiple($targetQuantity, $multiple);

            if ($quantity <= 0) {
                continue;
            }

            $quantity = min($quantity, $suggested);
            $lineTotal = $quantity * $unitCost;

            if (($used + $lineTotal) <= ($budget + 0.000001)) {
                $rows[$index]['approved_quantity'] = $quantity;
                $rows[$index]['approved_total'] = $lineTotal;
                $used += $lineTotal;
            }
        }

        $remainingBudget = max(0, $budget - $used);

        usort($eligibleIndexes, function (int $a, int $b) use ($rows): int {
            $scoreA = (float) ($rows[$a]['allocation_weight'] ?? 0);
            $scoreB = (float) ($rows[$b]['allocation_weight'] ?? 0);

            if ($scoreA === $scoreB) {
                return ((float) ($rows[$b]['full_estimated_total'] ?? 0)) <=> ((float) ($rows[$a]['full_estimated_total'] ?? 0));
            }

            return $scoreB <=> $scoreA;
        });

        foreach ($eligibleIndexes as $index) {
            if ($remainingBudget <= 0) {
                break;
            }

            $row = $rows[$index];
            $unitCost = (float) ($row['unit_cost'] ?? 0);
            $suggested = (float) ($row['full_suggested'] ?? 0);
            $approved = (float) ($row['approved_quantity'] ?? 0);
            $pending = max(0, $suggested - $approved);
            $multiple = static::quantityMultipleForRow($row);

            if ($unitCost <= 0 || $pending <= 0) {
                continue;
            }

            $affordableQuantity = static::roundDownToMultiple($remainingBudget / $unitCost, $multiple);
            $extraQuantity = min($pending, $affordableQuantity);

            if ($extraQuantity <= 0) {
                continue;
            }

            $extraTotal = $extraQuantity * $unitCost;

            if ($extraTotal <= ($remainingBudget + 0.000001)) {
                $rows[$index]['approved_quantity'] = $approved + $extraQuantity;
                $rows[$index]['approved_total'] = ((float) ($rows[$index]['approved_total'] ?? 0)) + $extraTotal;
                $remainingBudget -= $extraTotal;
            }
        }

        foreach ($rows as &$row) {
            $suggested = (float) ($row['full_suggested'] ?? 0);
            $approved = (float) ($row['approved_quantity'] ?? 0);
            $unitCost = (float) ($row['unit_cost'] ?? 0);

            $pending = max(0, $suggested - $approved);

            $row['pending_quantity'] = $pending;
            $row['pending_total'] = $pending * $unitCost;

            if ($approved <= 0) {
                $row['budget_status'] = 'Pendiente si alcanza';
            } elseif ($pending > 0) {
                $row['budget_status'] = 'Compra parcial';
            } else {
                $row['budget_status'] = 'Compra completa';
            }
        }
        unset($row);

        return $rows;
    }



    protected static function purchaseAllocationWeight(array $row): float
    {
        $priority = (string) ($row['priority'] ?? 'normal');

        $priorityWeight = match ($priority) {
            'critical' => 100,
            'high' => 70,
            'normal' => 40,
            'low' => 20,
            default => 40,
        };

        $available = (float) ($row['available'] ?? 0);
        $min = (float) ($row['min'] ?? 0);

        $stockWeight = 0;

        if ($available <= 0) {
            $stockWeight = 80;
        } elseif ($min > 0) {
            $ratio = max(0, min(1, $available / $min));
            $stockWeight = (1 - $ratio) * 60;
        }

        $amountWeight = min(25, max(0, ((float) ($row['full_estimated_total'] ?? $row['estimated_total'] ?? 0)) / 1000));

        return $priorityWeight + $stockWeight + $amountWeight;
    }

    protected static function quantityMultipleForRow(array $row): float
    {
        $multiple = (float) ($row['purchase_multiple'] ?? 0);

        if ($multiple <= 0) {
            $multiple = (float) ($row['pack_units'] ?? 0);
        }

        return $multiple > 0 ? $multiple : 1;
    }

    protected static function roundDownToMultiple(float $quantity, float $multiple): float
    {
        if ($quantity <= 0) {
            return 0;
        }

        if ($multiple <= 0) {
            return $quantity;
        }

        return floor($quantity / $multiple) * $multiple;
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
        $variant = $rule->product_variant_id ? static::productRow($rule->product_variant_id) : null;
        $parent = static::productRow($rule->product_id);

        $pack = static::productValue($variant, 'purchase_pack_units')
            ?? static::productValue($parent, 'purchase_pack_units')
            ?? 1;

        $min = static::productValue($variant, 'purchase_min_quantity')
            ?? static::productValue($parent, 'purchase_min_quantity')
            ?? 0;

        $multiple = static::productValue($variant, 'purchase_multiple_quantity')
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


    protected static function purchaseTaxRate(object $rule): float
    {
        $variant = $rule->product_variant_id ? static::productRow($rule->product_variant_id) : null;
        $parent = static::productRow($rule->product_id);

        $tax = static::productValue($variant, 'purchase_tax_rate')
            ?? static::productValue($parent, 'purchase_tax_rate')
            ?? 16;

        return (float) $tax;
    }

    protected static function estimatedCostData(object $rule): array
    {
        $latest = static::latestPurchaseCost($rule);

        if ($latest !== null && $latest > 0) {
            return [
                'unit_cost' => $latest,
                'source' => 'Última compra',
            ];
        }

        $variant = $rule->product_variant_id ? static::productRow($rule->product_variant_id) : null;
        $parent = static::productRow($rule->product_id);

        foreach (['average_cost_without_tax', 'standard_cost', 'purchase_price', 'cost'] as $column) {
            $value = static::productValue($variant, $column)
                ?? static::productValue($parent, $column);

            if ($value !== null && $value > 0) {
                return [
                    'unit_cost' => (float) $value,
                    'source' => match ($column) {
                        'average_cost_without_tax' => 'Costo promedio',
                        'standard_cost' => 'Costo estándar',
                        'purchase_price' => 'Precio de compra',
                        default => 'Costo producto',
                    },
                ];
            }
        }

        return [
            'unit_cost' => 0,
            'source' => 'Sin costo',
        ];
    }

    protected static function latestPurchaseCost(object $rule): ?float
    {
        $candidateTables = [
            ['lines' => 'purchase_order_lines', 'header' => 'purchase_orders'],
            ['lines' => 'purchase_lines', 'header' => 'purchases'],
            ['lines' => 'purchase_items', 'header' => 'purchases'],
        ];

        foreach ($candidateTables as $candidate) {
            $lineTable = $candidate['lines'];
            $headerTable = $candidate['header'];

            if (! Schema::hasTable($lineTable)) {
                continue;
            }

            if (! Schema::hasColumn($lineTable, 'product_id')) {
                continue;
            }

            $costColumn = static::firstColumn($lineTable, [
                'unit_cost',
                'price_unit',
                'unit_price',
                'cost',
                'purchase_price',
                'price',
            ]);

            if (! $costColumn) {
                continue;
            }

            $query = DB::table($lineTable)
                ->where($lineTable . '.product_id', $rule->product_id)
                ->whereNotNull($lineTable . '.' . $costColumn)
                ->where($lineTable . '.' . $costColumn, '>', 0);

            if ($rule->product_variant_id && Schema::hasColumn($lineTable, 'product_variant_id')) {
                $query->where($lineTable . '.product_variant_id', $rule->product_variant_id);
            }

            if (Schema::hasTable($headerTable)) {
                $foreign = static::firstColumn($lineTable, [
                    'purchase_order_id',
                    'purchase_id',
                    'order_id',
                ]);

                if ($foreign) {
                    $query->leftJoin($headerTable, $headerTable . '.id', '=', $lineTable . '.' . $foreign);

                    $dateColumn = static::firstColumn($headerTable, [
                        'purchase_date',
                        'order_date',
                        'date',
                        'created_at',
                    ]);

                    $dateColumn
                        ? $query->orderByDesc($headerTable . '.' . $dateColumn)
                        : $query->orderByDesc($lineTable . '.id');
                } else {
                    $query->orderByDesc($lineTable . '.id');
                }
            } else {
                $query->orderByDesc($lineTable . '.id');
            }

            $row = $query->first([$lineTable . '.' . $costColumn . ' as unit_cost']);

            if ($row && (float) $row->unit_cost > 0) {
                return (float) $row->unit_cost;
            }
        }

        return null;
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

            $foreign = static::firstColumn($lineTable, ['purchase_order_id', 'purchase_id', 'order_id']);

            if (! $foreign) {
                continue;
            }

            $dateColumn = static::firstColumn($headerTable, ['purchase_date', 'order_date', 'date', 'created_at']);
            $supplierIdColumn = static::firstColumn($headerTable, ['supplier_id', 'vendor_id', 'contact_id', 'partner_id']);
            $supplierNameColumn = static::firstColumn($headerTable, ['supplier_name', 'vendor_name', 'partner_name']);

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

            $row = DB::table($table)->where('id', $supplierId)->first(['id', $labelColumn]);

            if ($row) {
                return (string) ($row->{$labelColumn} ?? ('Proveedor #' . $supplierId));
            }
        }

        return 'Proveedor #' . $supplierId;
    }

    protected static function urgencyScore(float $available, float $min, string $priority): float
    {
        $priorityScore = match ($priority) {
            'critical' => 4000,
            'high' => 3000,
            'normal' => 2000,
            'low' => 1000,
            default => 2000,
        };

        $stockScore = 0;

        if ($available <= 0) {
            $stockScore = 900;
        } elseif ($min > 0) {
            $ratio = max(0, min(1, $available / $min));
            $stockScore = (1 - $ratio) * 800;
        }

        return $priorityScore + $stockScore;
    }

    protected static function statusFor(float $available, float $min): string
    {
        if ($available <= 0) {
            return 'Crítico';
        }

        if ($available <= $min) {
            return 'Requiere compra';
        }

        return 'Suficiente';
    }

    protected static function productValue(?object $product, string $column): ?float
    {
        if (! $product || ! Schema::hasColumn('products', $column)) {
            return null;
        }

        $value = $product->{$column} ?? null;

        return ($value !== null && $value !== '') ? (float) $value : null;
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
                'Compras',
                'Reportes',
            ])
        ) {
            return true;
        }

        return method_exists($user, 'can')
            ? $user->can('inventory.view_suggested_purchase_list') || $user->can('inventory.view') || $user->can('purchases.view')
            : false;
    }

    public function generatePurchaseRequests()
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('purchase_requests')) {
            \Filament\Notifications\Notification::make()
                ->title('No existe el módulo de solicitudes de compra')
                ->body('Ejecuta la migración antes de generar solicitudes.')
                ->danger()
                ->send();

            return null;
        }

        $rows = collect($this->rows)
            ->filter(fn (array $row): bool => (float) ($row['approved_quantity'] ?? $row['suggested'] ?? 0) > 0)
            ->values();

        if ($rows->isEmpty()) {
            \Filament\Notifications\Notification::make()
                ->title('No hay líneas para generar')
                ->body('Captura un presupuesto o revisa que existan cantidades sugeridas para comprar ahora.')
                ->warning()
                ->send();

            return null;
        }

        $created = [];

        \Illuminate\Support\Facades\DB::transaction(function () use ($rows, &$created): void {
            $groups = $rows->groupBy(function (array $row): string {
                $supplier = trim((string) ($row['supplier'] ?? '')) ?: 'Sin proveedor sugerido';
                $warehouseId = (string) ($row['warehouse_id'] ?? '');
                $locationId = (string) ($row['location_id'] ?? '');

                return $supplier . '||' . $warehouseId . '||' . $locationId;
            });

            $sequenceOffset = 0;

            foreach ($groups as $groupKey => $groupRows) {
                $sequenceOffset++;

                $firstRow = $groupRows->first();

                $supplierName = trim((string) ($firstRow['supplier'] ?? '')) ?: 'Sin proveedor sugerido';

                $request = \App\Models\PurchaseRequest::create([
                    'company_id' => static::currentCompanyId(),
                    'number' => static::makePurchaseRequestNumber($sequenceOffset),
                    'status' => 'draft',
                    'supplier_id' => null,
                    'supplier_name' => $supplierName,
                    'warehouse_id' => $firstRow['warehouse_id'] ?? null,
                    'location_id' => $firstRow['location_id'] ?? null,
                    'warehouse_label' => $firstRow['warehouse'] ?? null,
                    'location_label' => $firstRow['location'] ?? null,
                    'requested_by_user_id' => auth()->id(),
                    'source' => 'suggested_purchase_list',
                    'budget_amount' => static::normalizeBudgetAmount($this->budgetAmount),
                    'total_without_tax' => 0,
                    'total_tax' => 0,
                    'total_with_tax' => 0,
                    'notes' => 'Generada desde Lista sugerida de compra.',
                    'requested_at' => now(),
                ]);

                $totalWithoutTax = 0.0;
                $totalTax = 0.0;
                $totalWithTax = 0.0;

                foreach ($groupRows as $row) {
                    $quantity = (float) ($row['approved_quantity'] ?? $row['suggested'] ?? 0);

                    if ($quantity <= 0) {
                        continue;
                    }

                    $taxRate = (float) ($row['purchase_tax_rate'] ?? 0);
                    $unitWithTax = (float) ($row['unit_cost'] ?? 0);

                    $unitWithoutTax = (float) ($row['unit_cost_without_tax'] ?? 0);

                    if ($unitWithoutTax <= 0 && $unitWithTax > 0) {
                        $unitWithoutTax = $taxRate > 0
                            ? $unitWithTax / (1 + ($taxRate / 100))
                            : $unitWithTax;
                    }

                    $lineWithoutTax = $quantity * $unitWithoutTax;
                    $lineWithTax = $quantity * $unitWithTax;
                    $lineTax = max(0, $lineWithTax - $lineWithoutTax);

                    \App\Models\PurchaseRequestLine::create([
                        'purchase_request_id' => $request->id,
                        'company_id' => static::currentCompanyId(),

                        'warehouse_id' => $row['warehouse_id'] ?? null,
                        'location_id' => $row['location_id'] ?? null,

                        'product_id' => $row['product_id'] ?? null,
                        'product_variant_id' => $row['product_variant_id'] ?? null,

                        'product_label' => $row['product'] ?? null,
                        'variant_label' => $row['variant'] ?? null,
                        'warehouse_label' => $row['warehouse'] ?? null,
                        'location_label' => $row['location'] ?? null,

                        'available_quantity' => (float) ($row['available'] ?? 0),
                        'suggested_quantity' => (float) ($row['full_suggested'] ?? $row['suggested'] ?? 0),
                        'requested_quantity' => $quantity,
                        'pending_quantity' => (float) ($row['pending_quantity'] ?? 0),

                        'unit_cost_without_tax' => $unitWithoutTax,
                        'tax_rate' => $taxRate,
                        'unit_cost_with_tax' => $unitWithTax,

                        'line_total_without_tax' => $lineWithoutTax,
                        'line_tax' => $lineTax,
                        'line_total_with_tax' => $lineWithTax,

                        'priority' => $row['priority'] ?? null,
                        'priority_label' => $row['priority_label'] ?? null,
                        'cost_source' => $row['cost_source'] ?? null,

                        'source_data' => $row,
                    ]);

                    $totalWithoutTax += $lineWithoutTax;
                    $totalTax += $lineTax;
                    $totalWithTax += $lineWithTax;
                }

                $request->update([
                    'total_without_tax' => $totalWithoutTax,
                    'total_tax' => $totalTax,
                    'total_with_tax' => $totalWithTax,
                ]);

                $created[] = $request->number;
            }
        });

        \Filament\Notifications\Notification::make()
            ->title('Solicitudes de compra generadas')
            ->body('Se generaron: ' . implode(', ', $created))
            ->success()
            ->send();

        return redirect(\App\Filament\Resources\PurchaseRequestResource::getUrl('index'));
    }

    protected static function makePurchaseRequestNumber(int $offset = 1): string
    {
        $nextId = ((int) (\Illuminate\Support\Facades\DB::table('purchase_requests')->max('id') ?? 0)) + $offset;

        return 'SC-' . now()->format('Ymd') . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
    }


}
