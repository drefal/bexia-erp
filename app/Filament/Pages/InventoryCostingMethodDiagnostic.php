<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Support\Inventory\InventoryCostingMethodResolver;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BEXIA_V57210L_INVENTORY_MENU_PERMISSION
class InventoryCostingMethodDiagnostic extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Diagnóstico de costeo';

    protected static ?string $title = 'Diagnóstico de método de costeo';

    protected static ?string $slug = 'diagnostico-costeo-inventario';

    protected static ?int $navigationSort = 98;

    protected static string $view = 'filament.pages.inventory-costing-method-diagnostic';

    protected array $categoryCache = [];

    protected array $companyCache = [];

    protected array $effectiveCache = [];

    public static function canAccess(): bool
    {
        return \App\Support\Security\BexiaTenantPermission::can('inventory.menu.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->productsQuery())
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('internal_reference')
                    ->label('Referencia')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('name')
                    ->label('Producto')
                    ->searchable()
                    ->wrap()
                    ->description(fn (Product $record): string => $this->productDescription($record))
                    ->sortable(),

                Tables\Columns\TextColumn::make('category_name_diagnostic')
                    ->label('Categoría')
                    ->state(fn (Product $record): string => $this->categoryName($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        if (! Schema::hasTable('product_categories')) {
                            return $query;
                        }

                        return $query->whereIn('product_category_id', function ($subquery) use ($search): void {
                            $subquery->select('id')
                                ->from('product_categories')
                                ->where('name', 'ilike', '%' . $search . '%');
                        });
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('costing_method')
                    ->label('Producto')
                    ->formatStateUsing(fn (?string $state): string => $this->methodLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => $this->methodColor($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('category_costing_method_diagnostic')
                    ->label('Categoría')
                    ->state(fn (Product $record): string => $this->methodLabel($this->categoryCostingMethod($record)))
                    ->badge()
                    ->color(fn (Product $record): string => $this->methodColor($this->categoryCostingMethod($record))),

                Tables\Columns\TextColumn::make('company_default_costing_method_diagnostic')
                    ->label('Empresa')
                    ->state(fn (Product $record): string => $this->methodLabel($this->companyDefaultCostingMethod($record)))
                    ->badge()
                    ->color(fn (Product $record): string => $this->methodColor($this->companyDefaultCostingMethod($record))),

                Tables\Columns\TextColumn::make('effective_costing_method_diagnostic')
                    ->label('Método efectivo')
                    ->state(fn (Product $record): string => $this->methodLabel($this->resolved($record)['method'] ?? 'average'))
                    ->badge()
                    ->color(fn (Product $record): string => $this->methodColor($this->resolved($record)['method'] ?? 'average')),

                Tables\Columns\TextColumn::make('effective_costing_source_diagnostic')
                    ->label('Fuente')
                    ->state(fn (Product $record): string => $this->sourceLabel($this->resolved($record)['source'] ?? 'system'))
                    ->badge()
                    ->color(fn (Product $record): string => $this->sourceColor($this->resolved($record)['source'] ?? 'system')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('costing_method')
                    ->label('Método configurado en producto')
                    ->options([
                        'inherit' => 'Heredar',
                        'average' => 'Promedio',
                        'fifo' => 'FIFO',
                        'standard' => 'Costo estándar',
                    ]),

                Tables\Filters\SelectFilter::make('product_type')
                    ->label('Tipo')
                    ->options([
                        'product' => 'Producto',
                        'service' => 'Servicio',
                        'consumable' => 'Consumible',
                        'storable' => 'Inventariable',
                    ]),

                Tables\Filters\TernaryFilter::make('is_variant')
                    ->label('Es variante'),
            ])
            ->defaultSort('name')
            ->paginated([25, 50, 100]);
    }

    protected function productsQuery(): Builder
    {
        $query = Product::query();

        if (Schema::hasColumn('products', 'company_id')) {
            $tenantCompanyId = $this->tenantCompanyId();

            if ($tenantCompanyId) {
                $query->where('company_id', $tenantCompanyId);
            }
        }

        return $query;
    }

    protected function tenantCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant && isset($tenant->id)) {
            return (int) $tenant->id;
        }

        $user = auth()->user();

        if ($user && isset($user->company_id)) {
            return (int) $user->company_id;
        }

        return null;
    }

    protected function resolved(Product $record): array
    {
        $key = (string) $record->getKey();

        if (isset($this->effectiveCache[$key])) {
            return $this->effectiveCache[$key];
        }

        $variantId = null;
        $productId = (int) $record->getKey();

        if (! empty($record->is_variant) || ! empty($record->parent_product_id)) {
            $variantId = (int) $record->getKey();
            $productId = ! empty($record->parent_product_id)
                ? (int) $record->parent_product_id
                : (int) $record->getKey();
        }

        $companyId = ! empty($record->company_id)
            ? (int) $record->company_id
            : $this->tenantCompanyId();

        return $this->effectiveCache[$key] = app(InventoryCostingMethodResolver::class)
            ->resolve($companyId, $productId, $variantId);
    }

    protected function categoryName(Product $record): string
    {
        $category = $this->category((int) ($record->product_category_id ?? 0));

        return $category->name ?? 'Sin categoría';
    }

    protected function categoryCostingMethod(Product $record): ?string
    {
        $category = $this->category((int) ($record->product_category_id ?? 0));

        return $category->costing_method ?? 'inherit';
    }

    protected function companyDefaultCostingMethod(Product $record): ?string
    {
        $company = $this->company((int) ($record->company_id ?? 0));

        return $company->default_costing_method ?? 'average';
    }

    protected function category(int $categoryId): ?object
    {
        if ($categoryId <= 0 || ! Schema::hasTable('product_categories')) {
            return null;
        }

        if (! array_key_exists($categoryId, $this->categoryCache)) {
            $this->categoryCache[$categoryId] = DB::table('product_categories')
                ->where('id', $categoryId)
                ->first();
        }

        return $this->categoryCache[$categoryId];
    }

    protected function company(int $companyId): ?object
    {
        if ($companyId <= 0 || ! Schema::hasTable('companies')) {
            return null;
        }

        if (! array_key_exists($companyId, $this->companyCache)) {
            $this->companyCache[$companyId] = DB::table('companies')
                ->where('id', $companyId)
                ->first();
        }

        return $this->companyCache[$companyId];
    }

    protected function productDescription(Product $record): string
    {
        $parts = [];

        if (! empty($record->product_type)) {
            $parts[] = 'Tipo: ' . $record->product_type;
        }

        if (! empty($record->is_variant)) {
            $parts[] = 'Variante';
        }

        if (! empty($record->parent_product_id)) {
            $parts[] = 'Padre #' . $record->parent_product_id;
        }

        return implode(' · ', $parts);
    }

    protected function methodLabel(?string $method): string
    {
        return match ($method) {
            'fifo' => 'FIFO',
            'standard' => 'Costo estándar',
            'average' => 'Promedio',
            default => 'Heredar',
        };
    }

    protected function methodColor(?string $method): string
    {
        return match ($method) {
            'fifo' => 'warning',
            'standard' => 'info',
            'average' => 'success',
            default => 'gray',
        };
    }

    protected function sourceLabel(?string $source): string
    {
        return match ($source) {
            'product_variant' => 'Variante',
            'product' => 'Producto',
            'category' => 'Categoría',
            'company' => 'Empresa',
            default => 'Sistema',
        };
    }

    protected function sourceColor(?string $source): string
    {
        return match ($source) {
            'product_variant', 'product' => 'success',
            'category' => 'warning',
            'company' => 'info',
            default => 'gray',
        };
    }
public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Security\BexiaTenantPermission::can('inventory.menu.view');
    }

}
