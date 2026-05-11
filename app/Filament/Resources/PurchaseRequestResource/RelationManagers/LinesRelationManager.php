<?php

namespace App\Filament\Resources\PurchaseRequestResource\RelationManagers;

use App\Filament\Resources\PurchaseRequestResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Productos';

    protected static ?string $modelLabel = 'producto';

    protected static ?string $pluralModelLabel = 'productos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(12)
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Producto')
                            ->options([])
                            ->searchable()
                            ->searchDebounce(700)
                            ->getSearchResultsUsing(fn (string $search): array => static::productSearchOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => $value ? static::productLabel($value) : null)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $set('product_variant_id', null);

                                if (! $state) {
                                    return;
                                }

                                $set('product_label', static::productLabel($state));
                                $set('variant_label', '—');
                                $set('unit_cost_without_tax', static::productCostWithoutTax($state));
                                $set('tax_rate', static::normalizeTaxRateOptionKey(static::productPurchaseTaxRate($state)));
                            })
                            ->columnSpan(5),

                        Forms\Components\Select::make('product_variant_id')
                            ->label('Variante')
                            ->options([])
                            ->searchable()
                            ->searchDebounce(700)
                            ->getSearchResultsUsing(fn (string $search, Get $get): array => static::variantSearchOptions($get('product_id'), $search))
                            ->getOptionLabelUsing(fn ($value): ?string => $value ? static::productLabel($value, true) : null)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                if (! $state) {
                                    $set('variant_label', '—');
                                    return;
                                }

                                $set('variant_label', static::productLabel($state, true));
                                $set('unit_cost_without_tax', static::productCostWithoutTax($state));
                                $set('tax_rate', static::normalizeTaxRateOptionKey(static::productPurchaseTaxRate($state)));
                            })
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('requested_quantity')
                            ->label('Cantidad')
                            ->numeric()
                            ->minValue(0.000001)
                            ->default(1)
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('unit_cost_without_tax')
                            ->label('Costo s/IVA')
                            ->prefix('$')
                            ->numeric()
                            ->step('0.0001')
                            ->default(0)
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\Select::make('tax_rate')
                            ->label('Impuesto')
                            ->options(fn (): array => static::purchaseTaxOptions())
                            ->getOptionLabelUsing(fn ($value): ?string => static::purchaseTaxLabel($value))
                            ->searchable()
                            ->native(false)
                            ->default(fn (): string => static::normalizeTaxRateOptionKey(16))
                            ->required()
                            ->columnSpan(3),

                        Forms\Components\Hidden::make('product_label')
                            ->dehydrated(true),

                        Forms\Components\Hidden::make('variant_label')
                            ->default('—')
                            ->dehydrated(true),

                        Forms\Components\Hidden::make('available_quantity')
                            ->default(0)
                            ->dehydrated(true),

                        Forms\Components\Hidden::make('suggested_quantity')
                            ->default(0)
                            ->dehydrated(true),

                        Forms\Components\Hidden::make('pending_quantity')
                            ->default(0)
                            ->dehydrated(true),

                        Forms\Components\Hidden::make('unit_cost_with_tax')
                            ->default(0)
                            ->dehydrated(true),

                        Forms\Components\Hidden::make('line_total_without_tax')
                            ->default(0)
                            ->dehydrated(true),

                        Forms\Components\Hidden::make('line_tax')
                            ->default(0)
                            ->dehydrated(true),

                        Forms\Components\Hidden::make('line_total_with_tax')
                            ->default(0)
                            ->dehydrated(true),

                        Forms\Components\Hidden::make('priority')
                            ->default('normal')
                            ->dehydrated(true),

                        Forms\Components\Hidden::make('priority_label')
                            ->default('Normal')
                            ->dehydrated(true),

                        Forms\Components\Hidden::make('cost_source')
                            ->default('Manual')
                            ->dehydrated(true),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Productos')
            ->description('Agrega o edita productos.')
            ->columns([
                Tables\Columns\TextColumn::make('product_label')
                    ->label('Producto')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('variant_label')
                    ->label('Variante')
                    ->placeholder('—')
                    ->wrap(),

                Tables\Columns\TextColumn::make('requested_quantity')
                    ->label('Cantidad')
                    ->numeric(decimalPlaces: 2)
                    ->alignRight(),

                Tables\Columns\TextColumn::make('unit_cost_without_tax')
                    ->label('Costo s/IVA')
                    ->money('MXN')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('tax_rate')
                    ->label('IVA')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2) . '%')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('unit_cost_with_tax')
                    ->label('Costo c/IVA')
                    ->money('MXN')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('line_total_with_tax')
                    ->label('Importe')
                    ->money('MXN')
                    ->alignRight(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar producto')
                    ->modalHeading('Agregar producto')
                    ->mutateFormDataUsing(fn (array $data): array => static::normalizeLineData($data))
                    ->after(function (): void {
                        PurchaseRequestResource::recalculateTotals($this->getOwnerRecord());
                        $this->dispatch('$refresh');
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->modalHeading('Editar producto')
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['tax_rate'] = static::normalizeTaxRateOptionKey($data['tax_rate'] ?? 16);

                        return $data;
                    })
                    ->mutateFormDataUsing(fn (array $data): array => static::normalizeLineData($data))
                    ->after(function (): void {
                        PurchaseRequestResource::recalculateTotals($this->getOwnerRecord());
                        $this->dispatch('$refresh');
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->after(function (): void {
                        PurchaseRequestResource::recalculateTotals($this->getOwnerRecord());
                        $this->dispatch('$refresh');
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('id');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->exists;
    }

    protected static function normalizeLineData(array $data): array
    {
        $quantity = (float) ($data['requested_quantity'] ?? 0);
        $unitWithoutTax = (float) ($data['unit_cost_without_tax'] ?? 0);
        $taxRate = (float) ($data['tax_rate'] ?? 0);

        $unitWithTax = round($unitWithoutTax * (1 + ($taxRate / 100)), 6);
        $lineWithoutTax = round($quantity * $unitWithoutTax, 6);
        $lineWithTax = round($quantity * $unitWithTax, 6);

        $data['product_label'] = $data['product_label'] ?? static::productLabel($data['product_id'] ?? null);
        $data['variant_label'] = ! empty($data['product_variant_id'])
            ? static::productLabel($data['product_variant_id'], true)
            : ($data['variant_label'] ?? '—');

        $data['available_quantity'] = (float) ($data['available_quantity'] ?? 0);
        $data['suggested_quantity'] = (float) ($data['suggested_quantity'] ?? 0);
        $data['pending_quantity'] = (float) ($data['pending_quantity'] ?? 0);

        $data['unit_cost_with_tax'] = $unitWithTax;
        $data['line_total_without_tax'] = $lineWithoutTax;
        $data['line_tax'] = max(0, round($lineWithTax - $lineWithoutTax, 6));
        $data['line_total_with_tax'] = $lineWithTax;

        $data['priority'] = $data['priority'] ?? 'normal';
        $data['priority_label'] = $data['priority_label'] ?? 'Normal';
        $data['cost_source'] = $data['cost_source'] ?? 'Manual';

        return $data;
    }

    protected static function productSearchOptions(string $search = ''): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $query = DB::table('products');

        if (Schema::hasColumn('products', 'parent_product_id')) {
            $query->whereNull('parent_product_id');
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        $search = trim($search);

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                foreach (['internal_reference', 'name', 'sku', 'barcode', 'code'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $query->orWhere($column, 'ilike', '%' . $search . '%');
                    }
                }
            });
        }

        return $query
            ->orderBy(Schema::hasColumn('products', 'internal_reference') ? 'internal_reference' : 'name')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn ($product): array => [
                $product->id => static::productLabelFromRow($product),
            ])
            ->all();
    }

    protected static function variantSearchOptions($productId, string $search = ''): array
    {
        if (! $productId || ! Schema::hasTable('products') || ! Schema::hasColumn('products', 'parent_product_id')) {
            return [];
        }

        $query = DB::table('products')
            ->where('parent_product_id', $productId);

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        $search = trim($search);

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                foreach (['internal_reference', 'name', 'sku', 'barcode', 'variant_group', 'variant_value'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $query->orWhere($column, 'ilike', '%' . $search . '%');
                    }
                }
            });
        }

        return $query
            ->orderBy(Schema::hasColumn('products', 'variant_value') ? 'variant_value' : 'name')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn ($product): array => [
                $product->id => static::productLabelFromRow($product, true),
            ])
            ->all();
    }

    protected static function productLabel($productId, bool $variant = false): string
    {
        if (! $productId || ! Schema::hasTable('products')) {
            return '—';
        }

        $product = DB::table('products')->where('id', $productId)->first();

        return $product ? static::productLabelFromRow($product, $variant) : 'Producto #' . $productId;
    }

    protected static function productLabelFromRow(object $product, bool $variant = false): string
    {
        $reference = '';
        foreach (['internal_reference', 'reference', 'code', 'sku'] as $column) {
            if (property_exists($product, $column) && trim((string) $product->{$column}) !== '') {
                $reference = trim((string) $product->{$column});
                break;
            }
        }

        if ($variant) {
            $variantParts = [];

            foreach (['variant_group', 'variant_value', 'name'] as $column) {
                if (property_exists($product, $column) && trim((string) $product->{$column}) !== '') {
                    $variantParts[] = trim((string) $product->{$column});
                }
            }

            $name = implode(' - ', array_unique($variantParts));
        } else {
            $name = property_exists($product, 'name') ? trim((string) $product->name) : '';
        }

        $label = trim(($reference ? $reference . ' - ' : '') . ($name ?: 'Producto #' . $product->id));

        return $label ?: 'Producto #' . $product->id;
    }

    protected static function productCostWithoutTax($productId): float
    {
        if (! $productId || ! Schema::hasTable('products')) {
            return 0.0;
        }

        $product = DB::table('products')->where('id', $productId)->first();

        if (! $product) {
            return 0.0;
        }

        foreach ([
            'average_cost_without_tax',
            'current_average_cost_without_tax',
            'avg_cost_without_tax',
            'purchase_cost_without_tax',
            'purchase_price_without_tax',
            'purchase_cost',
            'purchase_price',
            'cost',
            'standard_price',
        ] as $column) {
            if (property_exists($product, $column) && is_numeric($product->{$column})) {
                return (float) $product->{$column};
            }
        }

        return 0.0;
    }

    protected static function productPurchaseTaxRate($productId): float
    {
        if (! $productId || ! Schema::hasTable('products')) {
            return 16.0;
        }

        $product = DB::table('products')->where('id', $productId)->first();

        if (! $product) {
            return 16.0;
        }

        foreach ([
            'purchase_tax_rate',
            'tax_purchase_rate',
            'purchase_vat_rate',
            'iva_compra',
            'tax_rate',
        ] as $column) {
            if (property_exists($product, $column) && is_numeric($product->{$column})) {
                return (float) $product->{$column};
            }
        }

        return 16.0;
    }

    protected static function purchaseTaxOptions(): array
    {
        return [
            static::normalizeTaxRateOptionKey(0) => 'Exento (0%)',
            static::normalizeTaxRateOptionKey(8) => 'IVA 8% (8.00%)',
            static::normalizeTaxRateOptionKey(16) => 'IVA 16% (16.00%)',
        ];
    }

    protected static function purchaseTaxLabel($rate): string
    {
        $key = static::normalizeTaxRateOptionKey($rate);
        $options = static::purchaseTaxOptions();

        return $options[$key] ?? ('IVA ' . $key . '%');
    }

    protected static function normalizeTaxRateOptionKey($rate): string
    {
        $rate = is_numeric($rate) ? (float) $rate : 0.0;

        return rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.') ?: '0';
    }
}
