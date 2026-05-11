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

    public static function canViewAny(): bool
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
        return static::canManage('inventory.delete') && ! (bool) $record->is_system;
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

                Forms\Components\Section::make('Datos del atributo')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->helperText('Ejemplo: COLOR, TALLA, MATERIAL.')
                            ->required()
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
                            ->label('Nombre')
                            ->helperText('Ejemplo: Color, Talla, Material.')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(5),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->columnSpan(3),

                        Forms\Components\Toggle::make('is_variant')
                            ->label('Usar para variantes')
                            ->helperText('Actívalo si este atributo genera variantes del producto.')
                            ->default(true)
                            ->columnSpan(4),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->columnSpan(4),

                        Forms\Components\Toggle::make('is_system')
                            ->label('Sistema')
                            ->helperText('Los registros del sistema no se deberían eliminar.')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(4),
                    ])
                    ->columns(12),
            ])
            ->columns(12);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\IconColumn::make('is_variant')
                    ->label('Variante')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_system')
                    ->label('Sistema')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable()
                    ->toggleable(),
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
                    ->visible(fn (ProductAttribute $record) => ! (bool) $record->is_system),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ]);
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
