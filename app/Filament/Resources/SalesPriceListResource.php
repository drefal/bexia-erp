<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesPriceListResource\Pages;
use App\Models\SalesPriceList;
use App\Models\SalesPriceListItem;
use App\Support\PublicPageAnalytics;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class SalesPriceListResource extends Resource
{
    protected static ?string $model = SalesPriceList::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationGroup = 'Productos';

    protected static ?string $navigationLabel = 'Listas de precios';

    protected static ?int $navigationSort = 40;
protected static ?string $modelLabel = 'lista de precios';

    protected static ?string $pluralModelLabel = 'listas de precios';

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    public static function userCanPermission(string $permission): bool
    {
        $user = Filament::auth()->user() ?: auth()->user();

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

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.salespricelistresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
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

        return $user->can('sales.view')
            || $user->can('sales.create')
            || $user->can('sales.update')
            || $user->can('ventas.configurar');
    }


    public static function canCreate(): bool
    {
        return static::userCanPermission('sales.configure_prices') || static::userCanPermission('sales.configure');
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::userCanPermission('sales.view') || static::userCanPermission('sales.configure_prices') || static::userCanPermission('sales.configure');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::userCanPermission('sales.configure_prices') || static::userCanPermission('sales.configure');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::userCanPermission('sales.configure_prices') || static::userCanPermission('sales.configure');
    }


    /*
     * BEXIA_SALES_PRICE_LIST_RESOURCE_RESPONSIVE_V5_79_42C
     * Visual-only responsive marker.
     */

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la lista')
                    ->extraAttributes(['class' => 'bexia-spl-section bexia-spl-section-main'])
                    ->columns(4)
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-code'])
                            ->label('Código')
                            ->maxLength(50)
                            ->placeholder('PUBLICO'),

                        Forms\Components\TextInput::make('name')
                            ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-name'])
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Precio público'),

                        Forms\Components\TextInput::make('currency')
                            ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-curr'])
                            ->label('Moneda')
                            ->default('MXN')
                            ->maxLength(8),

                        Forms\Components\Select::make('calculation_type')
                            ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-mode'])
                            ->label('Tipo de lista')
                            ->default('items')
                            ->reactive()
                            ->options([
                                'items' => 'Lista de productos',
                                'formula' => 'Fórmula sobre otra lista',
                            ])
                            ->helperText('Los precios siempre se manejan sin impuestos.'),

                        Forms\Components\Select::make('formula_basis')
                            ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-basis'])
                            ->label('Base de fórmula')
                            ->default('price_list')
                            ->reactive()
                            ->options([
                                'price_list' => 'Otra lista de precios',
                                'product_cost' => 'Costo del producto',
                            ])
                            ->visible(fn (Forms\Get $get): bool => ($get('calculation_type') ?? 'items') === 'formula')
                            ->dehydrated(fn (Forms\Get $get): bool => ($get('calculation_type') ?? 'items') === 'formula')
                            ->helperText('Todos los cálculos son sin impuestos.'),

                        Forms\Components\Select::make('base_price_list_id')
                            ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-base-ref'])
                            ->label('Lista base')
                            ->searchable()
                            ->options(fn (): array => static::basePriceListOptions())
                            ->visible(fn (Forms\Get $get): bool => ($get('calculation_type') ?? 'items') === 'formula' && ($get('formula_basis') ?? 'price_list') === 'price_list')
                            ->dehydrated(fn (Forms\Get $get): bool => ($get('calculation_type') ?? 'items') === 'formula' && ($get('formula_basis') ?? 'price_list') === 'price_list')
                            ->helperText('La fórmula toma el precio sin impuestos de esta lista.'),

                        Forms\Components\TextInput::make('adjustment_percent')
                            ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-adjust'])
                            ->label('Ajuste %')
                            ->numeric()
                            ->default(0)
                            ->visible(fn (Forms\Get $get): bool => ($get('calculation_type') ?? 'items') === 'formula')
                            ->dehydrated(fn (Forms\Get $get): bool => ($get('calculation_type') ?? 'items') === 'formula')
                            ->helperText('Ejemplo: -10 = 10% menos, 5 = 5% más.'),

                        Forms\Components\Toggle::make('is_default')
                            ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-main-flag'])
                            ->label('Lista predeterminada'),

                        Forms\Components\Toggle::make('is_active')
                            ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-enabled'])
                            ->label('Activa')
                            ->default(true),

                        Forms\Components\DatePicker::make('valid_from')
                            ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-date-from'])
                            ->label('Válida desde'),

                        Forms\Components\DatePicker::make('valid_to')
                            ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-date-to'])
                            ->label('Válida hasta'),

                        Forms\Components\Textarea::make('notes')
                            ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-notes'])
                            ->label('Notas')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                /*
                 * BEXIA_PUBLIC_CALCULATOR_V5_83_0B
                 * Metadatos para publicar listas de financiamiento.
                 */
                Forms\Components\Section::make('Calculadora pública de financiamiento')
                    ->description('Permite utilizar esta lista como plan de pago en una calculadora pública.')
                    ->columns(4)
                    ->schema([
                        Forms\Components\Select::make('payment_provider')
                            ->label('Proveedor de pago')
                            ->options([
                                'mercado_pago' => 'Mercado Pago',
                            ])
                            ->placeholder('No aplica')
                            ->reactive()
                            ->required(
                                fn (Forms\Get $get): bool =>
                                    (bool) $get('public_calculator')
                            ),

                        Forms\Components\TextInput::make('installment_months')
                            ->label('Plazo (meses)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(120)
                            ->required(
                                fn (Forms\Get $get): bool =>
                                    (bool) $get('public_calculator')
                            )
                            ->helperText('Ejemplo: 3, 6, 9, 12, 18 o 24.'),

                        /*
                         * BEXIA_MP_FINANCING_V5_83_2A2
                         * adjustment_percent conserva la tasa total
                         * histórica de crédito.
                         */
                        Forms\Components\TextInput::make(
                            'financing_percent'
                        )
                            ->label(
                                'Financiamiento adicional (%)'
                            )
                            ->numeric()
                            ->step('0.0001')
                            ->minValue(0)
                            ->maxValue(99)
                            ->suffix('%')
                            ->reactive()
                            ->helperText(
                                'No incluye deslizamiento. ' .
                                'Crédito agrega 2.19% y débito 1.69%.'
                            )
                            ->required(
                                fn (Forms\Get $get): bool =>
                                    (bool) $get(
                                        'public_calculator'
                                    )
                            )
                            ->afterStateUpdated(
                                function (
                                    $state,
                                    Forms\Set $set
                                ): void {
                                    if (
                                        $state === null
                                        || $state === ''
                                    ) {
                                        return;
                                    }

                                    $financing =
                                        (float) $state;

                                    /*
                                     * Mantener adjustment_percent
                                     * compatible con la tasa total
                                     * de crédito usada históricamente.
                                     */
                                    $set(
                                        'adjustment_percent',
                                        round(
                                            $financing
                                            + 2.1900,
                                            4
                                        )
                                    );
                                }
                            ),

                        Forms\Components\Placeholder::make(
                            'public_rate_preview'
                        )
                            ->label(
                                'Tasas calculadas'
                            )
                            ->content(
                                function (
                                    Forms\Get $get
                                ): string {
                                    $financing =
                                        (float) (
                                            $get(
                                                'financing_percent'
                                            )
                                            ?? 0
                                        );

                                    return sprintf(
                                        'Crédito %.4f%% · Débito %.4f%%',
                                        $financing + 2.1900,
                                        $financing + 1.6900
                                    );
                                }
                            ),

                        Forms\Components\Toggle::make('public_calculator')
                            ->label('Mostrar en calculadora pública')
                            ->default(false)
                            ->reactive()
                            ->helperText('Solo se publican listas activas tipo fórmula sobre otra lista.'),

                        Forms\Components\TextInput::make('public_sort')
                            ->label('Orden público')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999)
                            ->default(0),

                        /*
                         * BEXIA_PUBLIC_ANALYTICS_STATS_V5_83_1A
                         */
                        Forms\Components\Placeholder::make(
                            'public_calculator_stats'
                        )
                            ->label('Estadísticas de uso')
                            ->columnSpanFull()
                            ->visible(
                                fn (
                                    Forms\Get $get,
                                    ?SalesPriceList $record
                                ): bool =>
                                    $record !== null
                                    && (bool) $get(
                                        'public_calculator'
                                    )
                                    && $get(
                                        'payment_provider'
                                    ) === 'mercado_pago'
                            )
                            ->content(
                                function (
                                    ?SalesPriceList $record
                                ): HtmlString {
                                    if (
                                        ! $record
                                        || ! $record->company_id
                                    ) {
                                        return new HtmlString(
                                            'Sin estadísticas.'
                                        );
                                    }

                                    $stats =
                                        app(
                                            PublicPageAnalytics::class
                                        )->summary(
                                            (int)
                                            $record->company_id,
                                            PublicPageAnalytics::
                                                MERCADO_PAGO_CALCULATOR
                                        );

                                    $card = static function (
                                        string $title,
                                        array $data
                                    ): string {
                                        return sprintf(
                                            '<div style="' .
                                            'border:1px solid #d1d5db;' .
                                            'border-radius:10px;' .
                                            'padding:12px 14px;' .
                                            'line-height:1.65;' .
                                            '">' .
                                            '<strong>%s</strong><br>' .
                                            'Visitas: <strong>%s</strong><br>' .
                                            'Visitantes únicos: ' .
                                            '<strong>%s</strong><br>' .
                                            'PDF descargados: ' .
                                            '<strong>%s</strong>' .
                                            '</div>',
                                            e($title),
                                            number_format(
                                                $data['views']
                                            ),
                                            number_format(
                                                $data['unique']
                                            ),
                                            number_format(
                                                $data['pdf']
                                            )
                                        );
                                    };

                                    return new HtmlString(
                                        '<div style="' .
                                        'display:grid;' .
                                        'grid-template-columns:' .
                                        'repeat(auto-fit,' .
                                        'minmax(190px,1fr));' .
                                        'gap:12px;' .
                                        '">' .
                                        $card(
                                            'Hoy',
                                            $stats['today']
                                        ) .
                                        $card(
                                            'Últimos 30 días',
                                            $stats['last30']
                                        ) .
                                        $card(
                                            'Acumulado',
                                            $stats['all']
                                        ) .
                                        '</div>' .
                                        '<div style="' .
                                        'margin-top:8px;' .
                                        'font-size:12px;' .
                                        'opacity:.7;' .
                                        '">' .
                                        'Los visitantes únicos son ' .
                                        'navegadores identificados de ' .
                                        'forma anónima. No se almacena ' .
                                        'la dirección IP.' .
                                        '</div>'
                                    );
                                }
                            ),
                    ]),

                Forms\Components\Section::make('Precios por producto')
                    ->extraAttributes(['class' => 'bexia-spl-section bexia-spl-section-lines'])
                    ->visible(fn (Forms\Get $get): bool => ($get('calculation_type') ?? 'items') === 'items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->extraAttributes(['class' => 'bexia-spl-repeater bexia-spl-repeater-lines'])
                            ->label('Productos')
                            ->relationship('items')
                            ->columns(12)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar producto')
                            ->reorderable(false)
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-item'])
                                    ->label('Producto')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(4)
                                    ->options(fn (): array => static::initialProductOptions())
                                    ->getSearchResultsUsing(fn (string $search): array => static::productSearchOptions($search))
                                    ->getOptionLabelUsing(fn ($value): ?string => static::productLabel((int) $value))
                                    ->reactive()
                                    ->afterStateHydrated(function (Forms\Components\Select $component, $state, ?SalesPriceListItem $record): void {
                                        if ($record && $record->product_id) {
                                            $component->state((int) $record->product_id);
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                        $productId = (int) ($state ?? 0);

                                        $set('product_variant_id', null);

                                        $info = static::productPriceInfo($productId, 0);

                                        $set('price_without_tax', $info['sale_price']);
                                        $set('tax_rate', 0);
                                        $set('price_with_tax', $info['sale_price']);
                                        $set('discount_percent', 0);
                                    }),

                                Forms\Components\Select::make('product_variant_id')
                                    ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-variant'])
                                    ->label('Variante')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(3)
                                    ->options(fn (Forms\Get $get): array => static::variantOptions((int) ($get('product_id') ?? 0)))
                                    ->visible(fn (Forms\Get $get): bool => static::productHasVariants((int) ($get('product_id') ?? 0)))
                                    ->required(fn (Forms\Get $get): bool => static::productHasVariants((int) ($get('product_id') ?? 0)))
                                    ->reactive()
                                    ->afterStateHydrated(function (Forms\Components\Select $component, $state, ?SalesPriceListItem $record): void {
                                        if ($record && $record->product_variant_id) {
                                            $component->state((int) $record->product_variant_id);
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                        $productId = (int) ($get('product_id') ?? 0);
                                        $variantId = (int) ($state ?? 0);

                                        $info = static::productPriceInfo($productId, $variantId);

                                        $set('price_without_tax', $info['sale_price']);
                                        $set('tax_rate', 0);
                                        $set('price_with_tax', $info['sale_price']);
                                        $set('discount_percent', 0);
                                    }),

                                Forms\Components\TextInput::make('min_quantity')
                                    ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-min-qty'])
                                    ->label('Cantidad mínima')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.000001)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('price_without_tax')
                                    ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-amount'])
                                    ->label('Precio sin impuestos')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->minValue(0)
                                    ->columnSpan(2),

                                Forms\Components\Toggle::make('is_active')
                                    ->extraAttributes(['class' => 'bexia-spl-field bexia-spl-field-enabled'])
                                    ->label('Activo')
                                    ->default(true)
                                    ->columnSpan(1),

                                Forms\Components\Hidden::make('tax_rate')
                                    ->default(0)
                                    ->dehydrated(true),

                                Forms\Components\Hidden::make('price_with_tax')
                                    ->dehydrated(true),

                                Forms\Components\Hidden::make('discount_percent')
                                    ->default(0)
                                    ->dehydrated(true),
                            ])
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => static::prepareItemData($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => static::prepareItemData($data)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes(['class' => 'bexia-spl-col-code bexia-spl-col-key'])
                    ->extraCellAttributes(['class' => 'bexia-spl-col-code bexia-spl-col-key'])
                    ->label('Código')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes(['class' => 'bexia-spl-col-name bexia-spl-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-spl-col-name bexia-spl-col-primary'])
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('calculation_type')
                    ->extraHeaderAttributes(['class' => 'bexia-spl-col-mode'])
                    ->extraCellAttributes(['class' => 'bexia-spl-col-mode'])
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => match ((string) $state) {
                        'items' => 'Productos',
                        'formula' => 'Fórmula',
                        default => (string) $state,
                    }),

                Tables\Columns\TextColumn::make('basePriceList.name')
                    ->extraHeaderAttributes(['class' => 'bexia-spl-col-base-ref'])
                    ->extraCellAttributes(['class' => 'bexia-spl-col-base-ref'])
                    ->label('Lista base')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('formula_basis')
                    ->extraHeaderAttributes(['class' => 'bexia-spl-col-basis'])
                    ->extraCellAttributes(['class' => 'bexia-spl-col-basis'])
                    ->label('Base fórmula')
                    ->formatStateUsing(fn (?string $state): string => match ((string) $state) {
                        'product_cost' => 'Costo producto',
                        'price_list' => 'Lista precio',
                        default => (string) $state,
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('adjustment_percent')
                    ->extraHeaderAttributes(['class' => 'bexia-spl-col-adjust bexia-spl-col-numeric'])
                    ->extraCellAttributes(['class' => 'bexia-spl-col-adjust bexia-spl-col-numeric'])
                    ->label('Ajuste %')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2) . '%')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->extraHeaderAttributes(['class' => 'bexia-spl-col-main-flag'])
                    ->extraCellAttributes(['class' => 'bexia-spl-col-main-flag'])
                    ->label('Default')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes(['class' => 'bexia-spl-col-enabled'])
                    ->extraCellAttributes(['class' => 'bexia-spl-col-enabled'])
                    ->label('Activa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('items_count')
                    ->extraHeaderAttributes(['class' => 'bexia-spl-col-lines bexia-spl-col-numeric'])
                    ->extraCellAttributes(['class' => 'bexia-spl-col-lines bexia-spl-col-numeric'])
                    ->label('Productos')
                    ->counts('items'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->extraHeaderAttributes(['class' => 'bexia-spl-col-updated'])
                    ->extraCellAttributes(['class' => 'bexia-spl-col-updated'])
                    ->label('Actualizada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    protected static function tenantCompanyId(): int
    {
        if (Filament::getTenant()) {
            return (int) Filament::getTenant()->getKey();
        }

        return (int) (request()->route('tenant') ?? auth()->user()?->company_id ?? 0);
    }

    protected static function basePriceListOptions(): array
    {
        if (! Schema::hasTable('sales_price_lists')) {
            return [];
        }

        $companyId = static::tenantCompanyId();

        return DB::table('sales_price_lists')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function initialProductOptions(): array
    {
        return static::productSearchOptions('', 50);
    }

    protected static function productSearchOptions(string $search, int $limit = 80): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $companyId = static::tenantCompanyId();
        $search = trim($search);

        $variantParentIds = collect();

        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';

            $variantParentIds = DB::table('products')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->where('is_variant', true)
                ->whereNotNull('parent_product_id')
                ->where(function ($q) use ($like) {
                    foreach (['internal_reference', 'sku', 'barcode', 'name', 'variant_name', 'variant_value', 'color'] as $column) {
                        if (Schema::hasColumn('products', $column)) {
                            $q->orWhereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", [$like]);
                        }
                    }
                })
                ->pluck('parent_product_id');
        }

        $query = DB::table('products')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('can_be_sold', true)
            ->where(function ($q) {
                $q->whereNull('is_variant')
                    ->orWhere('is_variant', false);
            });

        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';

            $query->where(function ($q) use ($like, $variantParentIds) {
                foreach (['internal_reference', 'sku', 'barcode', 'name', 'model', 'brand', 'color', 'product_line'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $q->orWhereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", [$like]);
                    }
                }

                if ($variantParentIds->isNotEmpty()) {
                    $q->orWhereIn('id', $variantParentIds->unique()->values()->all());
                }
            });
        }

        return $query
            ->orderByRaw("COALESCE(internal_reference, sku, barcode, '')")
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn ($product): array => [
                (int) $product->id => static::productLabel((int) $product->id),
            ])
            ->all();
    }

    protected static function productLabel(int $productId): string
    {
        $product = $productId > 0
            ? DB::table('products')->where('id', $productId)->first()
            : null;

        if (! $product) {
            return 'Producto #' . $productId;
        }

        $ref = trim((string) ($product->internal_reference ?? $product->sku ?? $product->barcode ?? ''));
        $name = trim((string) ($product->name ?? ('Producto #' . $productId)));

        return trim(($ref !== '' ? $ref . ' - ' : '') . $name);
    }

    protected static function productHasVariants(int $productId): bool
    {
        if ($productId <= 0 || ! Schema::hasTable('products')) {
            return false;
        }

        return DB::table('products')
            ->where('parent_product_id', $productId)
            ->where('is_variant', true)
            ->where('is_active', true)
            ->exists();
    }

    protected static function variantOptions(int $productId): array
    {
        if ($productId <= 0 || ! Schema::hasTable('products')) {
            return [];
        }

        return DB::table('products')
            ->where('parent_product_id', $productId)
            ->where('is_variant', true)
            ->where('is_active', true)
            ->orderByRaw("COALESCE(variant_group, '')")
            ->orderByRaw("COALESCE(variant_value, variant_name, name, '')")
            ->get()
            ->mapWithKeys(fn ($variant): array => [
                (int) $variant->id => static::variantLabel($variant),
            ])
            ->all();
    }

    protected static function variantLabel(object $variant): string
    {
        $group = trim((string) ($variant->variant_group ?? ''));
        $value = '';

        foreach (['variant_value', 'variant_name', 'color'] as $column) {
            if (property_exists($variant, $column) && trim((string) $variant->{$column}) !== '') {
                $value = trim((string) $variant->{$column});
                break;
            }
        }

        if ($value === '') {
            $value = trim((string) ($variant->name ?? ''));
        }

        if ($value === '') {
            return 'Variante #' . $variant->id;
        }

        return $group !== '' ? $group . ': ' . $value : $value;
    }

    protected static function productPriceInfo(int $productId, int $variantId = 0): array
    {
        $product = $productId > 0
            ? DB::table('products')->where('id', $productId)->first()
            : null;

        $variant = $variantId > 0
            ? DB::table('products')->where('id', $variantId)->first()
            : null;

        $salePrice = 0.0;

        if ($product) {
            $salePrice = (float) ($product->sale_price ?? 0);
        }

        if ($variant && $variant->sale_price !== null) {
            $salePrice = (float) $variant->sale_price;
        }

        return [
            'sale_price' => $salePrice,
        ];
    }

    protected static function prepareItemData(array $data): array
    {
        $data['company_id'] = static::tenantCompanyId();

        if (empty($data['product_variant_id'])) {
            $data['product_variant_id'] = null;
        }

        $price = (float) ($data['price_without_tax'] ?? 0);

        $data['tax_rate'] = 0;
        $data['price_with_tax'] = $price;
        $data['discount_percent'] = 0;

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesPriceLists::route('/'),
            'create' => Pages\CreateSalesPriceList::route('/create'),
            'edit' => Pages\EditSalesPriceList::route('/{record}/edit'),
        ];
    }
}
