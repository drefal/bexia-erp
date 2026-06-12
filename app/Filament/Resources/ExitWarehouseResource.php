<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExitWarehouseResource\Pages;
use App\Models\ExitWarehouse;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExitWarehouseResource extends Resource
{
    protected static ?string $model = ExitWarehouse::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Salidas';
    protected static ?string $navigationLabel = 'Almacenes de Salidas';

    protected static ?int $navigationSort = 30;
    protected static ?string $modelLabel = 'Almacén de salida';
    protected static ?string $pluralModelLabel = 'Almacenes de salidas';
protected static ?string $tenantOwnershipRelationshipName = 'company';
    protected static ?string $tenantRelationshipName = 'exitWarehouses';

    protected static function canManageCatalogs(): bool
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

        return $user->can('salidas.configurar');
    }

    public static function canAccess(): bool
    {
        return static::canManageCatalogs();
    }

public static function canCreate(): bool
    {
        return static::canManageCatalogs();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageCatalogs();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManageCatalogs();
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

public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn () => Filament::getTenant()?->getKey())
                    ->required(),

                Forms\Components\Section::make('Datos generales')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->default(fn () => filled(Filament::getTenant()?->getKey()) ? ExitWarehouse::nextCodeForCompany((int) Filament::getTenant()->getKey()) : '00001')
                            ->helperText('Se genera automáticamente con 5 dígitos según la empresa actual.')
                            ->readOnly()
                            ->dehydrated()
                            ->maxLength(5)
                            ->columnSpan(6)
                            ->extraInputAttributes([
                                'style' => 'background-color:#ffffff;',
                            ]),

                        Forms\Components\Select::make('usage_type')
                            ->label('Uso')
                            ->options([
                                'envio' => 'Solo envío',
                                'recepcion' => 'Solo recepción',
                                'ambos' => 'Ambos',
                            ])
                            ->default('ambos')
                            ->required()
                            ->native(false)
                            ->columnSpan(4),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->columnSpan(4)
                            ->extraInputAttributes([
                                'style' => 'background-color:#ffffff;',
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(4)
                            ->columnSpan(12)
                            ->extraInputAttributes([
                                'style' => 'background-color:#ffffff;',
                            ]),
                    ])
                    ->columns(12),
            ])
            ->columns(12);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.exitwarehouseresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('salidas.ver')
                || $user?->can('salidas.ver_todas')
                || $user?->can('salidas.configurar')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('salidas.ver')
                || $user?->can('salidas.ver_todas')
                || $user?->can('salidas.configurar')
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->extraHeaderAttributes(['style' => 'min-width: 220px;'])
                    ->extraCellAttributes(['style' => 'min-width: 220px;'])
                    ->wrap()
                    ->width('260px'),

                Tables\Columns\TextColumn::make('code')
                    ->hidden()
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->extraHeaderAttributes(['style' => 'min-width: 140px;'])
                    ->extraCellAttributes(['style' => 'min-width: 140px;'])
                    ->wrap()
                    ->width('160px'),

                Tables\Columns\TextColumn::make('usage_type')
                    ->label('Uso')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'envio' => 'Solo envío',
                        'recepcion' => 'Solo recepción',
                        'ambos' => 'Ambos',
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExitWarehouses::route('/'),
            'create' => Pages\CreateExitWarehouse::route('/create'),
            'edit' => Pages\EditExitWarehouse::route('/{record}/edit'),
        ];
    }
}
