<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductCategoryResource\Pages;
use App\Models\Product;
use App\Models\ProductCategory;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'Productos';
    protected static ?string $navigationLabel = 'Categorías de producto';
    protected static ?string $modelLabel = 'Categoría de producto';
    protected static ?string $pluralModelLabel = 'Categorías de producto';
    protected static ?int $navigationSort = 20;
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            return (int) $tenant->getKey();
        }

        $user = Filament::auth()->user();

        return $user && isset($user->company_id)
            ? (int) $user->company_id
            : null;
    }

    protected static function canManage(): bool
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

        return $user->can('inventory.update') || $user->can('inventory.create');
    }

    public static function canAccess(): bool
    {
        return static::canManage();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    protected static function categoryTreeOptions(?string $search = null): array
    {
        $companyId = static::currentCompanyId();

        if (! $companyId) {
            return [];
        }

        $categories = ProductCategory::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'parent_id', 'code', 'name']);

        $byId = $categories->keyBy('id');

        $options = [];

        foreach ($categories as $category) {
            $options[$category->id] = static::buildTreeLabel($category, $byId);
        }

        $search = trim((string) $search);

        if ($search !== '') {
            $needle = mb_strtolower($search);

            $options = collect($options)
                ->filter(fn (string $label): bool => str_contains(mb_strtolower($label), $needle))
                ->all();
        }

        return collect($options)
            ->sort()
            ->take(300)
            ->all();
    }

    protected static function categoryTreeLabel(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        $companyId = static::currentCompanyId();

        $category = ProductCategory::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->whereKey($value)
            ->first(['id', 'parent_id', 'code', 'name']);

        if (! $category) {
            return null;
        }

        $categories = ProductCategory::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->get(['id', 'parent_id', 'code', 'name']);

        return static::buildTreeLabel($category, $categories->keyBy('id'));
    }

    protected static function categoryTreePathLabel(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        $companyId = static::currentCompanyId();

        $category = ProductCategory::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->whereKey($value)
            ->first(['id', 'parent_id', 'code', 'name']);

        if (! $category) {
            return null;
        }

        $categories = ProductCategory::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->get(['id', 'parent_id', 'code', 'name']);

        return static::buildTreePathLabel($category, $categories->keyBy('id'));
    }

    protected static function buildTreePathLabel(ProductCategory $category, \Illuminate\Support\Collection $byId): string
    {
        $names = [];
        $current = $category;
        $guard = 0;

        while ($current && $guard < 30) {
            array_unshift($names, trim((string) $current->name));

            if (! $current->parent_id || ! $byId->has($current->parent_id)) {
                break;
            }

            $current = $byId->get($current->parent_id);
            $guard++;
        }

        return implode(' / ', array_filter($names));
    }


    protected static function buildTreeLabel(ProductCategory $category, \Illuminate\Support\Collection $byId): string
    {
        $names = [];
        $current = $category;
        $guard = 0;

        while ($current && $guard < 30) {
            array_unshift($names, trim((string) $current->name));

            if (! $current->parent_id || ! $byId->has($current->parent_id)) {
                break;
            }

            $current = $byId->get($current->parent_id);
            $guard++;
        }

        $path = implode(' / ', array_filter($names));
        $code = trim((string) ($category->code ?? ''));

        return $code !== ''
            ? $code . ' - ' . $path
            : $path;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Categoría')
                    ->schema([
                        Forms\Components\Hidden::make('company_id')
                            ->default(fn (): ?int => static::currentCompanyId())
                            ->required(),

                        Forms\Components\Select::make('parent_id')
                            ->label('Categoría padre')
                            ->options(fn (): array => static::categoryTreeOptions())
                            ->getSearchResultsUsing(fn (string $search): array => static::categoryTreeOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => static::categoryTreeLabel($value))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Sin categoría padre')
                            ->helperText('Usa este campo para construir el árbol de categorías.')
                            ->columnSpan(12),

                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(80)
                            ->placeholder('Ej. CAT-17')
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->columnSpan(2),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true)
                            ->columnSpan(1),
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

                Tables\Columns\TextColumn::make('tree_label')
                    ->label('Categoría')
                    ->getStateUsing(fn (ProductCategory $record): string => static::categoryTreePathLabel($record->id) ?? $record->name)
                    ->searchable(['code', 'name'])
                    ->wrap()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('name', $direction)),

                Tables\Columns\TextColumn::make('parent_label')
                    ->label('Padre')
                    ->getStateUsing(fn (ProductCategory $record): string => $record->parent_id ? (static::categoryTreeLabel($record->parent_id) ?? '—') : '—')
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn (ProductCategory $record): bool => ! ProductCategory::query()
                        ->where('parent_id', $record->id)
                        ->exists()
                        && ! Product::query()
                            ->where('product_category_id', $record->id)
                            ->exists()
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductCategories::route('/'),
            'create' => Pages\CreateProductCategory::route('/create'),
            'edit' => Pages\EditProductCategory::route('/{record}/edit'),
        ];
    }
}
