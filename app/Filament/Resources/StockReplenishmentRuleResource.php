<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockReplenishmentRuleResource\Pages;
use App\Models\StockReplenishmentRule;
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

class StockReplenishmentRuleResource extends Resource
{
    protected static ?string $model = StockReplenishmentRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Reglas de reabastecimiento';

    protected static ?string $modelLabel = 'regla de reabastecimiento';

    protected static ?string $pluralModelLabel = 'reglas de reabastecimiento';

    protected static ?int $navigationSort = 100;

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        $query = StockReplenishmentRule::query()
            ->with(['warehouse', 'location', 'product', 'productVariant']);

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
        }

        return $query;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.stockreplenishmentruleresource',
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


    /*
     * BEXIA_STOCK_REPLENISHMENT_RULE_RESOURCE_RESPONSIVE_V5_79_39C
     * Visual-only responsive marker.
     */

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => static::currentCompanyId()),

                Forms\Components\Section::make('Regla de reabastecimiento')
                    ->extraAttributes(['class' => 'bexia-stock-replenishment-rule-section bexia-stock-replenishment-rule-section-main'])
                    ->description('Define el mínimo, máximo y prioridad por almacén, ubicación, producto y variante.')
                    ->schema([
                        Forms\Components\Select::make('warehouse_id')
                            ->extraAttributes(['class' => 'bexia-stock-replenishment-rule-field bexia-stock-replenishment-rule-field-warehouse'])
                            ->label('Almacén')
                            ->options(fn (): array => static::warehouseOptions())
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('location_id', null);
                            })
                            ->columnSpan(6),

                        Forms\Components\Select::make('location_id')
                            ->extraAttributes(['class' => 'bexia-stock-replenishment-rule-field bexia-stock-replenishment-rule-field-location'])
                            ->label('Ubicación')
                            ->options(fn (Forms\Get $get): array => static::locationOptions($get('warehouse_id')))
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->helperText('Solo se muestran ubicaciones internas físicas del almacén.')
                            ->columnSpan(6),

                        Forms\Components\Select::make('product_id')
                            ->extraAttributes(['class' => 'bexia-stock-replenishment-rule-field bexia-stock-replenishment-rule-field-item'])
                            ->label('Producto')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => static::productSearchOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => static::productLabel($value))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('product_variant_id', null);
                            })
                            ->columnSpan(6),

                        Forms\Components\Select::make('product_variant_id')
                            ->extraAttributes(['class' => 'bexia-stock-replenishment-rule-field bexia-stock-replenishment-rule-field-variant'])
                            ->label('Variante')
                            ->options(fn (Forms\Get $get): array => static::variantOptions($get('product_id')))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Sin variante')
                            ->helperText('Si el producto tiene variantes, crea una regla específica por variante.')
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('min_quantity')
                            ->extraAttributes(['class' => 'bexia-stock-replenishment-rule-field bexia-stock-replenishment-rule-field-floor'])
                            ->label('Cantidad mínima')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.000001')
                            ->default(0)
                            ->required()
                            ->helperText('Cuando el disponible sea menor o igual a este valor, aparecerá en el reporte de reabastecimiento.')
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('max_quantity')
                            ->extraAttributes(['class' => 'bexia-stock-replenishment-rule-field bexia-stock-replenishment-rule-field-ceiling'])
                            ->label('Cantidad máxima')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.000001')
                            ->default(0)
                            ->required()
                            ->helperText('El reporte sugerirá comprar hasta llegar a este máximo.')
                            ->columnSpan(6),

                        Forms\Components\Placeholder::make('current_stock')
                            ->extraAttributes(['class' => 'bexia-stock-replenishment-rule-field bexia-stock-replenishment-rule-field-onhand'])
                            ->label('Disponible actual')
                            ->content(fn (Forms\Get $get): string => static::currentStockLabel($get))
                            ->columnSpan(6),

                        Forms\Components\Placeholder::make('purchase_info')
                            ->extraAttributes(['class' => 'bexia-stock-replenishment-rule-field bexia-stock-replenishment-rule-field-buy-info'])
                            ->label('Datos de compra')
                            ->content(fn (Forms\Get $get): string => static::purchaseInfoLabel($get('product_id'), $get('product_variant_id')))
                            ->columnSpan(6),

                        Forms\Components\Select::make('priority')
                            ->extraAttributes(['class' => 'bexia-stock-replenishment-rule-field bexia-stock-replenishment-rule-field-priority'])
                            ->label('Prioridad')
                            ->options([
                                'low' => 'Baja',
                                'normal' => 'Normal',
                                'high' => 'Alta',
                                'critical' => 'Crítica',
                            ])
                            ->default('normal')
                            ->native(false)
                            ->required()
                            ->columnSpan(3),
                        Forms\Components\Select::make('preferred_supplier_id')
                            ->extraAttributes(['class' => 'bexia-stock-replenishment-rule-field bexia-stock-replenishment-rule-field-supplier'])
                            ->label('Proveedor preferido')
                            ->options(fn (): array => static::supplierOptions())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Sin proveedor preferido')
                            ->helperText('Si se define, la lista sugerida de compra usará este proveedor antes que el proveedor del producto.')
                            ->columnSpan(5),


                        Forms\Components\TextInput::make('lead_time_days')
                            ->extraAttributes(['class' => 'bexia-stock-replenishment-rule-field bexia-stock-replenishment-rule-field-lead-days'])
                            ->label('Días de entrega')
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->placeholder('Ej. 7')
                            ->suffix('días')
                            ->helperText('Plazo estimado del proveedor para esta regla. Dejar vacío si no aplica.')
                            ->columnSpan(4),

                        Forms\Components\Toggle::make('is_active')
                            ->extraAttributes(['class' => 'bexia-stock-replenishment-rule-field bexia-stock-replenishment-rule-field-enabled'])
                            ->label('Activa')
                            ->default(true)
                            ->columnSpan(6),

                        Forms\Components\Textarea::make('notes')
                            ->extraAttributes(['class' => 'bexia-stock-replenishment-rule-field bexia-stock-replenishment-rule-field-notes'])
                            ->label('Notas')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(12),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->extraHeaderAttributes(['class' => 'bexia-stock-replenishment-rule-col-warehouse'])
                    ->extraCellAttributes(['class' => 'bexia-stock-replenishment-rule-col-warehouse'])
                    ->label('Almacén')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('location.name')
                    ->extraHeaderAttributes(['class' => 'bexia-stock-replenishment-rule-col-location'])
                    ->extraCellAttributes(['class' => 'bexia-stock-replenishment-rule-col-location'])
                    ->label('Ubicación')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_display')
                    ->extraHeaderAttributes(['class' => 'bexia-stock-replenishment-rule-col-item bexia-stock-replenishment-rule-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-stock-replenishment-rule-col-item bexia-stock-replenishment-rule-col-primary'])
                    ->label('Producto')
                    ->state(fn (StockReplenishmentRule $record): string => static::productLabel($record->product_id))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('product', function (Builder $query) use ($search): void {
                            $query->where('name', 'ilike', '%' . $search . '%');

                            foreach (['internal_reference', 'sku', 'barcode', 'code'] as $column) {
                                if (Schema::hasColumn('products', $column)) {
                                    $query->orWhere($column, 'ilike', '%' . $search . '%');
                                }
                            }
                        });
                    }),

                Tables\Columns\TextColumn::make('variant_display')
                    ->extraHeaderAttributes(['class' => 'bexia-stock-replenishment-rule-col-variant'])
                    ->extraCellAttributes(['class' => 'bexia-stock-replenishment-rule-col-variant'])
                    ->label('Variante')
                    ->state(fn (StockReplenishmentRule $record): string => $record->product_variant_id ? static::variantLabel($record->product_variant_id) : '—'),

                Tables\Columns\TextColumn::make('min_quantity')
                    ->extraHeaderAttributes(['class' => 'bexia-stock-replenishment-rule-col-floor bexia-stock-replenishment-rule-col-number'])
                    ->extraCellAttributes(['class' => 'bexia-stock-replenishment-rule-col-floor bexia-stock-replenishment-rule-col-number'])
                    ->label('Mínimo')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('max_quantity')
                    ->extraHeaderAttributes(['class' => 'bexia-stock-replenishment-rule-col-ceiling bexia-stock-replenishment-rule-col-number'])
                    ->extraCellAttributes(['class' => 'bexia-stock-replenishment-rule-col-ceiling bexia-stock-replenishment-rule-col-number'])
                    ->label('Máximo')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_stock')
                    ->extraHeaderAttributes(['class' => 'bexia-stock-replenishment-rule-col-onhand bexia-stock-replenishment-rule-col-number'])
                    ->extraCellAttributes(['class' => 'bexia-stock-replenishment-rule-col-onhand bexia-stock-replenishment-rule-col-number'])
                    ->label('Disponible')
                    ->state(fn (StockReplenishmentRule $record): string => number_format(static::currentQuantity(
                        (int) $record->company_id,
                        (int) $record->warehouse_id,
                        (int) $record->location_id,
                        (int) $record->product_id,
                        $record->product_variant_id ? (int) $record->product_variant_id : null,
                    ), 2)),

                
                Tables\Columns\TextColumn::make('preferred_supplier_display')
                    ->extraHeaderAttributes(['class' => 'bexia-stock-replenishment-rule-col-supplier'])
                    ->extraCellAttributes(['class' => 'bexia-stock-replenishment-rule-col-supplier'])
                    ->label('Proveedor')
                    ->state(fn (StockReplenishmentRule $record): string => static::supplierLabel($record->preferred_supplier_id))
                    ->searchable(false)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('lead_time_days')
                    ->extraHeaderAttributes(['class' => 'bexia-stock-replenishment-rule-col-lead-days'])
                    ->extraCellAttributes(['class' => 'bexia-stock-replenishment-rule-col-lead-days'])
                    ->label('Entrega')
                    ->formatStateUsing(fn ($state): string => $state ? $state . ' días' : '—')
                    ->sortable()
                    ->toggleable(),Tables\Columns\TextColumn::make('priority')
                        ->extraHeaderAttributes(['class' => 'bexia-stock-replenishment-rule-col-priority'])
                        ->extraCellAttributes(['class' => 'bexia-stock-replenishment-rule-col-priority'])
                    ->label('Prioridad')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'low' => 'Baja',
                        'high' => 'Alta',
                        'critical' => 'Crítica',
                        default => 'Normal',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'low' => 'gray',
                        default => 'success',
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes(['class' => 'bexia-stock-replenishment-rule-col-enabled'])
                    ->extraCellAttributes(['class' => 'bexia-stock-replenishment-rule-col-enabled'])
                    ->label('Activa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->extraHeaderAttributes(['class' => 'bexia-stock-replenishment-rule-col-updated'])
                    ->extraCellAttributes(['class' => 'bexia-stock-replenishment-rule-col-updated'])
                    ->label('Actualizada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activas')
                    ->placeholder('Todas')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),

                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Almacén')
                    ->options(fn (): array => static::warehouseOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn (): bool => static::userCanManage()),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Eliminar seleccionadas')
                    ->visible(fn (): bool => static::userCanManage()),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockReplenishmentRules::route('/'),
            'create' => Pages\CreateStockReplenishmentRule::route('/create'),
            'edit' => Pages\EditStockReplenishmentRule::route('/{record}/edit'),
        ];
    }

    protected static function warehouseOptions(): array
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

    protected static function locationOptions($warehouseId): array
    {
        if (! $warehouseId || ! Schema::hasTable('stock_locations')) {
            return [];
        }

        $query = DB::table('stock_locations')
            ->leftJoin('stock_location_types', 'stock_location_types.id', '=', 'stock_locations.stock_location_type_id')
            ->where('stock_locations.warehouse_id', $warehouseId);

        if (Schema::hasColumn('stock_locations', 'is_active')) {
            $query->where('stock_locations.is_active', true);
        }

        $query->where(function ($query): void {
            $query
                ->where('stock_location_types.is_internal', true)
                ->orWhereNull('stock_location_types.id');
        });

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('stock_locations', 'company_id')) {
            $query->where('stock_locations.company_id', $companyId);
        } elseif (Schema::hasColumn('stock_locations', 'company_id')) {
            $query->whereNull('stock_locations.company_id');
        }

        return $query
            ->orderBy('stock_locations.name')
            ->get([
                'stock_locations.id',
                'stock_locations.code',
                'stock_locations.name',
            ])
            ->mapWithKeys(fn ($location): array => [
                $location->id => trim(($location->code ? $location->code . ' - ' : '') . $location->name),
            ])
            ->all();
    }

    protected static function productSearchOptions(string $search): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $query = DB::table('products');

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('products', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('products', 'is_variant')) {
            $query->where(function ($query): void {
                $query
                    ->where('is_variant', false)
                    ->orWhereNull('is_variant');
            });
        } elseif (Schema::hasColumn('products', 'parent_product_id')) {
            $query->whereNull('parent_product_id');
        }

        $search = trim($search);

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $query->where(function ($query) use ($like): void {
                foreach (['name', 'description', 'sku', 'internal_reference', 'reference', 'code', 'barcode'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $query->orWhere($column, 'ilike', $like);
                    }
                }
            });
        }

        return $query
            ->orderBy(Schema::hasColumn('products', 'name') ? 'name' : 'id')
            ->limit(50)
            ->get(['id'])
            ->mapWithKeys(fn ($row): array => [
                $row->id => static::productLabel($row->id),
            ])
            ->all();
    }

    protected static function variantOptions($productId): array
    {
        if (! $productId || ! Schema::hasTable('products') || ! Schema::hasColumn('products', 'parent_product_id')) {
            return [];
        }

        $query = DB::table('products')
            ->where('parent_product_id', (int) $productId);

        if (Schema::hasColumn('products', 'is_variant')) {
            $query->where('is_variant', true);
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('products', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->orderBy(Schema::hasColumn('products', 'variant_value') ? 'variant_value' : 'name')
            ->limit(300)
            ->get(['id'])
            ->mapWithKeys(fn ($row): array => [
                $row->id => static::variantLabel($row->id),
            ])
            ->all();
    }

    protected static function productLabel($productId): string
    {
        return static::labelFromProducts($productId, false);
    }

    protected static function variantLabel($variantId): string
    {
        return static::labelFromProducts($variantId, true);
    }

    protected static function labelFromProducts($id, bool $variant = false): string
    {
        if (! $id || ! Schema::hasTable('products')) {
            return '—';
        }

        $row = DB::table('products')->where('id', $id)->first();

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
            $variantText = '';

            if ($group !== '' && $value !== '') {
                $variantText = $group . ': ' . $value;
            } elseif ($value !== '') {
                $variantText = $value;
            } elseif (Schema::hasColumn('products', 'name')) {
                $variantText = trim((string) ($row->name ?? ''));
            }

            if ($reference !== '' && $variantText !== '') {
                return $reference . ' - ' . $variantText;
            }

            return $variantText ?: ($reference ?: ('Variante #' . $id));
        }

        $name = Schema::hasColumn('products', 'name') ? trim((string) ($row->name ?? '')) : '';

        if ($reference !== '' && $name !== '') {
            return $reference . ' - ' . $name;
        }

        return $name ?: ($reference ?: ('Producto #' . $id));
    }

    protected static function currentStockLabel(Forms\Get $get): string
    {
        $companyId = static::currentCompanyId();
        $warehouseId = $get('warehouse_id');
        $locationId = $get('location_id');
        $productId = $get('product_id');
        $variantId = $get('product_variant_id');

        if (! $companyId || ! $warehouseId || ! $locationId || ! $productId) {
            return 'Selecciona almacén, ubicación y producto.';
        }

        return number_format(static::currentQuantity(
            (int) $companyId,
            (int) $warehouseId,
            (int) $locationId,
            (int) $productId,
            $variantId ? (int) $variantId : null,
        ), 2);
    }

    protected static function currentQuantity(int $companyId, int $warehouseId, int $locationId, int $productId, ?int $variantId): float
    {
        if (! Schema::hasTable('stock_quants')) {
            return 0;
        }

        $query = DB::table('stock_quants')
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId);

        if (Schema::hasColumn('stock_quants', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $variantId
            ? $query->where('product_variant_id', $variantId)
            : $query->whereNull('product_variant_id');

        return (float) $query->sum('quantity');
    }

    protected static function purchaseInfoLabel($productId, $variantId = null): string
    {
        if (! $productId || ! Schema::hasTable('products')) {
            return 'Selecciona un producto.';
        }

        $row = DB::table('products')
            ->where('id', $variantId ?: $productId)
            ->first();

        if (! $row && $variantId) {
            $row = DB::table('products')->where('id', $productId)->first();
        }

        if (! $row) {
            return 'Sin datos de compra.';
        }

        $pack = Schema::hasColumn('products', 'purchase_pack_units') ? ($row->purchase_pack_units ?? null) : null;
        $min = Schema::hasColumn('products', 'purchase_min_quantity') ? ($row->purchase_min_quantity ?? null) : null;
        $multiple = Schema::hasColumn('products', 'purchase_multiple_quantity') ? ($row->purchase_multiple_quantity ?? null) : null;

        return 'UXES: ' . ($pack ?: '1')
            . ' | Compra mínima: ' . ($min ?: '—')
            . ' | Múltiplo: ' . ($multiple ?: ($pack ?: '1'));
    }


    protected static function supplierOptions(): array
    {
        $companyId = static::currentCompanyId();

        foreach (['suppliers', 'vendors', 'contacts'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table);

            if (Schema::hasColumn($table, 'company_id') && $companyId) {
                $query->where(function ($query) use ($companyId, $table): void {
                    $query
                        ->where($table . '.company_id', $companyId)
                        ->orWhereNull($table . '.company_id');
                });
            }

            if (Schema::hasColumn($table, 'is_active')) {
                $query->where($table . '.is_active', true);
            }

            if ($table === 'contacts') {
                $query->where(function ($query) use ($table): void {
                    foreach ([
                        'is_supplier',
                        'is_vendor',
                        'supplier',
                        'vendor',
                    ] as $column) {
                        if (Schema::hasColumn($table, $column)) {
                            $query->orWhere($table . '.' . $column, true);
                        }
                    }

                    foreach ([
                        'contact_type',
                        'type',
                        'category',
                    ] as $column) {
                        if (Schema::hasColumn($table, $column)) {
                            $query->orWhereIn($table . '.' . $column, [
                                'supplier',
                                'vendor',
                                'proveedor',
                            ]);
                        }
                    }
                });
            }

            $labelColumn = static::firstExistingColumn($table, [
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

    protected static function supplierLabel($supplierId): string
    {
        if (! $supplierId) {
            return 'Sin proveedor';
        }

        foreach (['suppliers', 'vendors', 'contacts'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $labelColumn = static::firstExistingColumn($table, [
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
                ->first();

            if ($row) {
                return (string) ($row->{$labelColumn} ?? ('Proveedor #' . $supplierId));
            }
        }

        return 'Proveedor #' . $supplierId;
    }

    protected static function firstExistingColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
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

public static function canCreate(): bool
    {
        return static::userCanManage();
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCanManage();
    }

public static function canDelete(Model $record): bool
{
    return static::userCanManage();
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
        ])
    ) {
        return true;
    }

    return method_exists($user, 'can')
        ? $user->can('inventory.view_replenishment_rules') || $user->can('inventory.view')
        : false;
}

protected static function userCanManage(): bool
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
        ])
    ) {
        return true;
    }

    return method_exists($user, 'can')
        ? $user->can('inventory.manage_replenishment_rules')
        : false;
}
}