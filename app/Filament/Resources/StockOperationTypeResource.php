<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockOperationTypeResource\Pages;
use App\Models\StockLocation;
use App\Models\StockOperationType;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StockOperationTypeResource extends Resource
{
    protected static ?string $model = StockOperationType::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Tipos de operación';

    protected static ?string $modelLabel = 'tipo de operación';

    protected static ?string $pluralModelLabel = 'tipos de operación';

    protected static ?int $navigationSort = 40;

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        $query = StockOperationType::query()
            ->with(['warehouse', 'sourceLocation', 'destinationLocation']);

        $companyId = static::currentCompanyId();

        $query->where(function (Builder $query) use ($companyId): void {
            $query->whereNull('company_id');

            if ($companyId) {
                $query->orWhere('company_id', $companyId);
            }
        });

        return $query;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.stockoperationtyperesource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('pos.menu.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('pos.menu.view')
            );
    }

    /*
     * BEXIA_SOPT_RESOURCE_RESPONSIVE_V5_79_62C
     * Visual-only responsive marker.
     */


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => static::currentCompanyId()),

                Forms\Components\Section::make('Tipo de operación')
                    ->extraAttributes(['class' => 'bexia-sopt-section bexia-sopt-section-main'])
                    ->schema([
                        Forms\Components\Select::make('warehouse_id')
                            ->extraAttributes(['class' => 'bexia-sopt-field bexia-sopt-field-warehouse bexia-sopt-select'])
                            ->label('Almacén')
                            ->options(fn (): array => static::warehouseOptions())
                            ->searchable()
                            ->native(false)
                            ->placeholder('General / todos los almacenes')
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('source_location_id', null);
                                $set('destination_location_id', null);
                            })
                            ->columnSpan(4),

                        Forms\Components\Select::make('operation_kind')
                            ->extraAttributes(['class' => 'bexia-sopt-field bexia-sopt-field-operation-kind bexia-sopt-select'])
                            ->label('Tipo')
                            ->options([
                                'receipt' => 'Recepción',
                                'delivery' => 'Entrega',
                                'internal_transfer' => 'Traslado interno',
                                'manufacturing' => 'Fabricación',
                                'inventory_adjustment' => 'Ajuste de inventario',
                            ])
                            ->required()
                            ->native(false)
                            ->columnSpan(4),

                        Forms\Components\Toggle::make('is_active')
                            ->extraAttributes(['class' => 'bexia-sopt-field bexia-sopt-field-active bexia-sopt-toggle'])
                            ->label('Activo')
                            ->default(true)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('sequence')
                            ->extraAttributes(['class' => 'bexia-sopt-field bexia-sopt-field-sequence bexia-sopt-number-field'])
                            ->label('Orden')
                            ->numeric()
                            ->default(10)
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('code')
                            ->extraAttributes(['class' => 'bexia-sopt-field bexia-sopt-field-code bexia-sopt-code-field'])
                            ->label('Código interno')
                            ->required()
                            ->maxLength(80)
                            ->helperText('Uso técnico. Ej. RECEPCION, ENTREGA, TRASLADO.')
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('name')
                            ->extraAttributes(['class' => 'bexia-sopt-field bexia-sopt-field-name'])
                            ->label('Nombre')
                            ->required()
                            ->maxLength(180)
                            ->helperText('Este es el nombre que verá el usuario.')
                            ->columnSpan(5),

                        Forms\Components\TextInput::make('reference_prefix')
                            ->extraAttributes(['class' => 'bexia-sopt-field bexia-sopt-field-reference-prefix bexia-sopt-code-field'])
                            ->label('Prefijo de referencia')
                            ->maxLength(30)
                            ->placeholder('Ej. REC, ENT, TR')
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('next_number')
                            ->extraAttributes(['class' => 'bexia-sopt-field bexia-sopt-field-next-number bexia-sopt-number-field'])
                            ->label('Siguiente número')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\Select::make('source_location_id')
                            ->extraAttributes(['class' => 'bexia-sopt-field bexia-sopt-field-source-location bexia-sopt-select'])
                            ->label('Ubicación origen')
                            ->options(fn (Forms\Get $get): array => static::locationOptions($get('warehouse_id')))
                            ->searchable()
                            ->native(false)
                            ->placeholder('Sin origen fijo')
                            ->columnSpan(6),

                        Forms\Components\Select::make('destination_location_id')
                            ->extraAttributes(['class' => 'bexia-sopt-field bexia-sopt-field-destination-location bexia-sopt-select'])
                            ->label('Ubicación destino')
                            ->options(fn (Forms\Get $get): array => static::locationOptions($get('warehouse_id')))
                            ->searchable()
                            ->native(false)
                            ->placeholder('Sin destino fijo')
                            ->columnSpan(6),

                        Forms\Components\Textarea::make('description')
                            ->extraAttributes(['class' => 'bexia-sopt-field bexia-sopt-field-description'])
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
                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes(['class' => 'bexia-sopt-col-code bexia-sopt-col-primary bexia-sopt-col-code-text'])
                    ->extraCellAttributes(['class' => 'bexia-sopt-col-code bexia-sopt-col-primary bexia-sopt-col-code-text'])
                    ->label('Código')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes(['class' => 'bexia-sopt-col-name bexia-sopt-col-primary-text'])
                    ->extraCellAttributes(['class' => 'bexia-sopt-col-name bexia-sopt-col-primary-text'])
                    ->label('Tipo de operación')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('operation_kind')
                    ->extraHeaderAttributes(['class' => 'bexia-sopt-col-operation-kind bexia-sopt-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-sopt-col-operation-kind bexia-sopt-col-badge'])
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'receipt' => 'Recepción',
                        'delivery' => 'Entrega',
                        'internal_transfer' => 'Traslado interno',
                        'manufacturing' => 'Fabricación',
                        'inventory_adjustment' => 'Ajuste de inventario',
                        default => (string) $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->extraHeaderAttributes(['class' => 'bexia-sopt-col-warehouse bexia-sopt-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-sopt-col-warehouse bexia-sopt-col-context'])
                    ->label('Almacén')
                    ->placeholder('General')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sourceLocation.name')
                    ->extraHeaderAttributes(['class' => 'bexia-sopt-col-source-location bexia-sopt-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-sopt-col-source-location bexia-sopt-col-context'])
                    ->label('Origen')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('destinationLocation.name')
                    ->extraHeaderAttributes(['class' => 'bexia-sopt-col-destination-location bexia-sopt-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-sopt-col-destination-location bexia-sopt-col-context'])
                    ->label('Destino')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reference_prefix')
                    ->extraHeaderAttributes(['class' => 'bexia-sopt-col-reference-prefix bexia-sopt-col-code-text'])
                    ->extraCellAttributes(['class' => 'bexia-sopt-col-reference-prefix bexia-sopt-col-code-text'])
                    ->label('Prefijo')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('next_number')
                    ->extraHeaderAttributes(['class' => 'bexia-sopt-col-next-number bexia-sopt-col-number'])
                    ->extraCellAttributes(['class' => 'bexia-sopt-col-next-number bexia-sopt-col-number'])
                    ->label('Siguiente')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sequence')
                    ->extraHeaderAttributes(['class' => 'bexia-sopt-col-sequence bexia-sopt-col-number'])
                    ->extraCellAttributes(['class' => 'bexia-sopt-col-sequence bexia-sopt-col-number'])
                    ->label('Orden')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes(['class' => 'bexia-sopt-col-active bexia-sopt-col-icon'])
                    ->extraCellAttributes(['class' => 'bexia-sopt-col-active bexia-sopt-col-icon'])
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('operation_kind')
                    ->label('Tipo')
                    ->options([
                        'receipt' => 'Recepción',
                        'delivery' => 'Entrega',
                        'internal_transfer' => 'Traslado interno',
                        'manufacturing' => 'Fabricación',
                        'inventory_adjustment' => 'Ajuste de inventario',
                    ]),

                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Almacén')
                    ->options(fn (): array => static::warehouseOptions()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ])
            ->defaultSort('sequence');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockOperationTypes::route('/'),
            'create' => Pages\CreateStockOperationType::route('/create'),
            'edit' => Pages\EditStockOperationType::route('/{record}/edit'),
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
            ->get(['id', 'name'])
            ->pluck('name', 'id')
            ->all();
    }

    protected static function locationOptions($warehouseId): array
    {
        $query = StockLocation::query()
            ->where('is_active', true);

        if ($warehouseId) {
            $query->where(function (Builder $query) use ($warehouseId): void {
                $query
                    ->where('warehouse_id', $warehouseId)
                    ->orWhereNull('warehouse_id');
            });
        }

        $companyId = static::currentCompanyId();

        $query->where(function (Builder $query) use ($companyId): void {
            $query->whereNull('company_id');

            if ($companyId) {
                $query->orWhere('company_id', $companyId);
            }
        });

        return $query
            ->orderByRaw('warehouse_id nulls first')
            ->orderBy('name')
            ->get(['id', 'warehouse_id', 'name'])
            ->mapWithKeys(fn (StockLocation $location): array => [
                $location->id => ($location->warehouse_id ? '' : 'Virtual / ') . $location->name,
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

public static function canCreate(): bool
    {
        return static::userCanPermission('inventory.create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCanPermission('inventory.update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCanPermission('inventory.delete');
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
