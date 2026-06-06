<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockLocationTypeResource\Pages;
use App\Models\StockLocationType;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockLocationTypeResource extends Resource
{
    protected static ?string $model = StockLocationType::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?string $navigationLabel = 'Tipos de ubicación';

    protected static ?string $modelLabel = 'tipo de ubicación';

    protected static ?string $pluralModelLabel = 'tipos de ubicación';
    protected static ?int $navigationSort = 20;
    protected static bool $isScopedToTenant = false;

public static function getEloquentQuery(): Builder
    {
        $query = StockLocationType::query();

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
        'resources.stocklocationtyperesource',
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => static::currentCompanyId()),

                Forms\Components\Section::make('Tipo de ubicación')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(50)
                            ->helperText('Ej. INTERNAL, SUPPLIER, CUSTOMER.')
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(150)
                            ->columnSpan(5),

                        Forms\Components\Toggle::make('is_internal')
                            ->label('Cuenta como stock interno')
                            ->helperText('Solo ubicaciones internas suman existencia disponible.')
                            ->default(false)
                            ->columnSpan(2),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('description')
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
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_internal')
                    ->label('Stock interno')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),

                Tables\Filters\TernaryFilter::make('is_internal')
                    ->label('Stock interno'),
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
            'index' => Pages\ListStockLocationTypes::route('/'),
            'create' => Pages\CreateStockLocationType::route('/create'),
            'edit' => Pages\EditStockLocationType::route('/{record}/edit'),
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
