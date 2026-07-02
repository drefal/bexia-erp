<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WarehouseResource extends Resource
{
    /**
     * BEXIA_WAREHOUSE_RESOURCE_RESPONSIVE_V5_79_103C
     *
     * Visual-only responsive classes for WarehouseResource.
     */
    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?string $navigationLabel = 'Almacenes';

    protected static ?string $modelLabel = 'almacén';

    protected static ?string $pluralModelLabel = 'almacenes';
    protected static ?int $navigationSort = 10;
    protected static bool $isScopedToTenant = false;
public static function getEloquentQuery(): Builder
    {
        $query = Warehouse::query();

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
        'resources.warehouseresource',
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
                    ->default(fn (): ?int => static::currentCompanyId()),

                Forms\Components\Section::make('Almacén')
                    ->extraAttributes([
                        'class' => 'bexia-whse-section bexia-whse-main-section',
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->extraAttributes([
                                'class' => 'bexia-whse-field bexia-whse-code-field bexia-whse-compact-field',
                            ])
                            ->label('Código')
                            ->required()
                            ->maxLength(50)
                            ->helperText('Ej. PRINCIPAL, CEDIS, TIENDA_01.')
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('name')
                            ->extraAttributes([
                                'class' => 'bexia-whse-field bexia-whse-name-field bexia-whse-main-field',
                            ])
                            ->label('Nombre')
                            ->required()
                            ->maxLength(150)
                            ->columnSpan(5),

                        Forms\Components\Toggle::make('is_active')
                            ->extraAttributes([
                                'class' => 'bexia-whse-field bexia-whse-active-field bexia-whse-bool-field',
                            ])
                            ->label('Activo')
                            ->default(true)
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('description')
                            ->extraAttributes([
                                'class' => 'bexia-whse-field bexia-whse-description-field bexia-whse-wide-field bexia-whse-textarea-field',
                            ])
                            ->dehydrated(false) // V5.61.2j: warehouses no tiene columna description.
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
                    ->extraHeaderAttributes([
                        'class' => 'bexia-whse-header bexia-whse-col-code',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-whse-cell bexia-whse-col-code bexia-whse-col-compact',
                    ])
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-whse-header bexia-whse-col-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-whse-cell bexia-whse-col-name bexia-whse-col-main',
                    ])
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('locations_count')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-whse-header bexia-whse-col-locations',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-whse-cell bexia-whse-col-locations bexia-whse-col-count',
                    ])
                    ->label('Ubicaciones')
                    ->counts('locations')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-whse-header bexia-whse-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-whse-cell bexia-whse-col-active bexia-whse-col-bool',
                    ])
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
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
}
