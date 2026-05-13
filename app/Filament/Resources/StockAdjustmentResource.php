<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAdjustmentResource\Pages;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\StockQuant;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockAdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustment::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Ajustes de inventario';

    protected static ?string $modelLabel = 'ajuste de inventario';

    protected static ?string $pluralModelLabel = 'ajustes de inventario';

    protected static ?int $navigationSort = 60;

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        $query = StockAdjustment::query()
            ->withCount('lines');

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
                    ->default(fn (): ?int => static::currentCompanyId()),

                Forms\Components\Section::make('Ajuste')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Referencia')
                            ->placeholder('Se genera automáticamente al guardar')
                            ->helperText('Formato: UBICACION/AJU/000001. Ej. STOCK/AJU/000001.')
                            ->maxLength(80)
                            ->readOnly()
                            ->dehydrated(true)
                            ->columnSpan(3),

                        Forms\Components\DateTimePicker::make('adjustment_at')
                            ->label('Fecha y hora')
                            ->default(now())
                            ->required()
                            ->seconds(false)
                            ->displayFormat('d/m/Y H:i')
                            ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                            ->columnSpan(3),

                        Forms\Components\Select::make('warehouse_id')
                            ->label('Almacén')
                            ->options(fn (): array => static::warehouseOptions())
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('location_id', null);
                            })
                            ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                            ->columnSpan(3),

                        Forms\Components\Select::make('location_id')
                            ->label('Ubicación')
                            ->options(fn (Forms\Get $get): array => static::locationOptions($get('warehouse_id')))
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                            ->columnSpan(3),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'draft' => 'Borrador',
                                'done' => 'Hecho',
                                'cancelled' => 'Cancelado',
                            ])
                            ->default('draft')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(3),

                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo')
                            ->placeholder('Ej. Inventario inicial, conteo físico, corrección por diferencia.')
                            ->rows(2)
                            ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                            ->columnSpan(9),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(2)
                            ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                            ->columnSpanFull(),
                    ])
                    ->columns(12),

                Forms\Components\Section::make('Productos contados')
                    ->description('Captura la cantidad física contada. Al confirmar, Bexia actualizará la existencia en la ubicación seleccionada.')
                    ->schema([
                        Forms\Components\Repeater::make('lines')
                            ->label('Líneas')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Producto')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search): array => static::productSearchOptions($search))
                                    ->getOptionLabelUsing(fn ($value): ?string => static::productLabel($value))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                                        $set('product_variant_id', null);
                                        static::refreshAdjustmentLineComputedFields($set, $get);
                                    })
                                    ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                                    ->columnSpan(4),

                                Forms\Components\Select::make('product_variant_id')
                                    ->label('Variante')
                                    ->options(fn (Forms\Get $get): array => static::variantOptions($get('product_id')))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('Sin variante')
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                                        static::refreshAdjustmentLineComputedFields($set, $get);
                                    })
                                    ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('counted_quantity')
                                    ->label('Cantidad contada')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                                        static::refreshAdjustmentLineComputedFields($set, $get);
                                    })
                                    ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                                    ->columnSpan(2),

                                Forms\Components\Hidden::make('current_quantity')
                                    ->default(0)
                                    ->dehydrated(true),

                                Forms\Components\Hidden::make('difference_quantity')
                                    ->default(0)
                                    ->dehydrated(true),

                                Forms\Components\Hidden::make('unit_cost')
                                    ->default(0)
                                    ->dehydrated(true),

                                Forms\Components\Placeholder::make('current_quantity_info')
                                    ->label('Cantidad actual')
                                    ->content(fn (Forms\Get $get): string => number_format((float) ($get('current_quantity') ?: 0), 2))
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('difference_quantity_info')
                                    ->label('Diferencia')
                                    ->content(fn (Forms\Get $get): string => number_format((float) ($get('difference_quantity') ?: 0), 2))
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('unit_cost_info')
                                    ->label('Costo prom.')
                                    ->content(fn (Forms\Get $get): string => '$ ' . number_format((float) ($get('unit_cost') ?: 0), 2))
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Notas')
                                    ->rows(1)
                                    ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                                    ->columnSpanFull(),
                            ])
                            ->columns(12)
                            ->defaultItems(1)
                            ->addActionLabel('Agregar producto')
                            ->reorderable(false)
                            ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Referencia')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('adjustment_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse_label')
                    ->label('Almacén')
                    ->state(fn (StockAdjustment $record): string => static::warehouseLabel($record->warehouse_id))
                    ->searchable(false),

                Tables\Columns\TextColumn::make('location_label')
                    ->label('Ubicación')
                    ->state(fn (StockAdjustment $record): string => static::locationLabel($record->location_id))
                    ->searchable(false),

                Tables\Columns\TextColumn::make('lines_count')
                    ->label('Líneas')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'Borrador',
                        'done' => 'Hecho',
                        'cancelled' => 'Cancelado',
                        default => (string) $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(45)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'done' => 'Hecho',
                        'cancelled' => 'Cancelado',
                    ]),

                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Almacén')
                    ->options(fn (): array => static::warehouseOptions()),

                Tables\Filters\SelectFilter::make('location_id')
                    ->label('Ubicación')
                    ->options(fn (): array => static::allLocationOptions()),
            ])
            ->actions([

                Tables\Actions\Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar ajuste de inventario')
                    ->modalDescription('Al confirmar, se actualizarán las existencias. Esta acción no debe hacerse si aún estás revisando las cantidades.')
                    ->modalSubmitActionLabel('Confirmar ajuste')
                    ->visible(fn (StockAdjustment $record): bool => $record->status === 'draft')
                    ->action(function (StockAdjustment $record): void {
                        static::confirmAdjustment($record);

                        Notification::make()
                            ->title('Ajuste confirmado')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAdjustments::route('/'),
            'create' => Pages\CreateStockAdjustment::route('/create'),
            'edit' => Pages\EditStockAdjustment::route('/{record}/edit'),
        ];
    }

    public static function confirmAdjustment(StockAdjustment $adjustment): void
    {
        if ($adjustment->status !== 'draft') {
            return;
        }

        $adjustment->load('lines');

        if ($adjustment->lines->isEmpty()) {
            \Filament\Notifications\Notification::make()
                ->title('No se puede confirmar')
                ->body('El ajuste no tiene productos capturados.')
                ->danger()
                ->send();

            throw new \Filament\Support\Exceptions\Halt();
        }

        DB::transaction(function () use ($adjustment): void {
            foreach ($adjustment->lines as $line) {
                if (! $line->product_id) {
                    continue;
                }

                $current = static::currentQuantity(
                    (int) $adjustment->company_id,
                    (int) $adjustment->warehouse_id,
                    (int) $adjustment->location_id,
                    (int) $line->product_id,
                    $line->product_variant_id ? (int) $line->product_variant_id : null
                );

                $counted = (float) $line->counted_quantity;

                if ($counted < 0 && ! static::locationAllowsNegativeStock((int) $adjustment->location_id)) {
                    \Filament\Notifications\Notification::make()
                        ->title('No se permite existencia negativa')
                        ->body('La ubicación seleccionada no permite cantidades negativas. Activa "Permitir existencias negativas" en la ubicación o captura una cantidad mayor o igual a cero.')
                        ->danger()
                        ->send();

                    throw new \Filament\Support\Exceptions\Halt();
                }

                $difference = $counted - $current;

                $averageCost = static::averageCost(
                    (int) $adjustment->company_id,
                    (int) $adjustment->warehouse_id,
                    (int) $adjustment->location_id,
                    (int) $line->product_id,
                    $line->product_variant_id ? (int) $line->product_variant_id : null
                );

                $unitCost = $averageCost;

                if ($unitCost === null && $line->unit_cost !== null) {
                    $unitCost = (float) $line->unit_cost;
                }

                $line->update([
                    'current_quantity' => $current,
                    'difference_quantity' => $difference,
                    'unit_cost' => $unitCost,
                ]);

                static::setQuantQuantity(
                    (int) $adjustment->company_id,
                    (int) $adjustment->warehouse_id,
                    (int) $adjustment->location_id,
                    (int) $line->product_id,
                    $line->product_variant_id ? (int) $line->product_variant_id : null,
                    $counted,
                    $unitCost
                );
            }

            $adjustment->update([
                'status' => 'done',
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
            ]);
        });
    }








    public static function assertAdjustmentLinesCanBeSaved(?int $locationId, array $lines): void
    {
        if (! $locationId || static::locationAllowsNegativeStock($locationId)) {
            return;
        }

        foreach ($lines as $line) {
            $counted = (float) ($line['counted_quantity'] ?? 0);

            if ($counted < 0) {
                \Filament\Notifications\Notification::make()
                    ->title('No se puede guardar el borrador')
                    ->body('La ubicación seleccionada no permite cantidades negativas. Activa "Permitir existencias negativas" en la ubicación o captura una cantidad mayor o igual a cero.')
                    ->danger()
                    ->send();

                throw new \Filament\Support\Exceptions\Halt();
            }
        }
    }

    protected static function adjustmentIsDoneFromForm(Forms\Get $get): bool
    {
        $status = $get('status') ?: $get('../../status');

        return $status === 'done';
    }

    protected static function refreshAdjustmentLineComputedFields(Forms\Set $set, Forms\Get $get): void
    {
        $productId = $get('product_id');
        $variantId = $get('product_variant_id');

        $warehouseId = $get('../../warehouse_id') ?: $get('warehouse_id');
        $locationId = $get('../../location_id') ?: $get('location_id');

        $companyId = static::currentCompanyId();

        $current = 0.0;
        $averageCost = 0.0;

        if ($companyId && $warehouseId && $locationId && $productId) {
            $current = (float) static::currentQuantity(
                (int) $companyId,
                (int) $warehouseId,
                (int) $locationId,
                (int) $productId,
                $variantId ? (int) $variantId : null
            );

            $average = static::averageCost(
                (int) $companyId,
                (int) $warehouseId,
                (int) $locationId,
                (int) $productId,
                $variantId ? (int) $variantId : null
            );

            $averageCost = $average !== null ? (float) $average : 0.0;
        }

        $counted = (float) ($get('counted_quantity') ?: 0);
        $difference = $counted - $current;

        $set('current_quantity', round($current, 2));
        $set('difference_quantity', round($difference, 2));
        $set('unit_cost', round($averageCost, 2));
    }



    protected static function averageCost(int $companyId, int $warehouseId, int $locationId, int $productId, ?int $variantId = null): ?float
    {
        if (Schema::hasTable('stock_quants') && Schema::hasColumn('stock_quants', 'average_cost')) {
            $query = DB::table('stock_quants')
                ->where('company_id', $companyId)
                ->where('warehouse_id', $warehouseId)
                ->where('location_id', $locationId)
                ->where('product_id', $productId);

            if ($variantId) {
                $query->where('product_variant_id', $variantId);
            } else {
                $query->whereNull('product_variant_id');
            }

            $value = $query->value('average_cost');

            if ($value !== null) {
                return (float) $value;
            }
        }

        // Si todavía no hay existencia, usamos costo de la variante.
        if ($variantId && Schema::hasTable('products')) {
            $variant = DB::table('products')
                ->where('id', $variantId)
                ->first();

            if ($variant) {
                foreach (['standard_cost', 'purchase_price', 'last_purchase_cost'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $value = $variant->{$column} ?? null;

                        if ($value !== null && (float) $value > 0) {
                            return (float) $value;
                        }
                    }
                }
            }
        }

        // Si no hay variante o la variante no tiene costo, usamos el producto padre.
        if (Schema::hasTable('products')) {
            $product = DB::table('products')
                ->where('id', $productId)
                ->first();

            if ($product) {
                foreach (['standard_cost', 'purchase_price', 'last_purchase_cost'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $value = $product->{$column} ?? null;

                        if ($value !== null && (float) $value > 0) {
                            return (float) $value;
                        }
                    }
                }
            }
        }

        return null;
    }



    protected static function locationAllowsNegativeStock(int $locationId): bool
    {
        if (! Schema::hasTable('stock_locations')) {
            return false;
        }

        if (! Schema::hasColumn('stock_locations', 'allow_negative_stock')) {
            return false;
        }

        return (bool) DB::table('stock_locations')
            ->where('id', $locationId)
            ->value('allow_negative_stock');
    }

    protected static function currentQuantity(int $companyId, int $warehouseId, int $locationId, int $productId, ?int $variantId = null): float
    {
        if (! Schema::hasTable('stock_quants')) {
            return 0;
        }

        $query = DB::table('stock_quants')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId);

        if ($variantId) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->whereNull('product_variant_id');
        }

        return (float) $query->sum('quantity');
    }

    protected static function setQuantQuantity(int $companyId, int $warehouseId, int $locationId, int $productId, ?int $variantId, float $quantity, ?float $unitCost = null): void
    {
        $query = StockQuant::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId);

        if ($variantId) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->whereNull('product_variant_id');
        }

        $quant = $query->first();

        if (! $quant) {
            $quant = new StockQuant([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'reserved_quantity' => 0,
            ]);
        }

        $quant->quantity = $quantity;

        if ($unitCost !== null) {
            $quant->average_cost = $unitCost;
        }

        $quant->save();
    }

    protected static function warehouseOptions(): array
    {
        if (! Schema::hasTable('warehouses')) {
            return [];
        }

        $query = DB::table('warehouses')
            ->where('is_active', true);

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }

    protected static function locationOptions($warehouseId): array
    {
        if (! $warehouseId || ! Schema::hasTable('stock_locations')) {
            return [];
        }

        $query = DB::table('stock_locations')
            ->where('stock_locations.warehouse_id', $warehouseId)
            ->where('stock_locations.is_active', true);

        if (Schema::hasTable('stock_location_types')) {
            $query
                ->leftJoin('stock_location_types', 'stock_location_types.id', '=', 'stock_locations.stock_location_type_id')
                ->where(function ($query): void {
                    $query
                        ->where('stock_location_types.is_internal', true)
                        ->orWhereNull('stock_location_types.id');
                });
        }

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('stock_locations.company_id', $companyId);
        } else {
            $query->whereNull('stock_locations.company_id');
        }

        return $query
            ->orderBy('stock_locations.name')
            ->pluck('stock_locations.name', 'stock_locations.id')
            ->all();
    }

    protected static function allLocationOptions(): array
    {
        if (! Schema::hasTable('stock_locations')) {
            return [];
        }

        $query = DB::table('stock_locations')
            ->where('is_active', true);

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
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

        // En ajustes se selecciona el producto padre.
        // Las variantes se eligen en el campo Variante.
        if (Schema::hasColumn('products', 'is_variant')) {
            $query->where(function ($query): void {
                $query
                    ->where('is_variant', false)
                    ->orWhereNull('is_variant');
            });
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
        if (! $productId || ! Schema::hasTable('products')) {
            return [];
        }

        $productId = (int) $productId;

        if (! Schema::hasColumn('products', 'parent_product_id')) {
            return [];
        }

        $query = DB::table('products')
            ->where('parent_product_id', $productId);

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
        return static::labelFromTable('products', $productId, ['sku', 'internal_reference', 'reference', 'code'], ['name', 'description']);
    }

    protected static function variantLabel($variantId): string
    {
        if (! $variantId || ! Schema::hasTable('products')) {
            return '—';
        }

        $row = DB::table('products')
            ->where('id', $variantId)
            ->first();

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

        $variantText = '';

        $group = Schema::hasColumn('products', 'variant_group')
            ? trim((string) ($row->variant_group ?? ''))
            : '';

        $value = Schema::hasColumn('products', 'variant_value')
            ? trim((string) ($row->variant_value ?? ''))
            : '';

        if ($group !== '' && $value !== '') {
            $variantText = $group . ': ' . $value;
        } elseif ($value !== '') {
            $variantText = $value;
        } elseif (Schema::hasColumn('products', 'variant_name')) {
            $variantText = trim((string) ($row->variant_name ?? ''));
        }

        if ($variantText === '' && Schema::hasColumn('products', 'name')) {
            $variantText = trim((string) ($row->name ?? ''));
        }

        if ($reference !== '' && $variantText !== '') {
            return $reference . ' - ' . $variantText;
        }

        return $variantText ?: ($reference ?: ('Variante #' . $variantId));
    }









    protected static function warehouseLabel($id): string
    {
        return static::labelFromTable('warehouses', $id, ['code'], ['name']);
    }

    protected static function locationLabel($id): string
    {
        return static::labelFromTable('stock_locations', $id, ['code'], ['name']);
    }

    protected static function labelFromTable(string $table, $id, array $codeColumns = [], array $nameColumns = []): string
    {
        if (! $id || ! Schema::hasTable($table)) {
            return '—';
        }

        $row = DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '—';
        }

        $code = '';

        foreach ($codeColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $value = trim((string) ($row->{$column} ?? ''));

                if ($value !== '') {
                    $code = $value;
                    break;
                }
            }
        }

        $name = '';

        foreach ($nameColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $value = trim((string) ($row->{$column} ?? ''));

                if ($value !== '') {
                    $name = $value;
                    break;
                }
            }
        }

        if ($code !== '' && $name !== '') {
            return $code . ' - ' . $name;
        }

        return $name ?: ($code ?: ('#' . $id));
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
        return static::userCanPermission('inventory.adjust_stock');
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCanPermission('inventory.adjust_stock');
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof StockAdjustment
            && $record->status === 'draft'
            && static::userCanPermission('inventory.delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::userCanPermission('inventory.delete');
    }

    protected static function userCanPermission(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (
            method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['admin', 'Administrador', 'Admin Empresa', 'Admin Grupo'])
        ) {
            return true;
        }

        if (method_exists($user, 'can')) {
            return $user->can($permission);
        }

        return false;
    }
}
