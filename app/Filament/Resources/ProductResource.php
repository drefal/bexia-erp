<?php

namespace App\Filament\Resources;

use Illuminate\Support\HtmlString;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\AccountingAccount;
use App\Models\AccountingSetting;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductCategory;
use App\Models\SatProductServiceCode;
use App\Models\SatUnitCode;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductResource extends Resource
{

protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Productos';
    protected static ?string $navigationLabel = 'Productos';

    protected static ?int $navigationSort = 10;
protected static ?string $modelLabel = 'producto';
    protected static ?string $pluralModelLabel = 'productos';
    protected static ?string $tenantOwnershipRelationshipName = 'company';
    protected static ?string $tenantRelationshipName = 'products';

    protected static function currentCompanyId(): ?int
    {
        return Filament::getTenant()?->getKey();
    }

    protected static function canManage(string $permission): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            return true;
        }

        if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
            return true;
        }

        return $user->can($permission);
    }

    public static function canAccess(): bool
    {
        return static::canManage('inventory.view');
    }

public static function canCreate(): bool
    {
        return static::canManage('inventory.create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManage('inventory.update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManage('inventory.delete');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenant = Filament::getTenant();

        if ($tenant) {
            $query->where('company_id', $tenant->getKey());
        }

        return $query;
    }

    protected static function categoryOptions(): array
    {
        $companyId = static::currentCompanyId();

        if (! $companyId) {
            return [];
        }

        return ProductCategory::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ProductCategory $category) => [
                $category->id => trim(($category->code ? $category->code . ' - ' : '') . $category->name),
            ])
            ->all();
    }

    protected static function unitOptions(): array
    {
        $companyId = static::currentCompanyId();

        if (! $companyId) {
            $options = [];
        }

        return InventoryUnit::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (InventoryUnit $unit) => [
                $unit->id => "{$unit->code} - {$unit->name}",
            ])
            ->all();
            return static::sortFavoriteUnitOptions($options);
    }

    protected static function accountOptions(?string $type = null): array
    {
        $companyId = static::currentCompanyId();

        if (! $companyId) {
            return [];
        }

        return AccountingAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when($type, fn (Builder $query) => $query->where('type', $type))
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (AccountingAccount $account) => [
                $account->id => "{$account->code} - {$account->name}",
            ])
            ->all();
    }

    protected static function responsibleOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->mapWithKeys(fn (User $user) => [
                $user->id => trim(($user->name ?: $user->email) . ' - ' . $user->email, ' -'),
            ])
            ->all();
    }

    protected static function satProductOptions(): array
    {
        return SatProductServiceCode::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->limit(200)
            ->get()
            ->mapWithKeys(fn (SatProductServiceCode $code) => [
                $code->code => "{$code->code} - {$code->description}",
            ])
            ->all();
    }


    protected static function productMetric(?Product $record, string $metric): string
    {
        if (! $record) {
            return '0';
        }

        if (in_array($metric, [
            'on_hand',
            'forecasted',
            'incoming',
            'outgoing',
            'reordering_rules',
            'bom',
            'sold',
            'purchased',
        ], true)) {
            return static::productOperationalMetricFromInventory($record, $metric);
        }


        if (! $record || ! $record->id) {
            return match ($metric) {
                'website' => 'No publicado',
                default => '0',
            };
        }

        return match ($metric) {
            'price_lists' => static::countIfTableExists('product_price_list_items', 'product_id', $record->id),
            'website' => static::websiteStatus($record),
            'on_hand' => static::sumIfTableExists('stock_levels', 'product_id', $record->id, 'quantity_on_hand'),
            'forecasted' => static::sumIfTableExists('stock_levels', 'product_id', $record->id, 'quantity_forecasted'),
            'incoming' => static::countInventoryMovements($record->id, 'in'),
            'outgoing' => static::countInventoryMovements($record->id, 'out'),
            'reordering_rules' => static::countIfTableExists('reordering_rules', 'product_id', $record->id),
            'bom' => static::countIfTableExists('bill_of_materials', 'product_id', $record->id),
            'sold' => static::sumIfTableExists('sales_lines', 'product_id', $record->id, 'quantity'),
            'purchased' => static::sumIfTableExists('purchase_lines', 'product_id', $record->id, 'quantity'),
            default => '0',
        };
    }

    protected static function countIfTableExists(string $table, string $column, int $productId): string
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return '0';
        }

        return (string) DB::table($table)
            ->where($column, $productId)
            ->count();
    }


    protected static function productOperationalMetricFromInventory(Product $record, string $metric): string
    {
        return match ($metric) {
            'on_hand' => static::formatOperationalMetric(static::productOperationalStockQuantity($record, false)),
            'forecasted' => static::formatOperationalMetric(static::productOperationalStockQuantity($record, true)),
            'incoming' => static::formatOperationalMetric(static::productOperationalMovementQuantity($record, 'incoming')),
            'outgoing' => static::formatOperationalMetric(static::productOperationalMovementQuantity($record, 'outgoing')),
            'reordering_rules' => (string) static::productOperationalReorderingRulesCount($record),
            'bom' => (string) static::productOperationalBomCount($record),
            'sold' => static::formatOperationalMetric(static::productOperationalSoldQuantity($record)),
            'purchased' => static::formatOperationalMetric(static::productOperationalPurchasedQuantity($record)),
            default => '0',
        };
    }

    protected static function productOperationalStockQuantity(Product $record, bool $available): float
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('stock_quants')) {
            return 0.0;
        }

        if (! \Illuminate\Support\Facades\Schema::hasColumn('stock_quants', 'quantity')) {
            return 0.0;
        }

        $query = \Illuminate\Support\Facades\DB::table('stock_quants');

        static::applyProductOperationalScope($query, $record, 'stock_quants');

        if (
            (int) ($record->company_id ?? 0) > 0
            && \Illuminate\Support\Facades\Schema::hasColumn('stock_quants', 'company_id')
        ) {
            $query->where('company_id', (int) $record->company_id);
        }

        $quantity = (float) (clone $query)->sum('quantity');

        if (! $available) {
            return $quantity;
        }

        $reserved = 0.0;

        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_quants', 'reserved_quantity')) {
            $reserved = (float) (clone $query)->sum('reserved_quantity');
        }

        return $quantity - $reserved;
    }

    protected static function productOperationalMovementQuantity(Product $record, string $direction): float
    {
        if (
            ! \Illuminate\Support\Facades\Schema::hasTable('stock_movement_lines')
            || ! \Illuminate\Support\Facades\Schema::hasTable('stock_movements')
        ) {
            return 0.0;
        }

        if (! \Illuminate\Support\Facades\Schema::hasColumn('stock_movement_lines', 'done_quantity')) {
            return 0.0;
        }

        $query = \Illuminate\Support\Facades\DB::table('stock_movement_lines as l')
            ->join('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->leftJoin('stock_operation_types as ot', 'ot.id', '=', 'm.stock_operation_type_id')
            ->leftJoin('stock_locations as src', 'src.id', '=', 'm.source_location_id')
            ->leftJoin('stock_locations as dst', 'dst.id', '=', 'm.destination_location_id');

        static::applyProductOperationalScope($query, $record, 'stock_movement_lines', 'l');

        if (
            (int) ($record->company_id ?? 0) > 0
            && \Illuminate\Support\Facades\Schema::hasColumn('stock_movements', 'company_id')
        ) {
            $query->where('m.company_id', (int) $record->company_id);
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_movements', 'status')) {
            $query->whereIn('m.status', ['done', 'confirmed', 'posted', 'completed']);
        }

        $select = [
            'l.done_quantity',
            'l.source_type',
            'm.source_location_id',
            'm.destination_location_id',
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_operation_types', 'code')) {
            $select[] = 'ot.code as operation_code';
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_operation_types', 'operation_kind')) {
            $select[] = 'ot.operation_kind as operation_kind';
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_locations', 'code')) {
            $select[] = 'src.code as source_code';
            $select[] = 'dst.code as destination_code';
        }

        $rows = $query->get($select);

        return (float) $rows->sum(function ($row) use ($direction): float {
            $quantity = (float) ($row->done_quantity ?? 0);

            if (abs($quantity) <= 0.000001) {
                return 0.0;
            }

            $operationCode = mb_strtolower((string) ($row->operation_code ?? ''));
            $operationKind = mb_strtolower((string) ($row->operation_kind ?? ''));
            $sourceType = mb_strtolower((string) ($row->source_type ?? ''));

            $sourceLocationId = (int) ($row->source_location_id ?? 0);
            $destinationLocationId = (int) ($row->destination_location_id ?? 0);

            $isAdjustment = str_contains($sourceType, 'adjustment')
                || str_contains($operationCode, 'ajuste')
                || str_contains($operationKind, 'adjustment');

            if ($isAdjustment) {
                if ($direction === 'incoming') {
                    return $quantity > 0 ? $quantity : 0.0;
                }

                if ($direction === 'outgoing') {
                    return $quantity < 0 ? abs($quantity) : 0.0;
                }

                return 0.0;
            }

            if ($sourceLocationId > 0 && $destinationLocationId > 0 && $sourceLocationId === $destinationLocationId) {
                return 0.0;
            }

            if ($direction === 'incoming') {
                if ($destinationLocationId > 0 && $sourceLocationId <= 0) {
                    return abs($quantity);
                }

                if (str_contains($operationCode, 'entrada') || str_contains($operationCode, 'recepcion') || str_contains($operationCode, 'in')) {
                    return abs($quantity);
                }

                return 0.0;
            }

            if ($direction === 'outgoing') {
                if ($sourceLocationId > 0 && $destinationLocationId <= 0) {
                    return abs($quantity);
                }

                if (str_contains($operationCode, 'salida') || str_contains($operationCode, 'despacho') || str_contains($operationCode, 'out')) {
                    return abs($quantity);
                }

                return 0.0;
            }

            return 0.0;
        });
    }


    protected static function productOperationalPurchasedQuantity(Product $record): float
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('purchase_order_lines')) {
            return 0.0;
        }

        $quantityColumn = static::firstExistingOperationalColumn('purchase_order_lines', [
            'received_base_quantity',
            'base_quantity',
            'ordered_quantity',
            'received_quantity',
        ]);

        if (! $quantityColumn) {
            return 0.0;
        }

        $query = \Illuminate\Support\Facades\DB::table('purchase_order_lines');

        static::applyProductOperationalScope($query, $record, 'purchase_order_lines');

        if (
            (int) ($record->company_id ?? 0) > 0
            && \Illuminate\Support\Facades\Schema::hasColumn('purchase_order_lines', 'company_id')
        ) {
            $query->where('company_id', (int) $record->company_id);
        }

        return (float) $query->sum($quantityColumn);
    }

    protected static function productOperationalSoldQuantity(Product $record): float
    {
        foreach (['sales_lines', 'sale_order_lines', 'sale_lines', 'pos_order_lines'] as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $quantityColumn = static::firstExistingOperationalColumn($table, [
                'quantity',
                'qty',
                'ordered_quantity',
                'delivered_quantity',
                'base_quantity',
            ]);

            if (! $quantityColumn) {
                continue;
            }

            $query = \Illuminate\Support\Facades\DB::table($table);

            static::applyProductOperationalScope($query, $record, $table);

            if (
                (int) ($record->company_id ?? 0) > 0
                && \Illuminate\Support\Facades\Schema::hasColumn($table, 'company_id')
            ) {
                $query->where('company_id', (int) $record->company_id);
            }

            return (float) $query->sum($quantityColumn);
        }

        return 0.0;
    }

    protected static function productOperationalReorderingRulesCount(Product $record): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('stock_replenishment_rules')) {
            return 0;
        }

        $query = \Illuminate\Support\Facades\DB::table('stock_replenishment_rules');

        static::applyProductOperationalScope($query, $record, 'stock_replenishment_rules');

        if (
            (int) ($record->company_id ?? 0) > 0
            && \Illuminate\Support\Facades\Schema::hasColumn('stock_replenishment_rules', 'company_id')
        ) {
            $query->where('company_id', (int) $record->company_id);
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_replenishment_rules', 'is_active')) {
            $query->where('is_active', true);
        }

        return (int) $query->count();
    }

    protected static function productOperationalBomCount(Product $record): int
    {
        foreach (['bill_of_materials', 'manufacturing_boms', 'product_boms'] as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $query = \Illuminate\Support\Facades\DB::table($table);

            static::applyProductOperationalScope($query, $record, $table);

            if (
                (int) ($record->company_id ?? 0) > 0
                && \Illuminate\Support\Facades\Schema::hasColumn($table, 'company_id')
            ) {
                $query->where('company_id', (int) $record->company_id);
            }

            return (int) $query->count();
        }

        return 0;
    }

    protected static function applyProductOperationalScope($query, Product $record, string $table, ?string $alias = null): void
    {
        $prefix = $alias ? $alias . '.' : '';

        $recordId = (int) ($record->id ?? 0);
        $companyId = (int) ($record->company_id ?? 0);

        if ($recordId <= 0) {
            $query->whereRaw('1 = 0');

            return;
        }

        $hasProductId = \Illuminate\Support\Facades\Schema::hasColumn($table, 'product_id');
        $hasVariantId = \Illuminate\Support\Facades\Schema::hasColumn($table, 'product_variant_id');

        if ((bool) ($record->is_variant ?? false)) {
            $query->where(function ($inner) use ($prefix, $recordId, $hasVariantId, $hasProductId): void {
                if ($hasVariantId) {
                    $inner->where($prefix . 'product_variant_id', $recordId);
                }

                if ($hasProductId) {
                    $hasVariantId
                        ? $inner->orWhere($prefix . 'product_id', $recordId)
                        : $inner->where($prefix . 'product_id', $recordId);
                }
            });

            return;
        }

        $variantIds = [];

        if (
            \Illuminate\Support\Facades\Schema::hasTable('products')
            && \Illuminate\Support\Facades\Schema::hasColumn('products', 'parent_product_id')
        ) {
            $variantQuery = \Illuminate\Support\Facades\DB::table('products')
                ->where('parent_product_id', $recordId);

            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'company_id') && $companyId > 0) {
                $variantQuery->where('company_id', $companyId);
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'is_variant')) {
                $variantQuery->where('is_variant', true);
            }

            $variantIds = $variantQuery
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();
        }

        $query->where(function ($inner) use ($prefix, $recordId, $variantIds, $hasVariantId, $hasProductId): void {
            if ($hasProductId) {
                $inner->where($prefix . 'product_id', $recordId);
            }

            if ($hasVariantId && ! empty($variantIds)) {
                $hasProductId
                    ? $inner->orWhereIn($prefix . 'product_variant_id', $variantIds)
                    : $inner->whereIn($prefix . 'product_variant_id', $variantIds);
            }
        });
    }

    protected static function firstExistingOperationalColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    protected static function formatOperationalMetric(float $value): string
    {
        return number_format($value, 2);
    }

    protected static function sumIfTableExists(string $table, string $column, int $productId, string $sumColumn): string
    {
        if (
            ! Schema::hasTable($table) ||
            ! Schema::hasColumn($table, $column) ||
            ! Schema::hasColumn($table, $sumColumn)
        ) {
            return '0.00';
        }

        $value = DB::table($table)
            ->where($column, $productId)
            ->sum($sumColumn);

        return number_format((float) $value, 2);
    }

    protected static function countInventoryMovements(int $productId, string $direction): string
    {
        if (
            ! Schema::hasTable('inventory_movements') ||
            ! Schema::hasColumn('inventory_movements', 'product_id')
        ) {
            return '0';
        }

        $query = DB::table('inventory_movements')
            ->where('product_id', $productId);

        if (Schema::hasColumn('inventory_movements', 'direction')) {
            $query->where('direction', $direction);
        }

        if (Schema::hasColumn('inventory_movements', 'movement_type')) {
            if ($direction === 'in') {
                $query->whereIn('movement_type', ['in', 'receipt', 'incoming', 'purchase_receipt']);
            }

            if ($direction === 'out') {
                $query->whereIn('movement_type', ['out', 'delivery', 'outgoing', 'sale_delivery']);
            }
        }

        return (string) $query->count();
    }

    protected static function websiteStatus(Product $record): string
    {
        if (! Schema::hasTable('product_web_settings')) {
            return 'No publicado';
        }

        if (! Schema::hasColumn('product_web_settings', 'product_id')) {
            return 'No publicado';
        }

        $query = DB::table('product_web_settings')
            ->where('product_id', $record->id);

        if (Schema::hasColumn('product_web_settings', 'is_published')) {
            return $query->where('is_published', true)->exists()
                ? 'Publicado'
                : 'No publicado';
        }

        return $query->exists() ? 'Configurado' : 'No publicado';
    }


    protected static function defaultCategoryId(): ?int
    {
        $companyId = static::currentCompanyId();

        if (! $companyId) {
            return null;
        }

        return ProductCategory::query()
            ->where('company_id', $companyId)
            ->where('code', 'GENERAL')
            ->where('is_active', true)
            ->value('id');
    }

    protected static function defaultUnitId(): ?int
    {
        $companyId = static::currentCompanyId();

        if (! $companyId) {
            return null;
        }

        return InventoryUnit::query()
            ->where('company_id', $companyId)
            ->where('code', 'PZA')
            ->where('is_active', true)
            ->value('id');
    }


    protected static function attributeOptions(): array
    {
        $companyId = static::currentCompanyId();

        if (! $companyId) {
            return [];
        }

        return ProductAttribute::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ProductAttribute $attribute) => [
                $attribute->id => "{$attribute->code} - {$attribute->name}",
            ])
            ->all();
    }

    protected static function attributeValueOptions(?int $attributeId): array
    {
        if (! $attributeId) {
            return [];
        }

        return ProductAttributeValue::query()
            ->where('product_attribute_id', $attributeId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ProductAttributeValue $value) => [
                $value->id => "{$value->code} - {$value->name}",
            ])
            ->all();
    }


    protected static function satUnitOptions(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('sat_unit_codes')) {
            $options = [];
        }

        return SatUnitCode::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (SatUnitCode $unit) => [
                $unit->code => trim($unit->code . ' - ' . $unit->name . ($unit->symbol ? ' (' . $unit->symbol . ')' : '')),
            ])
            ->all();
            return static::sortFavoriteUnitOptions($options);
    }



    protected static function taxRateOptions(?string $usageType = null): array
    {
        if (! class_exists(\App\Models\TaxRate::class)) {
            return [];
        }

        $companyId = static::currentCompanyId();

        return \App\Models\TaxRate::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->mapWithKeys(function (\App\Models\TaxRate $tax): array {
                $rate = rtrim(rtrim(number_format((float) $tax->rate * 100, 4, '.', ''), '0'), '.');

                $suffix = $tax->factor_type === 'exento'
                    ? 'Exento'
                    : $rate . '%';

                $type = strtoupper((string) $tax->tax_type);
                $kind = $tax->is_withholding ? 'Retención' : 'Traslado';

                return [
                    $tax->id => trim("{$tax->code} - {$tax->name} ({$type} {$suffix}, {$kind})"),
                ];
            })
            ->all();
    }



    protected static function satProductSearchOptions(?string $search = null, int $limit = 80): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('sat_product_service_codes')) {
            return [];
        }

        $search = trim((string) $search);

        $query = \Illuminate\Support\Facades\DB::table('sat_product_service_codes')
            ->select(['code', 'description']);

        if (\Illuminate\Support\Facades\Schema::hasColumn('sat_product_service_codes', 'is_active')) {
            $query->where('is_active', true);
        }

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $prefix = $search . '%';

            $query->where(function ($query) use ($search, $like, $prefix): void {
                $query
                    ->where('code', $search)
                    ->orWhere('code', 'like', $prefix)
                    ->orWhere('description', 'ilike', $like);

                if (\Illuminate\Support\Facades\Schema::hasColumn('sat_product_service_codes', 'similar_words')) {
                    $query->orWhere('similar_words', 'ilike', $like);
                }
            });

            $query->orderByRaw(
                "CASE WHEN code = ? THEN 0 WHEN code LIKE ? THEN 1 ELSE 2 END",
                [$search, $prefix]
            );
        }

        return $query
            ->orderBy('code')
            ->limit($limit)
            ->get()
            ->mapWithKeys(function ($row): array {
                $description = \Illuminate\Support\Str::limit((string) $row->description, 140);

                return [
                    (string) $row->code => trim((string) $row->code . ' - ' . $description),
                ];
            })
            ->all();
    }

    protected static function satProductOptionLabel($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || ! \Illuminate\Support\Facades\Schema::hasTable('sat_product_service_codes')) {
            return null;
        }

        $row = \Illuminate\Support\Facades\DB::table('sat_product_service_codes')
            ->select(['code', 'description'])
            ->where('code', $value)
            ->first();

        if (! $row) {
            return $value;
        }

        return trim((string) $row->code . ' - ' . (string) $row->description);
    }

    protected static function satUnitSearchOptions(?string $search = null, int $limit = 80): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('sat_unit_codes')) {
            $options = [];
        }

        $search = trim((string) $search);

        $query = \Illuminate\Support\Facades\DB::table('sat_unit_codes')
            ->select(['code', 'name', 'symbol']);

        if (\Illuminate\Support\Facades\Schema::hasColumn('sat_unit_codes', 'is_active')) {
            $query->where('is_active', true);
        }

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $prefix = $search . '%';

            $query->where(function ($query) use ($search, $like, $prefix): void {
                $query
                    ->where('code', $search)
                    ->orWhere('code', 'like', $prefix)
                    ->orWhere('name', 'ilike', $like);

                if (\Illuminate\Support\Facades\Schema::hasColumn('sat_unit_codes', 'description')) {
                    $query->orWhere('description', 'ilike', $like);
                }

                if (\Illuminate\Support\Facades\Schema::hasColumn('sat_unit_codes', 'symbol')) {
                    $query->orWhere('symbol', 'ilike', $like);
                }
            });

            $query->orderByRaw(
                "CASE WHEN code = ? THEN 0 WHEN code LIKE ? THEN 1 ELSE 2 END",
                [$search, $prefix]
            );
        }

        return $query
            ->orderBy('code')
            ->limit($limit)
            ->get()
            ->mapWithKeys(function ($row): array {
                $symbol = trim((string) ($row->symbol ?? ''));
                $label = trim((string) $row->code . ' - ' . (string) $row->name);

                if ($symbol !== '') {
                    $label .= ' (' . $symbol . ')';
                }

                return [
                    (string) $row->code => $label,
                ];
            })
            ->all();
            return static::sortFavoriteUnitOptions($options);
    }

    protected static function satUnitOptionLabel($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || ! \Illuminate\Support\Facades\Schema::hasTable('sat_unit_codes')) {
            return null;
        }

        $row = \Illuminate\Support\Facades\DB::table('sat_unit_codes')
            ->select(['code', 'name', 'symbol'])
            ->where('code', $value)
            ->first();

        if (! $row) {
            return $value;
        }

        $symbol = trim((string) ($row->symbol ?? ''));
        $label = trim((string) $row->code . ' - ' . (string) $row->name);

        if ($symbol !== '') {
            $label .= ' (' . $symbol . ')';
        }

        return $label;
    }



    protected static function satTaxObjectOptions(?string $search = null, int $limit = 50): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('sat_billing_catalog_items')) {
            return [
                '01' => '01 - No objeto de impuesto',
                '02' => '02 - Sí objeto de impuesto',
                '03' => '03 - Sí objeto de impuesto y no obligado al desglose',
            ];
        }

        $search = trim((string) $search);

        $query = \Illuminate\Support\Facades\DB::table('sat_billing_catalog_items')
            ->where('catalog_key', 'objeto_imp')
            ->where('is_active', true)
            ->select(['code', 'name', 'description']);

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $prefix = $search . '%';

            $query->where(function ($query) use ($search, $like, $prefix): void {
                $query
                    ->where('code', $search)
                    ->orWhere('code', 'like', $prefix)
                    ->orWhere('name', 'ilike', $like)
                    ->orWhere('description', 'ilike', $like);
            });

            $query->orderByRaw(
                "CASE WHEN code = ? THEN 0 WHEN code LIKE ? THEN 1 ELSE 2 END",
                [$search, $prefix]
            );
        }

        return $query
            ->orderBy('code')
            ->limit($limit)
            ->get()
            ->mapWithKeys(function ($row): array {
                $label = trim((string) $row->code . ' - ' . (string) ($row->name ?: $row->description));

                return [
                    (string) $row->code => $label,
                ];
            })
            ->all();
    }

    protected static function satTaxObjectLabel($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('sat_billing_catalog_items')) {
            return $value;
        }

        $row = \Illuminate\Support\Facades\DB::table('sat_billing_catalog_items')
            ->where('catalog_key', 'objeto_imp')
            ->where('code', $value)
            ->first(['code', 'name', 'description']);

        if (! $row) {
            return $value;
        }

        return trim((string) $row->code . ' - ' . (string) ($row->name ?: $row->description));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.productresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('inventory.menu.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('inventory.menu.view')
            );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn () => static::currentCompanyId())
                    ->required(),

                Forms\Components\Section::make('Resumen operativo')
                    ->description('Indicadores relacionados al producto. Algunos aparecerán en cero hasta activar ventas, compras, inventario, fabricación o e-commerce.')
                    ->schema([
                        Forms\Components\Placeholder::make('metric_price_lists')
                            ->label('Precios adicionales')
                            ->content(fn (?Product $record): string => static::productMetric($record, 'price_lists'))
                            ->columnSpan(2),

                        Forms\Components\Placeholder::make('metric_website')
                            ->label('Sitio web')
                            ->content(fn (?Product $record): string => static::productMetric($record, 'website'))
                            ->columnSpan(2),

                        Forms\Components\Placeholder::make('metric_on_hand')
                            ->label('A la mano')
                            ->content(fn (?Product $record): string => static::productMetric($record, 'on_hand'))
                            ->columnSpan(2),

                        Forms\Components\Placeholder::make('metric_forecasted')
                            ->label('Pronosticado')
                            ->content(fn (?Product $record): string => static::productMetric($record, 'forecasted'))
                            ->columnSpan(2),

                        Forms\Components\Placeholder::make('metric_incoming')
                            ->label('Entradas')
                            ->content(fn (?Product $record): string => static::productMetric($record, 'incoming'))
                            ->columnSpan(2),

                        Forms\Components\Placeholder::make('metric_outgoing')
                            ->label('Salidas')
                            ->content(fn (?Product $record): string => static::productMetric($record, 'outgoing'))
                            ->columnSpan(2),

                        Forms\Components\Placeholder::make('metric_reordering_rules')
                            ->label('Reglas de reabastecimiento')
                            ->content(fn (?Product $record): string => static::productMetric($record, 'reordering_rules'))
                            ->columnSpan(3),

                        Forms\Components\Placeholder::make('metric_bom')
                            ->label('Lista de materiales')
                            ->content(fn (?Product $record): string => static::productMetric($record, 'bom'))
                            ->columnSpan(3),

                        Forms\Components\Placeholder::make('metric_sold')
                            ->label('Vendido')
                            ->content(fn (?Product $record): string => static::productMetric($record, 'sold'))
                            ->columnSpan(3),

                        Forms\Components\Placeholder::make('metric_purchased')
                            ->label('Comprado')
                            ->content(fn (?Product $record): string => static::productMetric($record, 'purchased'))
                            ->columnSpan(3),
                    ])
                    ->columns(12)
                    ->collapsible()
                    ->columnSpanFull(),

                Forms\Components\Tabs::make('Producto')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Información general')
                            ->schema([

                                Forms\Components\Section::make('Precios y costos')
                                    ->description('Los importes base se capturan sin IVA. Los importes con impuesto son calculados y no se editan manualmente.')
                                    ->schema([
                                        Forms\Components\TextInput::make('sale_price')
                                            ->label('Precio de venta sin IVA')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step('0.000001')
                                            ->prefix('$')
                                            ->live(onBlur: true)
                                            ->helperText('Precio base de venta antes de impuestos.')
    ->disabled()
    ->dehydrated(false),
                                        Forms\Components\TextInput::make('sale_tax_rate')
                                            ->label('IVA venta %')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(16)
                                            ->step('0.0001')
                                            ->live(onBlur: true),

                                        Forms\Components\Placeholder::make('sale_price_with_tax_preview')
                                            ->label('Precio de venta con IVA')
                                            ->content(fn (Forms\Get $get): string => static::salePriceWithTaxLabel($get))
                                            ->helperText('Calculado automáticamente. No editable.'),

                                        Forms\Components\TextInput::make('average_cost_without_tax')
                                            ->label('Costo promedio actual sin IVA')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step('0.000001')
                                            ->prefix('$')
                                            ->live(onBlur: true)
                                            ->helperText('Editable para carga inicial o correcciones controladas. Después podrá actualizarse desde compras e inventario.')
    ->disabled()
    ->dehydrated(false),
                                        Forms\Components\TextInput::make('purchase_tax_rate')
                                            ->label('IVA compra %')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(16)
                                            ->step('0.0001')
                                            ->live(onBlur: true),

                                        Forms\Components\Placeholder::make('purchase_cost_with_tax_preview')
                                            ->label('Costo promedio con IVA')
                                            ->content(fn (Forms\Get $get): string => static::purchaseCostWithTaxLabel($get))
                                            ->helperText('Calculado automáticamente. No editable.'),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),




Forms\Components\Actions::make([
    Forms\Components\Actions\Action::make('change_sale_price_with_reason')
        ->label('Cambio de precio')
        ->icon('heroicon-o-currency-dollar')
        ->color('primary')
        ->visible(fn (?\App\Models\Product $record): bool => $record !== null && (bool) $record->getKey())
        ->modalHeading('Cambio de precio de venta')
        ->modalDescription('Captura el nuevo precio de venta sin IVA y el motivo del cambio.')
        ->modalSubmitActionLabel('Guardar cambio de precio')
        ->modalSubmitAction(fn ($action) => $action->color('primary'))
        ->form([
            Forms\Components\Placeholder::make('current_sale_price')
                ->label('Precio actual sin IVA')
                ->content(fn (?\App\Models\Product $record): string => $record ? '$ ' . number_format((float) ($record->sale_price ?? 0), 4) : '—'),

            Forms\Components\TextInput::make('new_value')
                ->label('Nuevo precio de venta sin IVA')
                ->numeric()
                ->minValue(0)
                ->step('0.000001')
                ->prefix('$')
                ->required(),

            Forms\Components\Textarea::make('reason')
                ->label('Motivo')
                ->rows(3)
                ->minLength(5)
                ->required()
                ->helperText('Ejemplo: ajuste por lista de precios nueva, promoción terminada, corrección autorizada.'),
        ])
        ->action(function (array $data, ?\App\Models\Product $record, $livewire): void {
            if (! $record) {
                return;
            }

            static::applyManualProductPriceCostChange(
                $record,
                'sale_price',
                'Precio de venta sin IVA',
                (float) $data['new_value'],
                (string) $data['reason'],
            );

            \Filament\Notifications\Notification::make()
                ->title('Cambio de precio guardado')
                ->success()
                ->send();

            $livewire->redirect(self::getUrl('edit', ['record' => $record]));
        }),

    Forms\Components\Actions\Action::make('change_average_cost_with_reason')
        ->label('Cambio de costo')
        ->icon('heroicon-o-calculator')
        ->color('warning')
        ->visible(fn (?\App\Models\Product $record): bool => $record !== null && (bool) $record->getKey())
        ->modalHeading('Cambio de costo promedio')
        ->modalDescription('Captura el nuevo costo promedio sin IVA y el motivo del cambio manual.')
        ->modalSubmitActionLabel('Guardar cambio de costo')
        ->modalSubmitAction(fn ($action) => $action->color('primary'))
        ->form([
            Forms\Components\Placeholder::make('current_average_cost')
                ->label('Costo actual sin IVA')
                ->content(fn (?\App\Models\Product $record): string => $record ? '$ ' . number_format((float) ($record->average_cost_without_tax ?? 0), 4) : '—'),

            Forms\Components\TextInput::make('new_value')
                ->label('Nuevo costo promedio sin IVA')
                ->numeric()
                ->minValue(0)
                ->step('0.000001')
                ->prefix('$')
                ->required(),

            Forms\Components\Textarea::make('reason')
                ->label('Motivo')
                ->rows(3)
                ->minLength(5)
                ->required()
                ->helperText('Ejemplo: corrección por costo real, carga inicial, ajuste autorizado.'),
        ])
        ->action(function (array $data, ?\App\Models\Product $record, $livewire): void {
            if (! $record) {
                return;
            }

            static::applyManualProductPriceCostChange(
                $record,
                'average_cost_without_tax',
                'Costo promedio actual sin IVA',
                (float) $data['new_value'],
                (string) $data['reason'],
            );

            \Filament\Notifications\Notification::make()
                ->title('Cambio de costo guardado')
                ->success()
                ->send();

            $livewire->redirect(self::getUrl('edit', ['record' => $record]));
        }),
])
    ->columnSpanFull(),

Forms\Components\Section::make('Auditoría de precios y costos')
    ->description('Consulta quién cambió precio/costo, cuándo y qué valor tenía antes.')
    ->visible(fn (?\App\Models\Product $record): bool => $record !== null && (bool) $record->getKey())
    ->schema([
        Forms\Components\Placeholder::make('price_cost_last_change')
            ->label('Último cambio')
            ->content(fn (?\App\Models\Product $record): \Illuminate\Support\HtmlString => static::priceCostLastChangeHtml($record)),

        Forms\Components\Placeholder::make('price_cost_audit_history')
            ->label('Historial reciente')
            ->content(fn (?\App\Models\Product $record): \Illuminate\Support\HtmlString => static::priceCostAuditHistoryHtml($record))
            ->columnSpanFull(),
    ])
    ->columns(1)
    ->columnSpanFull(),

                                Forms\Components\Section::make('Datos principales')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->default(function (): ?string {
                                                $parentId = (int) request()->query('parent_product_id');
                                                if ($parentId <= 0) {
                                                    return null;
                                                }
                                                return \App\Models\Product::query()->whereKey($parentId)->value('name');
                                            })
                                            ->helperText(fn (): ?string => request()->filled('parent_product_id') ? 'Nombre final: producto padre + valor de variante.' : null)
                                            ->label('Nombre del producto')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(8),

                                        Forms\Components\TextInput::make('internal_reference')
                                            ->label('Referencia interna')
                                            ->maxLength(80)
                                            ->live(onBlur: true)
                                            ->dehydrateStateUsing(fn ($state): ?string => filled($state) ? trim((string) $state) : null)
                                            ->afterStateUpdated(function (mixed $state, \Filament\Forms\Set $set, \Filament\Forms\Get $get, ?\App\Models\Product $record, \Livewire\Component $livewire): void {
                                                $reference = trim((string) ($state ?? ''));

                                                if ($reference === '') {
                                                    return;
                                                }

                                                $companyId = (int) (
                                                    ($record?->company_id ?? null)
                                                    ?: ($get('company_id') ?: 0)
                                                    ?: (\Filament\Facades\Filament::getTenant()?->getKey() ?: 0)
                                                );

                                                $query = \App\Models\Product::query()
                                                    ->whereRaw('LOWER(TRIM(internal_reference)) = ?', [mb_strtolower($reference, 'UTF-8')]);

                                                if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'company_id')) {
                                                    if ($companyId > 0) {
                                                        $query->where('company_id', $companyId);
                                                    } else {
                                                        $query->whereNull('company_id');
                                                    }
                                                }

                                                if ($record?->getKey()) {
                                                    $query->whereKeyNot($record->getKey());
                                                }

                                                if (! $query->exists()) {
                                                    return;
                                                }

                                                $previousReference = $record?->getOriginal('internal_reference')
                                                    ?: $record?->internal_reference
                                                    ?: null;

                                                $previousReference = filled($previousReference)
                                                    ? trim((string) $previousReference)
                                                    : null;

                                                $set('internal_reference', $previousReference);

                                                $message = $previousReference
                                                    ? 'La referencia interna ya existe en otro producto de esta empresa. Se regresó al valor anterior: ' . $previousReference . '.'
                                                    : 'La referencia interna ya existe en otro producto de esta empresa. Se limpió el campo.';

                                                // BEXIA_V5550F_INTERNAL_REFERENCE_DUPLICATE_MODAL_DISPATCH_FIELD
                                                $livewire->dispatch(
                                                    'bexia-internal-reference-duplicate-modal',
                                                    title: 'Referencia interna duplicada',
                                                    message: $message,
                                                );
                                            })
                                            ->helperText('Debe ser única por empresa. Es diferente del SKU / código de barras.')
                                            // BEXIA_V5550E_INTERNAL_REFERENCE_NOTICE_NO_INLINE_RULE
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('sku')
                                            ->label('SKU / Código de barras')
                                            ->maxLength(120)
                                            ->columnSpan(4),

                                        Forms\Components\FileUpload::make('image_path')
                                            ->label('Imagen principal')
                                            ->disk('public')
                                            ->directory('products')
                                            ->visibility('public')
                                            ->image()
                                            ->imageEditor()
                                            ->downloadable()
                                            ->openable()
                                            ->previewable(true)
                                            ->helperText('Sube la imagen principal del producto. Formatos recomendados: JPG o PNG.')
                                            ->columnSpan(3),

                                        Forms\Components\Select::make('product_type')
                                            ->label('Tipo de producto')
                                            ->options([
                                                'stockable' => 'Producto almacenable',
                                                'consumable' => 'Consumible',
                                                'service' => 'Servicio',
                                            ])
                                            ->default('stockable')
                                            ->required()
                                            ->native(false)
                                            ->columnSpan(4),

                                        Forms\Components\Select::make('product_category_id')
                                            ->label('Categoría')
                                            ->options(fn (): array => self::productCategoryTreeOptions())
                                            ->searchable()
                                            ->preload()
                                            ->getSearchResultsUsing(fn (string $search): array => self::productCategoryTreeOptions($search))
                                            ->getOptionLabelUsing(fn ($value): ?string => self::productCategoryShortLabel($value))
                                            ->default(fn (): ?int => self::defaultProductCategoryId())
                                            ->placeholder('Buscar categoría por código, nombre o ruta...')
                                            ->helperText('Busca por código, nombre o ruta completa del árbol de categorías.')
                                            ->native(false)
                                            ->columnSpan(6),
                                        Forms\Components\Select::make('inventory_unit_id')
                                            ->label('Unidad interna de inventario')
                                            ->options(fn () => static::unitOptions())
                                            ->default(fn () => static::defaultUnitId())
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->placeholder('Selecciona unidad interna')
                                            ->helperText('Unidad usada para inventario interno. La unidad SAT para timbrado se selecciona en SAT / Facturación.')
                                            ->columnSpan(6),

                                        Forms\Components\Toggle::make('can_be_sold')
                                            ->label('Se puede vender')
                                            ->default(true)
                                            ->columnSpan(4),

                                        Forms\Components\Toggle::make('can_be_purchased')
                                            ->label('Se puede comprar')
                                            ->default(true)
                                            ->columnSpan(4),

                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Activo')
                                            ->default(true)
                                            ->columnSpan(4),

                                        Forms\Components\Textarea::make('description')
                                            ->label('Descripción interna')
                                            ->rows(3)
                                            ->columnSpan(12),
                                    ])
                                    ->columns(12),

Forms\Components\Section::make('Atributos de catálogo')
                                    ->description('Selecciona valores desde el catálogo de atributos. Marca, modelo, material, color, talla, línea, condición y catálogo deben capturarse aquí, no como texto libre.')
                                    
                                // hide_attributes_on_variant_create_v5
                                ->visible(fn (): bool => ! request()->filled('parent_product_id'))

                                // hide_attributes_catalog_on_variant_v6
                                ->visible(fn (?\Illuminate\Database\Eloquent\Model $record): bool => ! request()->filled('parent_product_id') && ! (bool) ($record?->is_variant ?? false))
->schema([
                                        Forms\Components\Repeater::make('attributeAssignments')
                                            ->label('Atributos del producto')
                                            ->relationship('attributeAssignments')
                                            ->schema([
                                                Forms\Components\Hidden::make('company_id')
                                                    ->default(fn () => static::currentCompanyId())
                                                    ->required(),

                                                Forms\Components\Select::make('product_attribute_id')
                                                    ->label('Atributo')
                                                    ->options(fn () => static::attributeOptions())
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->native(false)
                                                    ->required()
                                                    ->afterStateUpdated(fn (Forms\Set $set) => $set('product_attribute_value_id', null))
                                                    ->columnSpan(6),

                                                Forms\Components\Select::make('product_attribute_value_id')
                                                    ->label('Valor de catálogo')
                                                    ->options(fn (Forms\Get $get): array => static::attributeValueOptions($get('product_attribute_id') ? (int) $get('product_attribute_id') : null))
                                                    ->searchable()
                                                    ->preload()
                                                    ->native(false)
                                                    ->required()
                                                    ->placeholder('Selecciona un valor')
                                                    ->helperText('Si no aparece el valor, primero agrégalo en Inventario > Atributos de producto > Valores.')
                                                    ->columnSpan(6),
                                            ])
                                            ->columns(12)
                                            ->defaultItems(0)
                                            ->addActionLabel('Agregar atributo')
                                            ->reorderable(false)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(12),

                            ]),

                        Forms\Components\Tabs\Tab::make('Imágenes')
                            ->schema([
                                Forms\Components\Section::make('Galería de imágenes')
                                    ->description('Puedes agregar varias imágenes. Para variantes, captura imágenes específicas en la variante. Si una variante no tiene imagen, después podrá heredar la imagen del producto base.')
                                    ->schema([
                                        Forms\Components\Repeater::make('images')
                                            ->label('Imágenes del producto')
                                            ->relationship('images')
                                            ->schema([
                                                Forms\Components\Hidden::make('company_id')
                                                    ->default(fn () => static::currentCompanyId())
                                                    ->required(),

                                                Forms\Components\FileUpload::make('image_path')
                                                    ->label('Imagen')
                                                    ->disk('public')
                                                    ->directory('products/gallery')
                                                    ->visibility('public')
                                                    ->image()
                                                    ->imageEditor()
                                                    ->downloadable()
                                                    ->openable()
                                                    ->previewable(true)
                                                    ->required()
                                                    ->columnSpan(5),

                                                Forms\Components\TextInput::make('title')
                                                    ->label('Título')
                                                    ->maxLength(255)
                                                    ->columnSpan(3),

                                                Forms\Components\TextInput::make('alt_text')
                                                    ->label('Texto alternativo')
                                                    ->maxLength(255)
                                                    ->columnSpan(4),

                                                Forms\Components\Toggle::make('is_primary')
                                                    ->label('Imagen principal de galería')
                                                    ->helperText('Marca solo una como principal.')
                                                    ->default(false)
                                                    ->columnSpan(3),

                                                Forms\Components\TextInput::make('sort_order')
                                                    ->label('Orden')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->columnSpan(3),

                                                Forms\Components\Toggle::make('is_active')
                                                    ->label('Activa')
                                                    ->default(true)
                                                    ->columnSpan(3),
                                            ])
                                            ->columns(12)
                                            ->defaultItems(0)
                                            ->addActionLabel('Agregar imagen')
                                            ->reorderable(false)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(12),
                            ]),

                        Forms\Components\Tabs\Tab::make('Ventas / POS')
                            ->schema([
                                Forms\Components\Section::make('Ventas')
                                    ->schema([
                                        Forms\Components\Select::make('invoice_policy')
                                            ->label('Política de facturación')
                                            ->options([
                                                'ordered_quantities' => 'Cantidades ordenadas',
                                                'delivered_quantities' => 'Cantidades entregadas',
                                            ])
                                            ->default('delivered_quantities')
                                            ->native(false)
                                            ->columnSpan(4),

                                        Forms\Components\Toggle::make('allow_out_of_stock_sales')
                                            ->label('Permitir venta sin existencia')
                                            ->default(false)
                                            ->columnSpan(3),

                                        Forms\Components\Textarea::make('sale_description')
                                            ->label('Descripción de ventas')
                                            ->rows(4)
                                            ->columnSpan(12),
                                    ])
                                    ->columns(12),

                                Forms\Components\Section::make('Punto de venta')
                                    ->schema([
                                        Forms\Components\Toggle::make('available_in_pos')
                                            ->label('Disponible en PdV')
                                            ->default(true)
                                            ->columnSpan(3),

                                        Forms\Components\Toggle::make('is_pos_favorite')
                                            ->label('Favorito en PDV')
                                            ->helperText('Aparece en el filtro Favoritos del punto de venta.')
                                            ->default(false)
                                            ->columnSpan(3),

                                        Forms\Components\Toggle::make('weigh_with_scale')
                                            ->label('Pesar con báscula')
                                            ->default(false)
                                            ->columnSpan(3),

                                        Forms\Components\Toggle::make('include_in_global_invoice')
                                            ->label('Incluir en factura global')
                                            ->default(true)
                                            ->columnSpan(3),
                                    ])
                                    ->columns(12),
                            ]),

                        Forms\Components\Tabs\Tab::make('Compra')
                            ->schema([

                                    Forms\Components\Section::make('Equivalencias de compra')
                                        ->description('Configura las unidades de compra y su equivalencia contra la unidad base del producto.')
                                        ->schema([
                                            Forms\Components\Repeater::make('purchaseUnits')
                                                ->label('')
                                                ->relationship('purchaseUnits')
                                                ->schema([
                                                    Forms\Components\Select::make('sat_unit_key')
                                                        ->label('Unidad SAT')
                                                        ->searchable()
                                                        ->preload()
                                                        ->options(fn (): array => static::purchaseEquivalenceSatUnitOptions())
                                                        ->getSearchResultsUsing(fn (string $search): array => static::purchaseEquivalenceSatUnitOptions($search))
                                                        ->getOptionLabelUsing(fn ($value): ?string => $value ? static::purchaseEquivalenceSatUnitLabel($value) : null)
                                                        ->live()
                                                        ->afterStateHydrated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                                            if (! $state) {
                                                                return;
                                                            }

                                                            $unit = static::purchaseEquivalenceSatUnitRow($state);

                                                            if (! $unit) {
                                                                return;
                                                            }

                                                            $set('sat_unit_name', $unit['name']);

                                                            if (trim((string) ($get('name') ?? '')) === '') {
                                                                $set('name', $unit['name']);
                                                            }
                                                        })
                                                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                                            $unit = static::purchaseEquivalenceSatUnitRow($state);

                                                            if (! $unit) {
                                                                return;
                                                            }

                                                            $set('sat_unit_name', $unit['name']);

                                                            if (trim((string) ($get('name') ?? '')) === '') {
                                                                $set('name', $unit['name']);
                                                            }
                                                        })
                                                        ->required()
                                                        ->columnSpan(3),

                                                    Forms\Components\TextInput::make('name')
                                                        ->label('Nombre interno')
                                                        ->placeholder('Caja, media caja, docena, paquete...')
                                                        ->required()
                                                        ->maxLength(80)
                                                        ->helperText('Nombre que verá el usuario en compras.')
                                                        ->columnSpan(3),

                                                    Forms\Components\Hidden::make('sat_unit_name')
                                                        ->dehydrated(true),

                                                    Forms\Components\TextInput::make('factor')
                                                        ->label('Factor a unidad base')
                                                        ->numeric()
                                                        ->step('0.000001')
                                                        ->minValue(0.000001)
                                                        ->required()
                                                        ->helperText('Ejemplo: caja de 20 piezas = 20')
                                                        ->columnSpan(2),

                                                    Forms\Components\Toggle::make('is_default')
                                                        ->label('Predeterminada')
                                                        ->inline(false)
                                                        ->columnSpan(2),

                                                    Forms\Components\Toggle::make('is_active')
                                                        ->label('Activa')
                                                        ->default(true)
                                                        ->inline(false)
                                                        ->columnSpan(2),

                                                    Forms\Components\TextInput::make('notes')
                                                        ->label('Notas')
                                                        ->maxLength(255)
                                                        ->columnSpanFull(),
                                                ])
                                                ->columns(12)
                                                ->defaultItems(0)
                                                ->addActionLabel('Agregar equivalencia')
                                                ->reorderable(false)
                                                ->collapsible()
                                                ->itemLabel(function (array $state): ?string {
                                                    $name = $state['name'] ?? 'Unidad';
                                                    $sat = $state['sat_unit_key'] ?? null;
                                                    $factor = $state['factor'] ?? null;

                                                    $label = $sat ? $name . ' [' . $sat . ']' : $name;

                                                    return $factor
                                                        ? $label . ' = ' . rtrim(rtrim(number_format((float) $factor, 4, '.', ''), '0'), '.') . ' base'
                                                        : $label;
                                                }),
                                        ])
                                        ->collapsible()
                                        ->columnSpanFull(),




                                Forms\Components\Section::make('Compra')
                                    ->schema([
                                        Forms\Components\TextInput::make('purchase_price')
                                            ->label('Precio de compra')
                                            ->numeric()
                                            ->default(0)
                                            ->prefix('$')
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('purchase_lead_time_days')
                                            ->label('Plazo de compra / entrega')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('días')
                                            ->columnSpan(3),



                                        Forms\Components\TextInput::make('purchase_pack_units')
                                            ->label('UXES / unidades por empaque')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(1)
                                            ->step('0.0001')
                                            ->helperText('Cantidad de unidades que contiene un empaque de compra. Ej. caja de 100 piezas.'),




                                        Forms\Components\TextInput::make('purchase_min_quantity')
                                            ->label('Compra mínima')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step('0.0001')
                                            ->helperText('Cantidad mínima que normalmente se debe comprar al proveedor.'),

                                        Forms\Components\TextInput::make('purchase_multiple_quantity')
                                            ->label('Múltiplo de compra')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(1)
                                            ->step('0.0001')
                                            ->helperText('El reporte de reabastecimiento redondeará la sugerencia a este múltiplo.'),

                                        Forms\Components\Textarea::make('purchase_description')
                                            ->label('Descripción de compra')
                                            ->rows(4)
                                            ->columnSpan(12),
                                    ])
                                    ->columns(12),

                                Forms\Components\Section::make('Última compra')
                                    ->schema([

                                        Forms\Components\Placeholder::make('last_purchases_preview')
                                            ->label('Últimas 5 compras')
                                            ->content(fn (?\App\Models\Product $record): HtmlString => static::lastPurchasesPreview($record))
                                            ->columnSpanFull(),

                                        Forms\Components\TextInput::make('last_purchase_cost')
                                            ->label('Último costo de compra')
                                            ->numeric()
                                            ->prefix('$')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('last_supplier_name')
                                            ->label('Último proveedor')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(5),

                                        Forms\Components\DateTimePicker::make('last_purchase_at')
                                            ->label('Fecha última compra')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(4),
                                    ])
                                    ->columns(12),
                            ]),

                        Forms\Components\Tabs\Tab::make('Inventario')
                            ->schema([
                                Forms\Components\Section::make('Inventario')
                                    ->schema([
                                        Forms\Components\Select::make('tracking')
                                            ->label('Seguimiento')
                                            ->options([
                                                'none' => 'Sin seguimiento',
                                                'lot' => 'Por lotes',
                                                'serial' => 'Por número de serie único',
                                            ])
                                            ->default('none')
                                            ->native(false)
                                            ->columnSpan(4),

                                        Forms\Components\Section::make('Trazabilidad avanzada / Importación')
                                            ->description('Configura si este producto debe pedir VIN/serie, motor y datos de importación durante la recepción de compra.')
                                            ->schema([
                                                Forms\Components\Grid::make([
                                                    'default' => 1,
                                                    'md' => 2,
                                                    'xl' => 3,
                                                ])
                                                    ->schema([
                                                        Forms\Components\Select::make('advanced_tracking_mode')
                                                            ->label('Nivel de control')
                                                            ->options([
                                                                'none' => 'No aplica',
                                                                'warning' => 'Recomendada con aviso',
                                                                'required' => 'Obligatoria',
                                                            ])
                                                            ->default('none')
                                                            ->helperText('Obligatoria bloquea la recepción si faltan datos. Recomendada solo muestra aviso.'),

                                                        Forms\Components\Placeholder::make('advanced_tracking_help')
                                                            ->label('Uso recomendado')
                                                            ->content('Úsalo para productos importados o con identificación individual, por ejemplo motocicletas con VIN, motor y pedimento.')
                                                            ->columnSpan([
                                                                'default' => 1,
                                                                'md' => 1,
                                                                'xl' => 2,
                                                            ]),
                                                    ]),

                                                Forms\Components\CheckboxList::make('advanced_tracking_fields')
                                                    ->label('Campos solicitados')
                                                    ->options([
                                                        'serial_number' => 'VIN / número de serie',
                                                        'motor_number' => 'Número de motor',
                                                        'customs_entry_number' => 'Número de pedimento',
                                                        'customs_entry_date' => 'Fecha de pedimento',
                                                        'customs_office' => 'Aduana',
                                                        'imported_model' => 'Modelo importado',
                                                        'imported_color' => 'Color importado',
                                                    ])
                                                    ->columns([
                                                        'default' => 1,
                                                        'md' => 2,
                                                        'xl' => 3,
                                                    ])
                                                    ->gridDirection('row')
                                                    ->helperText('Selecciona los datos que se pedirán durante la recepción de compra.')
                                                    ->columnSpanFull(),

                                                Forms\Components\Textarea::make('advanced_tracking_notes')
                                                    ->label('Notas de trazabilidad')
                                                    ->rows(2)
                                                    ->columnSpanFull()
                                                    ->helperText('Uso interno para indicar reglas o instrucciones especiales de captura.'),
                                            ])
                                            ->collapsible()
                                            ->collapsed(fn (?Product $record): bool => (string) ($record?->advanced_tracking_mode ?? 'none') === 'none')
                                            ->columnSpanFull(),


                                        Forms\Components\Select::make('costing_method')
                                            ->label('Método de costeo')
                                            ->options([
                                                'inherit' => 'Heredar',
                                                'average' => 'Promedio',
                                                'fifo' => 'FIFO',
                                                'standard' => 'Costo estándar',
                                            ])
                                            ->default('inherit')
                                            ->native(false)
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('standard_cost')
                                            ->label('Costo')
                                            ->numeric()
                                            ->default(0)
                                            ->prefix('$')
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('weight')
                                            ->label('Peso')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('kg')
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('volume')
                                            ->label('Volumen')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('m³')
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('customer_lead_time_days')
                                            ->label('Plazo entrega cliente')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('días')
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('manufacturing_lead_time_days')
                                            ->label('Plazo fabricación')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('días')
                                            ->columnSpan(3),

                                        Forms\Components\Select::make('responsible_user_id')
                                            ->label('Responsable')
                                            ->options(fn () => static::responsibleOptions())
                                            ->searchable()
                                            ->native(false)
                                            ->columnSpan(6),
                                    ])
                                    ->columns(12),
                            ]),

                        Forms\Components\Tabs\Tab::make('Contabilidad')
                            ->visible(false)
                            ->schema([
                                Forms\Components\Section::make('Cuenta contable del producto')
                                    ->description('Pendiente de definir: aquí se ligará la cuenta contable propia del producto cuando creemos el catálogo/cuenta de producto. Por ahora no se mostrarán cuentas de inventario, costo o ingreso para evitar configuraciones incorrectas.')
                                    ->schema([
                                        Forms\Components\Placeholder::make('product_account_pending')
                                            ->label('Estado')
                                            ->content('Pendiente: cuenta contable de producto no creada todavía.')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(12),
                            ]),


                        Forms\Components\Tabs\Tab::make('Facturación')
                            ->schema([
                                Forms\Components\Section::make('Datos SAT')
                                    ->schema([
                                        Forms\Components\Select::make('sat_product_service_code')
                                            ->label('Clave producto/servicio SAT')
                                            ->options(fn (): array => static::satProductSearchOptions(null, 30))
                                            ->getSearchResultsUsing(fn (string $search): array => static::satProductSearchOptions($search, 80))
                                            ->getOptionLabelUsing(fn ($value): ?string => static::satProductOptionLabel($value))
                                            ->searchable()
                                            ->native(false)
                                            ->placeholder('Busca por clave o descripción SAT')
                                            ->helperText('Escribe la clave completa, una parte de la clave o texto de la descripción. Ej. 44121600 o tijeras.')
                                            ->columnSpan(6),

                                        Forms\Components\Select::make('sat_unit_code')
                                            ->label('Unidad SAT')
                                            ->options(fn (): array => static::satUnitSearchOptions(null, 30))
                                            ->getSearchResultsUsing(fn (string $search): array => static::satUnitSearchOptions($search, 80))
                                            ->getOptionLabelUsing(fn ($value): ?string => static::satUnitOptionLabel($value))
                                            ->searchable()
                                            ->native(false)
                                            ->placeholder('Busca por clave, nombre o símbolo SAT')
                                            ->helperText('Ej. H87, E48, pieza, servicio.')
                                            ->columnSpan(6),
                                        Forms\Components\Select::make('sat_tax_object_code')
                                            ->label('Objeto de impuesto SAT')
                                            ->options(fn (): array => static::satTaxObjectOptions(null, 20))
                                            ->getSearchResultsUsing(fn (string $search): array => static::satTaxObjectOptions($search, 50))
                                            ->getOptionLabelUsing(fn ($value): ?string => static::satTaxObjectLabel($value))
                                            ->searchable()
                                            ->native(false)
                                            ->default('02')
                                            ->placeholder('Selecciona objeto de impuesto')
                                            ->helperText('Para productos con IVA normalmente usa 02 - Sí objeto de impuesto.')
                                            ->columnSpan(6),

                                        Forms\Components\TextInput::make('country_of_origin')
                                            ->label('País origen')
                                            ->maxLength(3)
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('hs_code')
                                            ->label('Código HS')
                                            ->maxLength(60)
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('tariff_fraction')
                                            ->label('Fracción arancelaria')
                                            ->maxLength(60)
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('customs_unit_code')
                                            ->label('UMT aduana')
                                            ->maxLength(60)
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('hazardous_material_code')
                                            ->label('Material peligroso')
                                            ->maxLength(60)
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('hazardous_packaging_code')
                                            ->label('Embalaje peligroso')
                                            ->maxLength(60)
                                            ->columnSpan(3),
                                    ])
                                    ->columns(12),

                                Forms\Components\Section::make('Impuestos')
                                    ->description('Selecciona los impuestos aplicables para venta y compra del producto.')
                                    ->schema([
                                        Forms\Components\Select::make('sale_tax_rate_ids')
                                            ->label('Impuestos de venta')
                                            ->options(fn (): array => static::taxRateOptions('sale'))
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->dehydrated(false)
                                            ->afterStateHydrated(function (Forms\Components\Select $component, ?Product $record): void {
                                                if (! $record || ! $record->exists || ! \Illuminate\Support\Facades\Schema::hasTable('product_tax_rates')) {
                                                    $component->state([]);
                                                    return;
                                                }

                                                $component->state(
                                                    \Illuminate\Support\Facades\DB::table('product_tax_rates')
                                                        ->where('product_id', $record->id)
                                                        ->where('usage_type', 'sale')
                                                        ->where('is_active', true)
                                                        ->pluck('tax_rate_id')
                                                        ->map(fn ($id) => (int) $id)
                                                        ->all()
                                                );
                                            })
                                            ->helperText('Ejemplo común: IVA16.')
                                            ->columnSpan(6),

                                        Forms\Components\Select::make('purchase_tax_rate_ids')
                                            ->label('Impuestos de compra')
                                            ->options(fn (): array => static::taxRateOptions('purchase'))
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->dehydrated(false)
                                            ->afterStateHydrated(function (Forms\Components\Select $component, ?Product $record): void {
                                                if (! $record || ! $record->exists || ! \Illuminate\Support\Facades\Schema::hasTable('product_tax_rates')) {
                                                    $component->state([]);
                                                    return;
                                                }

                                                $component->state(
                                                    \Illuminate\Support\Facades\DB::table('product_tax_rates')
                                                        ->where('product_id', $record->id)
                                                        ->where('usage_type', 'purchase')
                                                        ->where('is_active', true)
                                                        ->pluck('tax_rate_id')
                                                        ->map(fn ($id) => (int) $id)
                                                        ->all()
                                                );
                                            })
                                            ->helperText('Ejemplo común: IVA16.')
                                            ->columnSpan(6),
                                    ])
                                    ->columns(12)
                                    ->columnSpanFull(), // product_taxes_section_v1

                            ]),
                    
                    Forms\Components\Tabs\Tab::make('Variantes')
                        ->schema([

                            Forms\Components\Section::make('Datos de la variante')
                                ->visible(fn (?\Illuminate\Database\Eloquent\Model $record): bool => request()->filled('parent_product_id') || (bool) ($record?->is_variant ?? false))
                                ->schema([
                                    Forms\Components\Placeholder::make('variant_parent_readonly_v6')
                                        ->label('Producto padre')
                                        ->visible(fn (): bool => request()->filled('parent_product_id'))
                                        ->content(function (): \Illuminate\Support\HtmlString {
                                            // variant_fields_in_variants_tab_v6
                                            $parentId = (int) request()->query('parent_product_id');

                                            if ($parentId <= 0) {
                                                return new \Illuminate\Support\HtmlString('');
                                            }

                                            $parent = \App\Models\Product::query()->find($parentId);

                                            if (! $parent) {
                                                return new \Illuminate\Support\HtmlString('<span class="text-sm text-gray-500">Producto padre no encontrado.</span>');
                                            }

                                            $url = self::getUrl('edit', ['record' => $parent]);

                                            return new \Illuminate\Support\HtmlString(
                                                '<div class="rounded-xl border border-primary-200 bg-primary-50 p-4 text-sm text-primary-800">' .
                                                '<div class="text-xs font-medium uppercase tracking-wide">Producto padre</div>' .
                                                '<div class="mt-1 font-semibold"><a class="text-primary-700 hover:underline" href="' . e($url) . '">' . e($parent->name) . '</a></div>' .
                                                '<div class="mt-1">Captura el valor de la variante. Al guardar se creará como: <strong>' . e($parent->name) . ' - [valor]</strong>.</div>' .
                                                '</div>'
                                            );
                                        })
                                        ->columnSpanFull(),

                                    Forms\Components\TextInput::make('variant_group')
                                        ->label('Atributo de variante')
                                        ->default('Color')
                                        ->afterStateHydrated(function ($component, $state): void {
                                            if (blank($state)) {
                                                $component->state('Color');
                                            }
                                        })
                                        ->required()
                                        ->dehydrated()
                                        ->maxLength(100),

                                    Forms\Components\TextInput::make('variant_value')
                                        ->label('Valor de variante')
                                        ->placeholder('Ej. Rojo, Azul, Grande')
                                        ->required(fn (): bool => request()->filled('parent_product_id'))
                                        ->dehydrated()
                                        ->maxLength(150),

                                    Forms\Components\TextInput::make('variant_name')
                                        ->label('Nombre corto de variante')
                                        ->placeholder('Opcional, normalmente igual al valor')
                                        ->dehydrated()
                                        ->maxLength(150),
                                ])
                                ->columns(3)
                                ->columnSpanFull(),


Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('add_variant_native_action')
                                ->label('Agregar variante')
                                ->icon('heroicon-m-plus')
                                ->color('primary')
                                ->url(fn (?\Illuminate\Database\Eloquent\Model $record): ?string => $record
                                    ? self::getUrl('create') . '?parent_product_id=' . $record->id
                                    : null
                                )
                                ->visible(fn (?\Illuminate\Database\Eloquent\Model $record): bool => filled($record) && ! (bool) ($record->is_variant ?? false)),
                        ])
                            ->alignEnd()
                            ->columnSpanFull(),

                                                        
                            Forms\Components\Hidden::make('parent_product_id')
                                ->default(fn () => request()->query('parent_product_id') ? (int) request()->query('parent_product_id') : null)
                                ->dehydrated(),

                            Forms\Components\Hidden::make('is_variant')
                                ->default(fn () => request()->filled('parent_product_id'))
                                ->dehydrated(),

/* V5.72.5e2b/e4: variantes únicas y filtradas por empresa del producto padre */
Forms\Components\Placeholder::make('/* V5.72.5j0: filas de variantes clicables usando el link Abrir */
variants_inner_table')
                                ->label('')
                                ->content(function (?\Illuminate\Database\Eloquent\Model $record): \Illuminate\Support\HtmlString {
                                    if (! $record) {
                                        return new \Illuminate\Support\HtmlString('<div class="text-sm text-gray-500">Guarda el producto para ver variantes.</div>');
                                    }

                                    if ((bool) ($record->is_variant ?? false)) {
                                        $parent = $record->parentProduct;

                                        if (! $parent) {
                                            return new \Illuminate\Support\HtmlString('<div class="text-sm text-gray-500">Esta variante no tiene producto padre ligado.</div>');
                                        }

                                        $url = self::getUrl('edit', ['record' => $parent]);

                                        return new \Illuminate\Support\HtmlString(
                                            '<div class="rounded-xl border border-gray-200 bg-white p-4 text-sm">' .
                                            '<div class="font-semibold text-gray-950">Esta es una variante</div>' .
                                            '<div class="mt-1 text-gray-600">Producto padre: <a class="inline-flex items-center gap-1 font-medium text-primary-600 hover:text-primary-500" href="' . e($url) . '">' . e($parent->name) . '</a></div>' .
                                            '<div class="mt-1 text-gray-600">Variante: ' . e(($record->variant_group ?: 'Variante') . ': ' . ($record->variant_value ?: $record->variant_name ?: $record->name)) . '</div>' .
                                            '</div>'
                                        );
                                    }

                                    $variants = \App\Models\Product::query()
                                        ->where('parent_product_id', $record->id)
                                        ->when(
                                            \Illuminate\Support\Facades\Schema::hasColumn('products', 'company_id') && ! empty($record->company_id),
                                            fn ($query) => $query->where('company_id', (int) $record->company_id),
                                        )
                                        ->where('is_variant', true)
                                        // BEXIA_V5727N28C_VARIANTS_TABLE_ACTIVE_ONLY
                                        ->when(
                                            \Illuminate\Support\Facades\Schema::hasColumn('products', 'is_active'),
                                            fn ($query) => $query->where(function ($q): void {
                                                $q->whereNull('is_active')->orWhere('is_active', true);
                                            }),
                                        )
                                        ->orderBy('variant_group')
                                        ->orderBy('variant_value')
                                        ->orderBy('id')
                                        ->get()
                                        ->unique('id')
                                        ->values();

                                    if ($variants->isEmpty()) {
                                        return new \Illuminate\Support\HtmlString('<div class="text-sm text-gray-500">Este producto no tiene variantes ligadas.</div>');
                                    }

                                    $rows = $variants->map(function ($variant) {
                                        $extra = is_array($variant->extra_attributes)
                                            ? $variant->extra_attributes
                                            : json_decode((string) $variant->extra_attributes, true);

                                        $extra = is_array($extra) ? $extra : [];

                                        $image = '—';

                                        if (! empty($variant->image_path)) {
                                            $src = \Illuminate\Support\Facades\Storage::disk('public')->url($variant->image_path);
                                            $image = '<img src="' . e($src) . '" alt="' . e($variant->name) . '" style="width:44px;height:44px;object-fit:cover;border-radius:10px;border:1px solid #e5e7eb;">';
                                        }

                                        $url = self::getUrl('edit', ['record' => $variant]);

                                        return '<tr data-bexia-variant-row="1" style="cursor:pointer;" onclick="if (! event.target.closest(&quot;a,button,input,select,textarea,label,[role=button]&quot;)) { const link = this.querySelector(&quot;a[href]&quot;); if (link) { window.location.href = link.href; } }">' .
                                            '<td class="px-4 py-3">' . $image . '</td>' .
                                            '<td class="px-4 py-3"><span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-700/10">' . e($variant->variant_group ?: '—') . '</span></td>' .
                                            '<td class="px-4 py-3 font-medium text-gray-950">' . e($variant->variant_value ?: $variant->variant_name ?: '—') . '</td>' .
                                            '<td class="px-4 py-3">' . e($variant->internal_reference ?: '—') . '</td>' .
                                            '<td class="px-4 py-3">' . e($variant->sku ?: '—') . '</td>' .
                                            '<td class="px-4 py-3 text-right">' . static::variantSalePriceLabel($variant) . '</td>' .
                                            '<td class="px-4 py-3 text-right">' . static::variantAverageCostLabel($variant) . '</td>' .
                                            '<td class="px-4 py-3 text-right">' . e(static::formatOperationalMetric(static::productOperationalStockQuantity($variant, false))) . '</td>' .
                                            '<td class="px-4 py-3 text-center">' . ((bool) $variant->is_active
                                                ? '<span title="Activo" class="inline-flex h-6 w-6 items-center justify-center rounded-full text-success-600 ring-1 ring-success-600/20"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.313a1 1 0 0 1-1.42 0L3.29 9.267a1 1 0 1 1 1.42-1.414l4.04 4.04 6.54-6.596a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd"/></svg></span>'
                                                : '<span title="Inactivo" class="inline-flex h-6 w-6 items-center justify-center rounded-full text-danger-600 ring-1 ring-danger-600/20"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 8.586 14.95 3.636a1 1 0 0 1 1.414 1.414L11.414 10l4.95 4.95a1 1 0 0 1-1.414 1.414L10 11.414l-4.95 4.95a1 1 0 0 1-1.414-1.414L8.586 10l-4.95-4.95A1 1 0 1 1 5.05 3.636L10 8.586Z" clip-rule="evenodd"/></svg></span>'
                                            ) . '</td>' .
                                            '<td class="px-4 py-3 text-right"><a class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm font-medium text-primary-600 hover:bg-primary-50 hover:text-primary-700" href="' . e($url) . '"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M12.232 4.232a.75.75 0 0 1 .53-.22h3.238a.75.75 0 0 1 .75.75V8a.75.75 0 0 1-1.5 0V6.573l-5.47 5.47a.75.75 0 1 1-1.06-1.06l5.47-5.47h-1.427a.75.75 0 0 1-.53-1.28Z"/><path d="M5.25 5.5A1.75 1.75 0 0 0 3.5 7.25v7.5c0 .966.784 1.75 1.75 1.75h7.5a1.75 1.75 0 0 0 1.75-1.75v-3a.75.75 0 0 0-1.5 0v3a.25.25 0 0 1-.25.25h-7.5a.25.25 0 0 1-.25-.25v-7.5A.25.25 0 0 1 5.25 7h3a.75.75 0 0 0 0-1.5h-3Z"/></svg><span>Abrir</span></a></td>' .
                                            '</tr>';
                                    })->implode('');

                                    $createVariantUrl = self::getUrl('create') . '?parent_product_id=' . $record->id;

                                    $toolbar = '';

                                    if (! (bool) ($record->is_variant ?? false)) {
                                        // variant_add_button_toolbar_v2
                                        $toolbar = '<div class="mb-4 flex items-center justify-between gap-3">' .
                                            '<div>' .
                                            '<div class="text-sm font-semibold text-gray-950">Variantes del producto</div>' .
                                            '<div class="text-xs text-gray-500">Agrega variantes con referencia, precio, costo, imagen y stock propio.</div>' .
                                            '</div>' .
                                            '<a href="' . e($createVariantUrl) . '" class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500">' .
                                            'Agregar variante' .
                                            '</a>' .
                                            '</div>';
                                    }

                                    $html = $toolbar . '<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm ring-1 ring-gray-950/5">' .
                                        '<table class="w-full table-auto divide-y divide-gray-200 text-sm">' .
                                        '<thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">' .
                                        '<tr>' .
                                        '<th class="px-4 py-3">Imagen</th>' .
                                        '<th class="px-4 py-3">Atributo</th>' .
                                        '<th class="px-4 py-3">Valor</th>' .
                                        '<th class="px-4 py-3">Referencia</th>' .
                                        '<th class="px-4 py-3">SKU / Código de barras</th>' .
                                        '<th class="px-4 py-3 text-right">Precio</th>' .
                                        '<th class="px-4 py-3 text-right">Costo</th>' .
                                        '<th class="px-4 py-3 text-right">Stock</th>' .
                                        '<th class="px-4 py-3">Activo</th>' .
                                        '<th class="px-4 py-3"></th>' .
                                        '</tr>' .
                                        '</thead>' .
                                        '<tbody class="divide-y divide-gray-200 bg-white">' . $rows . '</tbody>' .
                                        '</table>' .
                                        '</div>';

                                    return new \Illuminate\Support\HtmlString($html);
                                })
                                ->columnSpanFull(),
                        ]),
])
                    ->columnSpanFull(),
            ]);
    }



    protected static function productListStockLabel(Product $record): string
    {
        $value = static::productListStockValue($record);

        if (abs($value - round($value)) < 0.00001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    protected static function productListStockValue(Product $record): float
    {
        return static::productOperationalStockQuantity($record, false);
    }

    protected static function stockForProductIds(array $productIds): float
    {
        $productIds = collect($productIds)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($productIds)) {
            return 0.0;
        }

        $stockByProduct = [];
        $productsWithoutStockLevel = $productIds;

        if (
            Schema::hasTable('stock_levels') &&
            Schema::hasColumn('stock_levels', 'product_id') &&
            Schema::hasColumn('stock_levels', 'quantity_on_hand')
        ) {
            $rows = DB::table('stock_levels')
                ->whereIn('product_id', $productIds)
                ->select('product_id', DB::raw('SUM(quantity_on_hand) as qty'))
                ->groupBy('product_id')
                ->get();

            foreach ($rows as $row) {
                $productId = (int) $row->product_id;
                $stockByProduct[$productId] = (float) $row->qty;
            }

            $productsWithoutStockLevel = collect($productIds)
                ->reject(fn ($id) => array_key_exists((int) $id, $stockByProduct))
                ->values()
                ->all();
        }

        if (! empty($productsWithoutStockLevel)) {
            Product::query()
                ->whereIn('id', $productsWithoutStockLevel)
                ->get(['id', 'extra_attributes'])
                ->each(function (Product $product) use (&$stockByProduct): void {
                    $stockByProduct[(int) $product->id] = static::stockFromProductExtraAttributes($product);
                });
        }

        return collect($stockByProduct)->sum(fn ($value) => (float) $value);
    }

    protected static function stockFromProductExtraAttributes(Product $product): float
    {
        $extra = $product->extra_attributes ?? [];

        if (is_string($extra)) {
            $decoded = json_decode($extra, true);
            $extra = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($extra)) {
            return 0.0;
        }

        foreach ([
            'source_stock_qty',
            'stock_migrado',
            'migrated_stock',
            'stock',
            'qty',
            'quantity',
            'existencia',
            'existencias',
        ] as $key) {
            if (array_key_exists($key, $extra) && is_numeric($extra[$key])) {
                return (float) $extra[$key];
            }
        }

        return 0.0;
    }

    protected static function productVariantStatusLabel(Product $record): string
    {
        // BEXIA_V5727N28E_VARIANTS_LABEL_CACHED_ACTIVE_ONLY
        if ((bool) ($record->is_variant ?? false)) {
            return 'Variante';
        }

        /*
         * Optimización:
         * - has_variants sirve como guard rápido para productos que nunca tuvieron variantes.
         * - si has_variants=true, sí validamos contra variantes activas y de la misma empresa.
         * - cacheamos por request para que getStateUsing() y color() no consulten dos veces por fila.
         */
        if (! (bool) ($record->has_variants ?? false)) {
            return 'Sin variantes';
        }

        static $cache = [];

        $key = ((int) ($record->company_id ?? 0)) . ':' . ((int) $record->getKey());

        if (! array_key_exists($key, $cache)) {
            $query = Product::query()
                ->where('company_id', $record->company_id)
                ->where('parent_product_id', $record->id)
                ->where('is_variant', true);

            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'is_active')) {
                $query->where(function ($q): void {
                    $q->whereNull('is_active')->orWhere('is_active', true);
                });
            } elseif (\Illuminate\Support\Facades\Schema::hasColumn('products', 'active')) {
                $query->where(function ($q): void {
                    $q->whereNull('active')->orWhere('active', true);
                });
            }

            $cache[$key] = $query->exists();
        }

        return $cache[$key] ? 'Con variantes' : 'Sin variantes';
    }



    protected static function productVariantStatusColor(Product $record): string
    {
        return match (static::productVariantStatusLabel($record)) {
            'Con variantes' => 'success',
            'Variante' => 'info',
            default => 'gray',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            // BEXIA_V5727N29C_EAGER_LOAD_PRODUCT_LIST
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['category', 'unit'])
                ->withExists([
                    'images as has_active_images' => fn ($imageQuery) => $imageQuery->where('is_active', true),
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('internal_reference')
                    ->label('Referencia interna')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('unit.code')
                    ->label('UdM')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('product_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'stockable' => 'Almacenable',
                        'consumable' => 'Consumible',
                        'service' => 'Servicio',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('tracking')
                    ->label('Seguimiento')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'none' => 'Sin seguimiento',
                        'lot' => 'Lotes',
                        'serial' => 'Series',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('advanced_tracking_mode')
                    ->label('Trazabilidad avanzada')
                    ->formatStateUsing(fn (?string $state): string => match ((string) $state) {
                        'required' => 'Obligatoria',
                        'warning' => 'Recomendada',
                        default => 'No aplica',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ((string) $state) {
                        'required' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('product_list_stock')
                    ->label('Stock')
                    ->getStateUsing(fn (Product $record): string => static::productListStockLabel($record))
                    ->alignEnd()
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('variant_status')
                    ->label('Variantes')
                    ->getStateUsing(fn (Product $record): string => static::productVariantStatusLabel($record))
                    ->badge()
                    ->color(fn (Product $record): string => static::productVariantStatusColor($record))
                    ->toggleable(),


                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Precio venta')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('standard_cost')
                    ->label('Costo')
                    ->money('MXN')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('has_image')
                    ->label('Imagen')
                    ->boolean()
                    ->getStateUsing(fn (Product $record): bool => filled($record->image_path) || (bool) ($record->has_active_images ?? false))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU / Código de barras')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_pos_favorite')
                    ->label('Favorito PDV')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_type')
                    ->label('Tipo')
                    ->options([
                        'stockable' => 'Almacenable',
                        'consumable' => 'Consumible',
                        'service' => 'Servicio',
                    ]),

                Tables\Filters\SelectFilter::make('tracking')
                    ->label('Seguimiento')
                    ->options([
                        'none' => 'Sin seguimiento',
                        'lot' => 'Lotes',
                        'serial' => 'Series',
                    ]),

                Tables\Filters\SelectFilter::make('advanced_tracking_mode')
                    ->label('Trazabilidad avanzada')
                    ->options([
                        'none' => 'No aplica',
                        'warning' => 'Recomendada con aviso',
                        'required' => 'Obligatoria',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),

                Tables\Filters\TernaryFilter::make('available_in_pos')
                    ->label('Disponible en POS'),

                Tables\Filters\TernaryFilter::make('is_pos_favorite')
                    ->label('Favorito PDV'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),

                Tables\Actions\Action::make('archive_product')
                    ->label('Archivar')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->visible(fn (Product $record): bool => (bool) $record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Archivar producto')
                    ->modalDescription(fn (Product $record): string => (bool) ($record->has_variants ?? false)
                        ? 'Se archivará este producto y también sus variantes. No se eliminará historial, stock ni movimientos.'
                        : 'Se archivará este producto. No se eliminará historial, stock ni movimientos.')
                    ->action(function (Product $record): void {
                        static::archiveProductRecord($record);
                    })
                    ->successNotificationTitle('Producto archivado'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('archive_products')
                        ->label('Archivar seleccionados')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Archivar productos seleccionados')
                        ->modalDescription('Se archivarán los productos seleccionados. Si alguno es producto padre, también se archivarán sus variantes. No se eliminará historial, stock ni movimientos.')
                        ->action(function ($records): void {
                            $records->each(fn (Product $record) => static::archiveProductRecord($record));
                        })
                        ->successNotificationTitle('Productos archivados'),
                ]),
            ]);
    }

    

    public static function archiveProductRecord(Product $record): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('products', 'is_active')) {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($record): void {
            $now = now();

            Product::query()
                ->whereKey($record->id)
                ->update([
                    'is_active' => false,
                    'updated_at' => $now,
                ]);

            if (
                \Illuminate\Support\Facades\Schema::hasColumn('products', 'parent_product_id')
                && \Illuminate\Support\Facades\Schema::hasColumn('products', 'is_variant')
            ) {
                $variants = Product::query()
                    ->where('parent_product_id', $record->id);

                if (
                    \Illuminate\Support\Facades\Schema::hasColumn('products', 'company_id')
                    && ! empty($record->company_id)
                ) {
                    $variants->where('company_id', (int) $record->company_id);
                }

                $variants->update([
                    'is_active' => false,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    protected static function currentCompanyIdForProductResource(): ?int
    {
        try {
            $tenant = \Filament\Facades\Filament::getTenant();

            if (is_object($tenant) && isset($tenant->id)) {
                return (int) $tenant->id;
            }
        } catch (\Throwable $e) {
            //
        }

        $routeTenant = request()->route('tenant');

        if (is_object($routeTenant) && isset($routeTenant->id)) {
            return (int) $routeTenant->id;
        }

        if (is_numeric($routeTenant)) {
            return (int) $routeTenant;
        }

        $user = auth()->user();

        if ($user && isset($user->company_id)) {
            return (int) $user->company_id;
        }

        return null;
    }

    protected static function productCategoryTreeOptions(?string $search = null, int $limit = 300): array
    {
        if (! class_exists(\App\Models\ProductCategory::class)) {
            return [];
        }

        $companyId = self::currentCompanyIdForProductResource();

        $query = \App\Models\ProductCategory::query()
            ->select(['id', 'company_id', 'parent_id', 'code', 'name'])
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId));

        if (\Illuminate\Support\Facades\Schema::hasColumn('product_categories', 'is_active')) {
            $query->where('is_active', true);
        }

        $categories = $query
            ->orderBy('name')
            ->get();

        $byId = $categories->keyBy('id');

        $options = [];

        foreach ($categories as $category) {
            $options[$category->id] = self::buildProductCategoryTreeLabel($category, $byId);
        }

        $search = trim((string) $search);

        if ($search !== '') {
            $needle = mb_strtolower($search);

            $options = collect($options)
                ->filter(function (string $label) use ($needle): bool {
                    return str_contains(mb_strtolower($label), $needle);
                })
                ->all();
        }

        return collect($options)
            ->sort()
            ->take($limit)
            ->all();
    }

    protected static function productCategoryTreeLabel(mixed $value): ?string
    {
        if (! $value || ! class_exists(\App\Models\ProductCategory::class)) {
            return null;
        }

        $companyId = self::currentCompanyIdForProductResource();

        $query = \App\Models\ProductCategory::query()
            ->select(['id', 'company_id', 'parent_id', 'code', 'name'])
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId));

        $category = $query->whereKey($value)->first();

        if (! $category) {
            return null;
        }

        $categories = \App\Models\ProductCategory::query()
            ->select(['id', 'company_id', 'parent_id', 'code', 'name'])
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->get();

        return self::buildProductCategoryTreeLabel($category, $categories->keyBy('id'));
    }

    protected static function buildProductCategoryTreeLabel(object $category, \Illuminate\Support\Collection $byId): string
    {
        $names = [];
        $current = $category;
        $guard = 0;

        while ($current && $guard < 30) {
            array_unshift($names, trim((string) $current->name));

            $parentId = $current->parent_id ?? null;

            if (! $parentId || ! $byId->has($parentId)) {
                break;
            }

            $current = $byId->get($parentId);
            $guard++;
        }

        $path = implode(' / ', array_filter($names));
        $code = trim((string) ($category->code ?? ''));

        return $code !== ''
            ? $code . ' - ' . $path
            : $path;
    }

    protected static function defaultProductCategoryId(): ?int
    {
        if (! class_exists(\App\Models\ProductCategory::class)) {
            return null;
        }

        $companyId = self::currentCompanyIdForProductResource();

        return \App\Models\ProductCategory::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where(function ($query): void {
                $query
                    ->whereRaw('upper(code) = ?', ['GENERAL'])
                    ->orWhereRaw('upper(name) = ?', ['GENERAL'])
                    ->orWhere('code', 'CAT-GENERAL');
            })
            ->value('id');
    }


    protected static function productCategoryShortLabel(mixed $value): ?string
    {
        if (! $value || ! class_exists(\App\Models\ProductCategory::class)) {
            return null;
        }

        $companyId = self::currentCompanyIdForProductResource();

        $category = \App\Models\ProductCategory::query()
            ->select(['id', 'company_id', 'parent_id', 'code', 'name'])
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->whereKey($value)
            ->first();

        if (! $category) {
            return null;
        }

        $code = trim((string) ($category->code ?? ''));
        $name = trim((string) ($category->name ?? ''));

        return $code !== ''
            ? $code . ' - ' . $name
            : $name;
    }


    protected static function supplierOptions(): array
    {
        $companyId = static::currentCompanyIdForProducts();

        foreach (['suppliers', 'vendors', 'contacts'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table);

            if (Schema::hasColumn($table, 'company_id') && $companyId) {
                $query->where(function ($query) use ($table, $companyId): void {
                    $query
                        ->where($table . '.company_id', $companyId)
                        ->orWhereNull($table . '.company_id');
                });
            }

            if (Schema::hasColumn($table, 'is_active')) {
                $query->where($table . '.is_active', true);
            }

            if ($table === 'contacts') {
                $hasSupplierFilter = false;

                $query->where(function ($query) use ($table, &$hasSupplierFilter): void {
                    foreach (['is_supplier', 'is_vendor', 'supplier', 'vendor'] as $column) {
                        if (Schema::hasColumn($table, $column)) {
                            $query->orWhere($table . '.' . $column, true);
                            $hasSupplierFilter = true;
                        }
                    }

                    foreach (['contact_type', 'type', 'category'] as $column) {
                        if (Schema::hasColumn($table, $column)) {
                            $query->orWhereIn($table . '.' . $column, [
                                'supplier',
                                'vendor',
                                'proveedor',
                            ]);
                            $hasSupplierFilter = true;
                        }
                    }
                });
            }

            $labelColumn = static::firstExistingProductColumn($table, [
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

            return $query
                ->orderBy($labelColumn)
                ->limit(500)
                ->get(['id', $labelColumn])
                ->mapWithKeys(fn ($row): array => [
                    $row->id => (string) ($row->{$labelColumn} ?? ('Proveedor #' . $row->id)),
                ])
                ->all();
        }

        return [];
    }

    protected static function lastPurchasesPreview(?\App\Models\Product $record): HtmlString
    {
        if (! $record) {
            return new HtmlString('<span style="color:#6b7280;">Guarda el producto para ver historial.</span>');
        }

        $productId = (int) $record->getKey();

        $rows = static::lastPurchaseRows($productId);

        if (empty($rows)) {
            return new HtmlString('<span style="color:#6b7280;">Aún no hay compras registradas para este producto.</span>');
        }

        $html = '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
        $html .= '<thead><tr>';
        $html .= '<th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:4px;">Fecha</th>';
        $html .= '<th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:4px;">Proveedor</th>';
        $html .= '<th style="text-align:right;border-bottom:1px solid #e5e7eb;padding:4px;">Cantidad</th>';
        $html .= '<th style="text-align:right;border-bottom:1px solid #e5e7eb;padding:4px;">Costo unit. sin IVA</th>';
        $html .= '<th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:4px;">Documento</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td style="padding:4px;border-bottom:1px solid #f3f4f6;">' . e($row['date'] ?? '—') . '</td>';
            $html .= '<td style="padding:4px;border-bottom:1px solid #f3f4f6;">' . e($row['supplier'] ?? '—') . '</td>';
            $html .= '<td style="padding:4px;border-bottom:1px solid #f3f4f6;text-align:right;">' . e($row['quantity'] ?? '—') . '</td>';
            $html .= '<td style="padding:4px;border-bottom:1px solid #f3f4f6;text-align:right;">' . e($row['cost'] ?? '—') . '</td>';
            $html .= '<td style="padding:4px;border-bottom:1px solid #f3f4f6;">' . e($row['document'] ?? '—') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return new HtmlString($html);
    }

    protected static function lastPurchaseRows(int $productId): array
    {
        /*
         * Preparado para compras reales.
         * Cuando el módulo de compras esté creando tablas definitivas, este método
         * puede mapear purchase_orders / purchase_order_lines.
         */
        $candidates = [
            ['lines' => 'purchase_order_lines', 'header' => 'purchase_orders'],
            ['lines' => 'purchase_lines', 'header' => 'purchases'],
            ['lines' => 'purchase_items', 'header' => 'purchases'],
        ];

        foreach ($candidates as $candidate) {
            $lineTable = $candidate['lines'];
            $headerTable = $candidate['header'];

            if (! Schema::hasTable($lineTable)) {
                continue;
            }

            $productColumn = static::firstExistingProductColumn($lineTable, ['product_id']);

            if (! $productColumn) {
                continue;
            }

            $dateColumn = Schema::hasTable($headerTable)
                ? static::firstExistingProductColumn($headerTable, ['date', 'order_date', 'purchase_date', 'created_at'])
                : null;

            $quantityColumn = static::firstExistingProductColumn($lineTable, ['quantity', 'qty', 'product_qty']);
            $costColumn = static::firstExistingProductColumn($lineTable, ['unit_cost', 'price_unit', 'unit_price', 'cost', 'purchase_price']);

            $query = DB::table($lineTable)
                ->where($lineTable . '.' . $productColumn, $productId);

            if (Schema::hasTable($headerTable)) {
                $foreign = static::firstExistingProductColumn($lineTable, ['purchase_order_id', 'purchase_id', 'order_id']);

                if ($foreign) {
                    $query->leftJoin($headerTable, $headerTable . '.id', '=', $lineTable . '.' . $foreign);
                }
            }

            $rows = $query
                ->orderByDesc($dateColumn ? $headerTable . '.' . $dateColumn : $lineTable . '.id')
                ->limit(5)
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            return $rows->map(function ($row) use ($dateColumn, $quantityColumn, $costColumn): array {
                return [
                    'date' => $dateColumn && isset($row->{$dateColumn}) ? (string) $row->{$dateColumn} : '—',
                    'supplier' => '—',
                    'quantity' => $quantityColumn && isset($row->{$quantityColumn}) ? number_format((float) $row->{$quantityColumn}, 2) : '—',
                    'cost' => $costColumn && isset($row->{$costColumn}) ? '$ ' . number_format((float) $row->{$costColumn}, 4) : '—',
                    'document' => '—',
                ];
            })->all();
        }

        return [];
    }

    protected static function firstExistingProductColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    protected static function currentCompanyIdForProducts(): ?int
    {
        if (class_exists(\Filament\Facades\Filament::class)) {
            try {
                $tenant = \Filament\Facades\Filament::getTenant();

                if ($tenant && method_exists($tenant, 'getKey')) {
                    return (int) $tenant->getKey();
                }
            } catch (\Throwable $exception) {
                // continuar
            }
        }

        $user = auth()->user();

        if ($user && isset($user->company_id)) {
            return (int) $user->company_id;
        }

        return null;
    }




    protected static function variantSalePriceLabel(Product $variant): string
    {
        $price = static::firstPositiveProductValue($variant, [
            'sale_price',
            'list_price',
            'price',
        ]);

        return number_format((float) ($price ?? 0), 2) . ' MXN';
    }

    protected static function variantAverageCostLabel(Product $variant): string
    {
        $cost = static::stockAverageCostForProductVariant($variant);

        if ($cost === null) {
            $cost = static::firstPositiveProductValue($variant, [
                'average_cost_without_tax',
                'standard_cost',
                'cost',
                'purchase_price',
                'last_purchase_cost',
            ]);
        }

        return number_format((float) ($cost ?? 0), 2) . ' MXN';
    }

    protected static function firstPositiveProductValue(Product $product, array $columns): ?float
    {
        foreach ($columns as $column) {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('products', $column)) {
                continue;
            }

            $value = $product->{$column} ?? null;

            if ($value !== null && $value !== '' && (float) $value > 0) {
                return (float) $value;
            }
        }

        return null;
    }

    protected static function stockAverageCostForProductVariant(Product $variant): ?float
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('stock_quants')) {
            return null;
        }

        if (! \Illuminate\Support\Facades\Schema::hasColumn('stock_quants', 'average_cost')) {
            return null;
        }

        $query = \Illuminate\Support\Facades\DB::table('stock_quants');

        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_quants', 'product_variant_id')) {
            $query->where('product_variant_id', $variant->id);
        } else {
            return null;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_quants', 'product_id')) {
            $parentId = $variant->parent_product_id ?? null;

            if ($parentId) {
                $query->where('product_id', $parentId);
            }
        }

        $companyId = static::currentCompanyIdForProducts();

        if (
            $companyId
            && \Illuminate\Support\Facades\Schema::hasColumn('stock_quants', 'company_id')
        ) {
            $query->where('company_id', $companyId);
        }

        $rows = $query
            ->whereNotNull('average_cost')
            ->get([
                'quantity',
                'average_cost',
            ]);

        if ($rows->isEmpty()) {
            return null;
        }

        $positiveRows = $rows->filter(fn ($row): bool => (float) ($row->quantity ?? 0) > 0);

        if ($positiveRows->isNotEmpty()) {
            $totalQuantity = (float) $positiveRows->sum(fn ($row): float => (float) ($row->quantity ?? 0));

            if ($totalQuantity > 0) {
                $totalCost = (float) $positiveRows->sum(
                    fn ($row): float => ((float) ($row->quantity ?? 0)) * ((float) ($row->average_cost ?? 0))
                );

                return $totalCost / $totalQuantity;
            }
        }

        $avg = $rows
            ->filter(fn ($row): bool => (float) ($row->average_cost ?? 0) > 0)
            ->avg(fn ($row): float => (float) ($row->average_cost ?? 0));

        return $avg !== null ? (float) $avg : null;
    }

    protected static function salePriceWithTaxLabel(Forms\Get $get): string
    {
        $price = null;

        foreach (['sale_price', 'list_price', 'price'] as $field) {
            $value = $get($field);

            if ($value !== null && $value !== '') {
                $price = (float) $value;
                break;
            }
        }

        $taxRate = $get('sale_tax_rate');

        if ($taxRate === null || $taxRate === '') {
            $taxRate = 16;
        }

        return '$ ' . number_format(((float) ($price ?? 0)) * (1 + ((float) $taxRate / 100)), 4);
    }

    protected static function purchaseCostWithTaxLabel(Forms\Get $get): string
    {
        $cost = $get('average_cost_without_tax');

        if ($cost === null || $cost === '') {
            $cost = 0;
        }

        $taxRate = $get('purchase_tax_rate');

        if ($taxRate === null || $taxRate === '') {
            $taxRate = 16;
        }

        return '$ ' . number_format(((float) $cost) * (1 + ((float) $taxRate / 100)), 4);
    }



    protected static function priceCostLastChangeHtml(?\App\Models\Product $record): \Illuminate\Support\HtmlString
    {
        if (! $record || ! $record->getKey() || ! \Illuminate\Support\Facades\Schema::hasTable('product_price_cost_audits')) {
            return new \Illuminate\Support\HtmlString('<span style="color:#6b7280;">Sin cambios registrados.</span>');
        }

        $audit = \Illuminate\Support\Facades\DB::table('product_price_cost_audits')
            ->where('product_id', $record->getKey())
            ->orderByDesc('changed_at')
            ->first();

        if (! $audit) {
            return new \Illuminate\Support\HtmlString('<span style="color:#6b7280;">Sin cambios registrados.</span>');
        }

        $user = static::priceCostAuditUserLabel($audit->user_id ?? null);
        $date = $audit->changed_at ? \Carbon\Carbon::parse($audit->changed_at)->format('d/m/Y H:i') : '—';

        $html = '<div style="font-size:13px;line-height:1.35;">';
        $html .= '<strong>' . e($audit->field_label ?? $audit->field_name) . '</strong>';
        $html .= ' cambió de <strong>' . e($audit->old_value ?? '—') . '</strong>';
        $html .= ' a <strong>' . e($audit->new_value ?? '—') . '</strong>';
        $html .= '<br><span style="color:#6b7280;">' . e($date) . ' por ' . e($user) . '</span>';
        $html .= '</div>';

        return new \Illuminate\Support\HtmlString($html);
    }

    protected static function priceCostAuditHistoryHtml(?\App\Models\Product $record): \Illuminate\Support\HtmlString
    {
        if (! $record || ! $record->getKey() || ! \Illuminate\Support\Facades\Schema::hasTable('product_price_cost_audits')) {
            return new \Illuminate\Support\HtmlString('<span style="color:#6b7280;">Sin historial.</span>');
        }

        $rows = \Illuminate\Support\Facades\DB::table('product_price_cost_audits')
            ->where('product_id', $record->getKey())
            ->orderByDesc('changed_at')
            ->limit(8)
            ->get();

        if ($rows->isEmpty()) {
            return new \Illuminate\Support\HtmlString('<span style="color:#6b7280;">Sin historial.</span>');
        }

        $html = '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
        $html .= '<thead><tr>';
        $html .= '<th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:5px;">Fecha</th>';
        $html .= '<th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:5px;">Campo</th>';
        $html .= '<th style="text-align:right;border-bottom:1px solid #e5e7eb;padding:5px;">Antes</th>';
        $html .= '<th style="text-align:right;border-bottom:1px solid #e5e7eb;padding:5px;">Nuevo</th>';
        $html .= '<th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:5px;">Usuario</th>';
        $html .= '<th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:5px;">Origen</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $date = $row->changed_at ? \Carbon\Carbon::parse($row->changed_at)->format('d/m/Y H:i') : '—';
            $user = static::priceCostAuditUserLabel($row->user_id ?? null);

            $html .= '<tr>';
            $html .= '<td style="padding:5px;border-bottom:1px solid #f3f4f6;">' . e($date) . '</td>';
            $html .= '<td style="padding:5px;border-bottom:1px solid #f3f4f6;">' . e($row->field_label ?? $row->field_name) . '</td>';
            $html .= '<td style="padding:5px;border-bottom:1px solid #f3f4f6;text-align:right;">' . e($row->old_value ?? '—') . '</td>';
            $html .= '<td style="padding:5px;border-bottom:1px solid #f3f4f6;text-align:right;">' . e($row->new_value ?? '—') . '</td>';
            $html .= '<td style="padding:5px;border-bottom:1px solid #f3f4f6;">' . e($user) . '</td>';
            $html .= '<td style="padding:5px;border-bottom:1px solid #f3f4f6;">' . e($row->source ?? 'manual') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return new \Illuminate\Support\HtmlString($html);
    }

    protected static function priceCostAuditUserLabel($userId): string
    {
        if (! $userId || ! \Illuminate\Support\Facades\Schema::hasTable('users')) {
            return 'Sistema';
        }

        $user = \Illuminate\Support\Facades\DB::table('users')->where('id', $userId)->first();

        if (! $user) {
            return 'Usuario #' . $userId;
        }

        foreach (['name', 'email'] as $column) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', $column) && trim((string) ($user->{$column} ?? '')) !== '') {
                return (string) $user->{$column};
            }
        }

        return 'Usuario #' . $userId;
    }



    protected static function applyManualProductPriceCostChange(\App\Models\Product $record, string $fieldName, string $fieldLabel, float $newValue, string $reason): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('products', $fieldName)) {
            throw new \RuntimeException('El campo ' . $fieldName . ' no existe en productos.');
        }

        $oldValue = $record->{$fieldName};

        if (static::normalizeManualAuditValue($oldValue) === static::normalizeManualAuditValue($newValue)) {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($record, $fieldName, $fieldLabel, $oldValue, $newValue, $reason): void {
            \Illuminate\Support\Facades\DB::table('products')
                ->where('id', $record->getKey())
                ->update([
                    $fieldName => $newValue,
                    'updated_at' => now(),
                ]);

            if (! \Illuminate\Support\Facades\Schema::hasTable('product_price_cost_audits')) {
                return;
            }

            \Illuminate\Support\Facades\DB::table('product_price_cost_audits')->insert([
                'company_id' => static::manualAuditCompanyId($record),
                'product_id' => $record->getKey(),
                'user_id' => auth()->id(),
                'field_name' => $fieldName,
                'field_label' => $fieldLabel,
                'old_value' => static::manualAuditStringValue($oldValue),
                'new_value' => static::manualAuditStringValue($newValue),
                'old_numeric_value' => is_numeric($oldValue) ? (float) $oldValue : null,
                'new_numeric_value' => is_numeric($newValue) ? (float) $newValue : null,
                'source' => 'manual',
                'notes' => $reason,
                'product_reference' => static::manualAuditProductReference($record),
                'product_name' => (string) ($record->name ?? ''),
                'changed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    protected static function normalizeManualAuditValue($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 6, '.', '');
        }

        return trim((string) $value);
    }

    protected static function manualAuditStringValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value)
            ? number_format((float) $value, 6, '.', '')
            : (string) $value;
    }

    protected static function manualAuditCompanyId(\App\Models\Product $record): ?int
    {
        if (isset($record->company_id) && $record->company_id) {
            return (int) $record->company_id;
        }

        if (method_exists(static::class, 'currentCompanyIdForProducts')) {
            return static::currentCompanyIdForProducts();
        }

        $user = auth()->user();

        if ($user && isset($user->company_id)) {
            return (int) $user->company_id;
        }

        return null;
    }

    protected static function manualAuditProductReference(\App\Models\Product $record): ?string
    {
        foreach (['internal_reference', 'sku', 'barcode', 'code'] as $field) {
            if (isset($record->{$field}) && trim((string) $record->{$field}) !== '') {
                return (string) $record->{$field};
            }
        }

        return null;
    }




    protected static function favoriteSatUnitCodesForDropdowns(): array
    {
        return [
            'H87',
            'EA',
            'E48',
            'ACT',
            'KGM',
            'E51',
            'A9',
            'MTR',
            'AB',
            'BB',
            'KT',
            'SET',
            'LTR',
            'XBX',
            'MON',
            'HUR',
            'MTK',
            '11',
            'MGM',
            'XPK',
            'XKI',
            'AS',
            'GRM',
            'PR',
            'DPC',
            'XUN',
            'DAY',
            'XLT',
            '10',
            'MLT',
            'E54',
        ];
    }

    protected static function sortFavoriteUnitOptions(array $options): array
    {
        if ($options === []) {
            return $options;
        }

        $favoriteRank = array_flip(static::favoriteSatUnitCodesForDropdowns());
        $labels = $options;

        uksort($options, function ($keyA, $keyB) use ($labels, $favoriteRank): int {
            $labelA = (string) ($labels[$keyA] ?? '');
            $labelB = (string) ($labels[$keyB] ?? '');

            $codeA = static::unitCodeFromDropdownOption($keyA, $labelA);
            $codeB = static::unitCodeFromDropdownOption($keyB, $labelB);

            $rankA = $favoriteRank[$codeA] ?? 999999;
            $rankB = $favoriteRank[$codeB] ?? 999999;

            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            return strnatcasecmp($labelA, $labelB);
        });

        return $options;
    }

    protected static function unitCodeFromDropdownOption(int|string $key, string $label): string
    {
        $key = trim((string) $key);

        if (in_array($key, static::favoriteSatUnitCodesForDropdowns(), true)) {
            return $key;
        }

        $label = trim(strip_tags($label));

        if (preg_match('/^([A-Z0-9]+)\s*(?:-|–|—|\|)/u', $label, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^([A-Z0-9]{1,5})\b/u', $label, $matches)) {
            return $matches[1];
        }

        return $key;
    }

    public static function purchaseEquivalenceSatUnitOptions(string $search = ''): array
    {
        $source = static::purchaseEquivalenceSatUnitSource();

        if (! $source) {
            $options = [
                'H87' => 'H87 - Pieza',
                'EA' => 'EA - Elemento',
                'XBX' => 'XBX - Caja',
                'XPK' => 'XPK - Paquete',
                'DPC' => 'DPC - Docenas de piezas',
                'KGM' => 'KGM - Kilogramo',
                'LTR' => 'LTR - Litro',
                'MTR' => 'MTR - Metro',
                'E48' => 'E48 - Unidad de servicio',
            ];
        }

        $table = $source['table'];
        $keyColumn = $source['key'];
        $nameColumn = $source['name'];
        $activeColumn = $source['active'];

        $query = \Illuminate\Support\Facades\DB::table($table);

        if ($activeColumn) {
            $query->where($activeColumn, true);
        }

        $search = trim($search);

        if ($search !== '') {
            $query->where(function ($query) use ($keyColumn, $nameColumn, $search): void {
                $query->whereRaw('CAST(' . $keyColumn . ' AS TEXT) ILIKE ?', ['%' . $search . '%'])
                    ->orWhereRaw('CAST(' . $nameColumn . ' AS TEXT) ILIKE ?', ['%' . $search . '%']);
            });
        }

        return $query
            ->orderBy($keyColumn)
            ->limit(100)
            ->get()
            ->mapWithKeys(fn ($unit): array => [
                (string) $unit->{$keyColumn} => (string) $unit->{$keyColumn} . ' - ' . (string) $unit->{$nameColumn},
            ])
            ->all();
            return static::sortFavoriteUnitOptions($options);
    }

    public static function purchaseEquivalenceSatUnitLabel(?string $key): ?string
    {
        $unit = static::purchaseEquivalenceSatUnitRow($key);

        return $unit
            ? $unit['key'] . ' - ' . $unit['name']
            : $key;
    }

    public static function purchaseEquivalenceSatUnitRow(?string $key): ?array
    {
        if (! $key) {
            return null;
        }

        $source = static::purchaseEquivalenceSatUnitSource();

        if (! $source) {
            $fallback = [
                'H87' => 'Pieza',
                'EA' => 'Elemento',
                'XBX' => 'Caja',
                'XPK' => 'Paquete',
                'DPC' => 'Docenas de piezas',
                'KGM' => 'Kilogramo',
                'LTR' => 'Litro',
                'MTR' => 'Metro',
                'E48' => 'Unidad de servicio',
            ];

            return isset($fallback[$key])
                ? ['key' => $key, 'name' => $fallback[$key]]
                : null;
        }

        $row = \Illuminate\Support\Facades\DB::table($source['table'])
            ->where($source['key'], $key)
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'key' => (string) $row->{$source['key']},
            'name' => (string) $row->{$source['name']},
        ];
    }

    public static function purchaseEquivalenceSatUnitSource(): ?array
    {
        $preferredTables = [
            'sat_units',
            'sat_unit_catalogs',
            'sat_clave_unidades',
            'sat_clave_unidad',
            'sat_catalog_units',
            'catalog_sat_units',
            'cfdi_units',
            'cfdi_sat_units',
            'units_sat',
            'sat_unit_codes',
        ];

        try {
            $dynamicTables = \Illuminate\Support\Facades\DB::table('information_schema.tables')
                ->whereRaw('table_schema = current_schema()')
                ->where(function ($query): void {
                    $query->where('table_name', 'ilike', '%sat%unit%')
                        ->orWhere('table_name', 'ilike', '%unidad%sat%')
                        ->orWhere('table_name', 'ilike', '%clave%unidad%')
                        ->orWhere('table_name', 'ilike', '%cfdi%unit%')
                        ->orWhere('table_name', 'ilike', '%unidad%');
                })
                ->pluck('table_name');
        } catch (\Throwable $e) {
            $dynamicTables = collect();
        }

        $tables = collect($preferredTables)
            ->merge($dynamicTables)
            ->unique()
            ->values();

        $keyCandidates = [
            'key',
            'clave',
            'clave_sat',
            'sat_key',
            'code',
            'codigo',
            'c_claveunidad',
            'clave_unidad',
            'unidad',
        ];

        $nameCandidates = [
            'name',
            'nombre',
            'description',
            'descripcion',
            'label',
        ];

        $activeCandidates = [
            'is_active',
            'active',
            'activo',
        ];

        $best = null;
        $bestCount = -1;

        foreach ($tables as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
            $columnsLower = array_map('strtolower', $columns);

            $keyColumn = null;

            foreach ($keyCandidates as $candidate) {
                $index = array_search(strtolower($candidate), $columnsLower, true);

                if ($index !== false) {
                    $keyColumn = $columns[$index];
                    break;
                }
            }

            $nameColumn = null;

            foreach ($nameCandidates as $candidate) {
                $index = array_search(strtolower($candidate), $columnsLower, true);

                if ($index !== false) {
                    $nameColumn = $columns[$index];
                    break;
                }
            }

            if (! $keyColumn || ! $nameColumn) {
                continue;
            }

            $activeColumn = null;

            foreach ($activeCandidates as $candidate) {
                $index = array_search(strtolower($candidate), $columnsLower, true);

                if ($index !== false) {
                    $activeColumn = $columns[$index];
                    break;
                }
            }

            try {
                $count = \Illuminate\Support\Facades\DB::table($table)->count();
            } catch (\Throwable $e) {
                $count = 0;
            }

            if ($count > $bestCount) {
                $best = [
                    'table' => $table,
                    'key' => $keyColumn,
                    'name' => $nameColumn,
                    'active' => $activeColumn,
                ];

                $bestCount = $count;
            }
        }

        return $best;
    }



    // BEXIA_V5727N28A_UNIQUE_CODES_ACTIVE_ONLY_HELPER
    public static function bexiaN28aValidateProductCodesUniqueAmongActive(array $data, ?int $ignoreProductId = null, ?int $companyId = null): array
    {
        $companyId = $companyId ?: (int) ($data['company_id'] ?? 0);

        if (! $companyId) {
            $tenant = \Filament\Facades\Filament::getTenant();

            if (is_object($tenant) && method_exists($tenant, 'getKey')) {
                $companyId = (int) $tenant->getKey();
            } elseif (is_numeric($tenant)) {
                $companyId = (int) $tenant;
            }
        }

        foreach (['sku', 'barcode', 'internal_reference'] as $field) {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('products', $field)) {
                continue;
            }

            $value = trim((string) ($data[$field] ?? ''));

            if ($value === '') {
                $data[$field] = null;
                continue;
            }

            $data[$field] = $value;

            if (! $companyId) {
                continue;
            }

            $query = \Illuminate\Support\Facades\DB::table('products')
                ->where('company_id', $companyId)
                ->where($field, $value);

            if ($ignoreProductId) {
                $query->where('id', '<>', $ignoreProductId);
            }

            static::bexiaN28aApplyActiveOnlyFilter($query);

            if ($query->exists()) {
                $label = match ($field) {
                    'barcode' => 'Código de barras',
                    'internal_reference' => 'Referencia interna',
                    default => 'SKU',
                };

                $message = $label . ' ya existe en otro producto o variante activa de esta empresa. Si el producto anterior está archivado, ya no debe bloquear este código.';

                throw \Illuminate\Validation\ValidationException::withMessages([
                    $field => $message,
                    'data.' . $field => $message,
                ]);
            }
        }

        return $data;
    }

    public static function bexiaN28aApplyActiveOnlyFilter($query): void
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'active')) {
            $query->where(function ($q) {
                $q->whereNull('active')->orWhere('active', true);
            });

            return;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'is_active')) {
            $query->where(function ($q) {
                $q->whereNull('is_active')->orWhere('is_active', true);
            });
        }
    }


}
