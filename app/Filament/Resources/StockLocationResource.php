<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockLocationResource\Pages;
use App\Models\StockLocation;
use App\Models\StockLocationType;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockLocationResource extends Resource
{
    protected static ?string $model = StockLocation::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?string $navigationLabel = 'Ubicaciones';

    protected static ?string $modelLabel = 'ubicación';

    protected static ?string $pluralModelLabel = 'ubicaciones';
protected static ?int $navigationSort = 30;
protected static bool $isScopedToTenant = false;

public static function getEloquentQuery(): Builder    {
        $query = StockLocation::query()
            ->with(['warehouse', 'type', 'parent']);

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
        'resources.stocklocationresource',
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
     * BEXIA_STOCK_LOCATION_RESOURCE_RESPONSIVE_V5_79_77C
     * Visual-only responsive marker.
     */


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => static::currentCompanyId()),

                Forms\Components\Section::make('Ubicación')
                    ->extraAttributes(['class' => 'bexia-slr-section bexia-slr-section-main'])
                    ->schema([
                        Forms\Components\Toggle::make('is_virtual_location')
                            ->extraAttributes(['class' => 'bexia-slr-field bexia-slr-toggle-field bexia-slr-toggle-virtual-location'])
                            ->label('Ubicación virtual')
                            ->helperText('Actívalo para ubicaciones como Proveedores, Clientes, Tránsito, Ajustes, Pérdida o Producción.')
                            ->default(fn (?StockLocation $record): bool => $record ? blank($record->warehouse_id) : false)
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, ?bool $state): void {
                                if ($state) {
                                    $set('warehouse_id', null);
                                    $set('parent_id', null);
                                }
                            })
                            ->columnSpan(3),

                        Forms\Components\Select::make('warehouse_id')
                            ->extraAttributes(['class' => 'bexia-slr-field bexia-slr-select-field bexia-slr-field-warehouse bexia-slr-related-field'])
                            ->label('Almacén')
                            ->options(fn (): array => static::warehouseOptions())
                            ->searchable()
                            ->native(false)
                            ->placeholder('Sin almacén / ubicación virtual')
                            ->required(fn (Forms\Get $get): bool => ! (bool) $get('is_virtual_location'))
                            ->disabled(fn (Forms\Get $get): bool => (bool) $get('is_virtual_location'))
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set): null => $set('parent_id', null))
                            ->columnSpan(4),

                        Forms\Components\Select::make('parent_id')
                            ->extraAttributes(['class' => 'bexia-slr-field bexia-slr-select-field bexia-slr-field-parent bexia-slr-hierarchy-field'])
                            ->label('Ubicación padre')
                            ->options(fn (Forms\Get $get, ?StockLocation $record): array => static::parentLocationOptions($get('warehouse_id'), $record?->id))
                            ->searchable()
                            ->native(false)
                            ->placeholder('Sin ubicación padre')
                            ->disabled(fn (Forms\Get $get): bool => (bool) $get('is_virtual_location'))
                            ->columnSpan(5),

                        Forms\Components\Select::make('stock_location_type_id')
                            ->extraAttributes(['class' => 'bexia-slr-field bexia-slr-select-field bexia-slr-field-type bexia-slr-related-field'])
                            ->label('Tipo de ubicación')
                            ->options(fn (): array => static::locationTypeOptions())
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('code')
                            ->extraAttributes(['class' => 'bexia-slr-field bexia-slr-code-field bexia-slr-short-field'])
                            ->label('Código')
                            ->required()
                            ->maxLength(80)
                            ->helperText('Ej. EXISTENCIAS, TRANSITO, PROVEEDORES, RACK-A-01.')
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('name')
                            ->extraAttributes(['class' => 'bexia-slr-field bexia-slr-name-field bexia-slr-primary-field'])
                            ->label('Nombre')
                            ->required()
                            ->maxLength(180)
                            ->columnSpan(5),

                        Forms\Components\TextInput::make('barcode')
                            ->extraAttributes(['class' => 'bexia-slr-field bexia-slr-barcode-field bexia-slr-code-field'])
                            ->label('Código de barras')
                            ->maxLength(120)
                            ->columnSpan(2),

                        Forms\Components\Toggle::make('is_active')
                            ->extraAttributes(['class' => 'bexia-slr-field bexia-slr-toggle-field bexia-slr-toggle-active'])
                            ->label('Activo')
                            ->default(true)
                            ->columnSpan(2),

                        Forms\Components\Toggle::make('allow_negative_stock')
                            ->extraAttributes(['class' => 'bexia-slr-field bexia-slr-toggle-field bexia-slr-toggle-negative-stock'])
                            ->label('Permitir existencias negativas')
                            ->helperText('Por defecto no se permite. Actívalo solo para ubicaciones especiales.')
                            ->default(false)
                            ->columnSpan(3),

                        Forms\Components\Textarea::make('description')
                            ->extraAttributes(['class' => 'bexia-slr-field bexia-slr-description-field bexia-slr-long-field'])
                            ->label('Descripción')
                            ->rows(3)
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
                    ->extraHeaderAttributes(['class' => 'bexia-slr-col-warehouse bexia-slr-col-related bexia-slr-col-location-context bexia-slr-col-long-text'])
                    ->extraCellAttributes(['class' => 'bexia-slr-col-warehouse bexia-slr-col-related bexia-slr-col-location-context bexia-slr-col-long-text'])
                    ->label('Almacén')
                    ->getStateUsing(fn ($record): string => $record->warehouse?->name ?: 'Sin almacén (virtual)')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes(['class' => 'bexia-slr-col-code bexia-slr-col-key bexia-slr-col-short bexia-slr-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-slr-col-code bexia-slr-col-key bexia-slr-col-short bexia-slr-col-context'])
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes(['class' => 'bexia-slr-col-name bexia-slr-col-primary bexia-slr-col-long-text bexia-slr-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-slr-col-name bexia-slr-col-primary bexia-slr-col-long-text bexia-slr-col-context'])
                    ->label('Ubicación')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->extraHeaderAttributes(['class' => 'bexia-slr-col-parent bexia-slr-col-hierarchy bexia-slr-col-related bexia-slr-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-slr-col-parent bexia-slr-col-hierarchy bexia-slr-col-related bexia-slr-col-context'])
                    ->label('Padre')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('type.name')
                    ->extraHeaderAttributes(['class' => 'bexia-slr-col-type bexia-slr-col-related bexia-slr-col-location-type bexia-slr-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-slr-col-type bexia-slr-col-related bexia-slr-col-location-type bexia-slr-col-context'])
                    ->label('Tipo')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('barcode')
                    ->extraHeaderAttributes(['class' => 'bexia-slr-col-barcode bexia-slr-col-key bexia-slr-col-short bexia-slr-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-slr-col-barcode bexia-slr-col-key bexia-slr-col-short bexia-slr-col-context'])
                    ->label('Código de barras')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('allow_negative_stock')
                    ->extraHeaderAttributes(['class' => 'bexia-slr-col-negative-stock bexia-slr-col-flag bexia-slr-col-icon bexia-slr-col-policy'])
                    ->extraCellAttributes(['class' => 'bexia-slr-col-negative-stock bexia-slr-col-flag bexia-slr-col-icon bexia-slr-col-policy'])
                    ->label('Negativos')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes(['class' => 'bexia-slr-col-active bexia-slr-col-status bexia-slr-col-icon bexia-slr-col-flag'])
                    ->extraCellAttributes(['class' => 'bexia-slr-col-active bexia-slr-col-status bexia-slr-col-icon bexia-slr-col-flag'])
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->extraAttributes(['class' => 'bexia-slr-filter bexia-slr-filter-warehouse bexia-slr-filter-related'])
                    ->label('Almacén')
                    ->options(fn (): array => static::warehouseOptions()),

                Tables\Filters\SelectFilter::make('stock_location_type_id')
                    ->extraAttributes(['class' => 'bexia-slr-filter bexia-slr-filter-type bexia-slr-filter-related'])
                    ->label('Tipo')
                    ->options(fn (): array => static::locationTypeOptions()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->extraAttributes(['class' => 'bexia-slr-filter bexia-slr-filter-active bexia-slr-filter-status'])
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ])
            ->defaultSort('warehouse_id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockLocations::route('/'),
            'create' => Pages\CreateStockLocation::route('/create'),
            'edit' => Pages\EditStockLocation::route('/{record}/edit'),
        ];
    }

    protected static function warehouseOptions(): array
    {
        $query = Warehouse::query()
            ->where('is_active', true);

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
        }

        return $query
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (Warehouse $warehouse): array => [
                $warehouse->id => $warehouse->code . ' - ' . $warehouse->name,
            ])
            ->all();
    }

    protected static function locationTypeOptions(): array
    {
        $query = StockLocationType::query()
            ->where('is_active', true);

        $companyId = static::currentCompanyId();

        $query->where(function (Builder $query) use ($companyId): void {
            $query->whereNull('company_id');

            if ($companyId) {
                $query->orWhere('company_id', $companyId);
            }
        });

        return $query
            ->orderBy('name')
            ->get(['id', 'name'])
            ->pluck('name', 'id')
            ->all();
    }

    protected static function parentLocationOptions($warehouseId, ?int $currentId = null): array
    {
        if (! $warehouseId) {
            return [];
        }

        $query = StockLocation::query()
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true);

        if ($currentId) {
            $query->where('id', '<>', $currentId);
        }

        return $query
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (StockLocation $location): array => [
                $location->id => $location->code . ' - ' . $location->name,
            ])
            ->all();
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
}
