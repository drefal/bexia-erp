<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductAttributeResource\Pages;
use App\Filament\Resources\ProductAttributeResource\RelationManagers;
use App\Models\ProductAttribute;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductAttributeResource extends Resource
{
    /**
     * BEXIA_PRODUCT_ATTRIBUTE_RESOURCE_RESPONSIVE_V5_79_81C
     *
     * Visual-only responsive classes for ProductAttributeResource.
     */
    protected static ?string $model = ProductAttribute::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationGroup = 'Productos';
    protected static ?string $navigationLabel = 'Atributos de producto';
    protected static ?string $modelLabel = 'Atributo de producto';
    protected static ?string $pluralModelLabel = 'Atributos de producto';
    protected static ?int $navigationSort = 30;
    protected static ?string $tenantOwnershipRelationshipName = 'company';
    protected static ?string $tenantRelationshipName = 'productAttributes';

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

    /*
     * BEXIA_V5_83_P12C4D_PROTECT_USED_ATTRIBUTE
     *
     * No permitir eliminar atributos del sistema,
     * atributos usados por assignments ni atributos
     * usados históricamente por variantes.
     */
    public static function canDelete(Model $record): bool
    {
        if (! $record instanceof ProductAttribute) {
            return false;
        }

        if (
            ! static::canManage('inventory.delete')
            || (bool) $record->is_system
        ) {
            return false;
        }

        if ($record->assignments()->exists()) {
            return false;
        }

        return ! \App\Models\Product::query()
            ->where(
                'company_id',
                $record->company_id
            )
            ->where('is_variant', true)
            ->whereRaw(
                'LOWER(TRIM(variant_group)) = ?',
                [
                    mb_strtolower(
                        trim((string) $record->name),
                        'UTF-8'
                    ),
                ]
            )
            ->exists();
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
                    ->default(fn () => static::currentCompanyId())
                    ->required(),

                Forms\Components\Section::make('Configuración del atributo')
                    // BEXIA_V5_83_P14A_SIMPLIFIED_ATTRIBUTE_CREATE
                    ->description('Captura el nombre del atributo. Al crear, Bexia genera automáticamente el código y los valores predeterminados.')
                    ->extraAttributes([
                        'class' => 'bexia-pattr-section bexia-pattr-section-main',
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->extraAttributes([
                                'class' => 'bexia-pattr-field bexia-pattr-code-field bexia-pattr-compact-field',
                            ])
                            ->label('Código')
                            ->helperText('Código interno generado automáticamente al crear el atributo.')
                            ->hiddenOn('create')
                            ->required(fn (string $operation): bool => $operation === 'edit')
                            ->maxLength(80)
                            ->unique(
                                table: 'product_attributes',
                                column: 'code',
                                ignorable: fn ($record) => $record,
                                modifyRuleUsing: fn ($rule) => $rule->where('company_id', static::currentCompanyId()),
                            )
                            ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('name')
                            ->extraAttributes([
                                'class' => 'bexia-pattr-field bexia-pattr-name-field bexia-pattr-wide-field',
                            ])
                            ->label('Nombre')
                            ->helperText('Ejemplo: Color, Talla, Material.')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('sort_order')
                            ->extraAttributes([
                                'class' => 'bexia-pattr-field bexia-pattr-sort-field bexia-pattr-compact-field',
                            ])
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->hiddenOn('create')
                            ->columnSpan(3),

                        Forms\Components\Toggle::make('is_variant')
                            ->extraAttributes([
                                'class' => 'bexia-pattr-field bexia-pattr-toggle-field bexia-pattr-variant-field',
                            ])
                            ->label('Usar para variantes')
                            ->helperText('Actívalo si este atributo genera variantes del producto.')
                            ->default(true)
                            ->hiddenOn('create')
                            ->columnSpan(4),

                        Forms\Components\Toggle::make('is_active')
                            ->extraAttributes([
                                'class' => 'bexia-pattr-field bexia-pattr-toggle-field bexia-pattr-active-field',
                            ])
                            ->label('Activo')
                            ->default(true)
                            ->hiddenOn('create')
                            ->columnSpan(4),

                        Forms\Components\Toggle::make('is_system')
                            ->extraAttributes([
                                'class' => 'bexia-pattr-field bexia-pattr-toggle-field bexia-pattr-system-field',
                            ])
                            ->label('Sistema')
                            ->helperText('Los registros del sistema no se deberían eliminar.')
                            ->hiddenOn('create')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(4),
                    ])
                    ->columns(12),
            ])
            ->columns(12);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.productattributeresource',
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-pattr-header bexia-pattr-col-code',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-pattr-cell bexia-pattr-col-code bexia-pattr-col-compact',
                    ])
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-pattr-header bexia-pattr-col-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-pattr-cell bexia-pattr-col-name bexia-pattr-col-wide',
                    ])
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                /*
                 * BEXIA_V5_83_P12C4D_ATTRIBUTE_VALUES_COUNT
                 */
                Tables\Columns\TextColumn::make('values_count')
                    ->label('Valores')
                    ->counts('values')
                    ->badge()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_variant')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-pattr-header bexia-pattr-col-variant',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-pattr-cell bexia-pattr-col-variant bexia-pattr-col-bool',
                    ])
                    ->label('Variante')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-pattr-header bexia-pattr-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-pattr-cell bexia-pattr-col-active bexia-pattr-col-bool',
                    ])
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_system')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-pattr-header bexia-pattr-col-system',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-pattr-cell bexia-pattr-col-system bexia-pattr-col-bool',
                    ])
                    ->label('Sistema')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-pattr-header bexia-pattr-col-sort',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-pattr-cell bexia-pattr-col-sort bexia-pattr-col-compact',
                    ])
                    ->label('Orden')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_variant')
                    ->label('Usar para variantes'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(
                        fn (ProductAttribute $record): bool =>
                            static::canDelete($record)
                    ),
            ])
            /*
             * El borrado masivo se deshabilita para evitar
             * eliminar catálogos que ya tengan uso histórico.
             */
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ValuesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductAttributes::route('/'),
            'create' => Pages\CreateProductAttribute::route('/create'),
            'edit' => Pages\EditProductAttribute::route('/{record}/edit'),
        ];
    }
}
