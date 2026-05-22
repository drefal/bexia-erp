<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PosPointResource\Pages;
use App\Models\PosPoint;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PosPointResource extends Resource
{
    protected static ?string $model = PosPoint::class;

    protected static ?string $navigationGroup = 'Punto de Venta';

    protected static ?string $navigationLabel = 'Configuración PDV';

    protected static ?string $modelLabel = 'punto de venta';

    protected static ?string $pluralModelLabel = 'puntos de venta';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 70;

    protected static bool $isScopedToTenant = false;

public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('pos_points', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    public static function shouldRegisterNavigation(): bool
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
                Forms\Components\Tabs::make('Configuración de punto de venta')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('General')
                            ->schema([
                                Forms\Components\Section::make('Datos principales')
                                    ->columns(3)
                                    ->schema([
Forms\Components\TextInput::make('name')
                                            ->label('Nombre del punto de venta')
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('code')
                                            ->label('Código')
                                            ->maxLength(80),

                                        Forms\Components\Select::make('status')
                                            ->label('Estado')
                                            ->options([
                                                'active' => 'Activo',
                                                'inactive' => 'Inactivo',
                                            ])
                                            ->default('active')
                                            ->required(),

                                        Forms\Components\Select::make('box_type')
                                            ->label('Tipo de caja')
                                            ->options([
                                                'seller' => 'Vendedor: genera ticket sin cobrar',
                                                'cashier' => 'Cajero: cobra tickets',
                                                'mixed' => 'Mixta: vende y cobra',
                                            ])
                                            ->default('mixed')
                                            ->required(),

                                        Forms\Components\Select::make('company_id')
                                            ->label('Empresa')
                                            ->options(fn () => static::optionsFromTable('companies', ['name']))
                                            ->default(fn () => static::currentCompanyId())
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Select::make('warehouse_id')
                                            ->label('Almacén')
                                            ->options(fn () => static::optionsFromTable('warehouses', ['name', 'code']))
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Select::make('stock_location_id')
                                            ->label('Ubicación / sucursal')
                                            ->options(fn () => static::optionsFromTable('stock_locations', ['name', 'code']))
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Textarea::make('notes')
                                            ->label('Notas')
                                            ->columnSpanFull()
                                            ->rows(3),
                                    ]),

                                Forms\Components\Section::make('Apartados / anticipos')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('allow_partial_payment')
                                            ->label('Permitir pago parcial')
                                            ->helperText('Permite registrar apartados o anticipos.'),

                                        Forms\Components\Select::make('partial_payment_product_id')
                                            ->label('Producto para apartado / anticipo')
                                            ->options(fn () => static::optionsFromTable('products', ['name', 'sku', 'barcode']))
                                            ->searchable()
                                            ->preload(),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Interfaz')
                            ->schema([
                                Forms\Components\Section::make('Interfaz de PDV')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('multiple_cashiers_per_session')
                                            ->label('Múltiples cajeros por sesión')
                                            ->helperText('Permite cambiar entre cajeros autorizados durante la sesión.')
                                            ->default(true),

                                        Forms\Components\Placeholder::make('cashiers_help')
                                            ->label('Cajeros permitidos')
                                            ->content('Los cajeros se configuran en Punto de Venta > Cajeros PDV. Cada cajero se liga al punto de venta.'),


                                        Forms\Components\Select::make('default_pos_category_mode')
                                            ->label('Categoría inicial en PDV')
                                            ->options([
                                                'favorites' => 'Favoritos',
                                                'top_sellers' => 'Más vendidos',
                                                'all' => 'Todos',
                                                'category' => 'Categoría específica',
                                            ])
                                            ->default('favorites')
                                            ->helperText('Define qué filtro se abre automáticamente al entrar al Punto de Venta.')
                                            ->native(false)
                                            ->live(),
                                        Forms\Components\Select::make('initial_category_id')
                                            ->visible(fn ($get): bool => $get('default_pos_category_mode') === 'category')
                                            ->label('Categoría específica inicial')
                                            ->helperText('Solo se usa cuando seleccionas "Categoría específica" en el campo anterior.')
                                            ->options(fn () => static::categoryOptions())
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Toggle::make('restrict_categories')
                                            ->label('Restringir categorías'),

                                        Forms\Components\Select::make('allowed_category_ids')
                                            ->label('Categorías permitidas')
                                            ->multiple()
                                            ->options(fn () => static::categoryOptions())
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Select::make('default_customer_id')
                                            ->label('Cliente predeterminado')
                                            ->options(fn () => static::customerOptions())
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Toggle::make('show_product_info')
                                            ->label('Mostrar información de producto')
                                            ->default(true),

                                        Forms\Components\Toggle::make('hide_cost')
                                            ->label('Ocultar costo')
                                            ->default(true),

                                        Forms\Components\Toggle::make('hide_margin')
                                            ->label('Ocultar margen')
                                            ->default(true),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Existencias')
                            ->schema([
                                Forms\Components\Section::make('Configuración de existencias')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('show_stock')
                                            ->label('Mostrar existencias en PDV')
                                            ->default(true),

                                        Forms\Components\Select::make('stock_display_type')
                                            ->label('Tipo de existencia')
                                            ->options([
                                                'on_hand' => 'Cantidad física',
                                                'available' => 'Cantidad disponible',
                                            ])
                                            ->default('on_hand'),

                                        Forms\Components\Toggle::make('allow_out_of_stock_sales')
                                            ->label('Permitir vender sin existencia')
                                            ->helperText('Si está apagado, el PDV bloqueará productos sin stock suficiente.'),

                                        Forms\Components\TextInput::make('deny_sale_below_qty')
                                            ->label('Denegar venta debajo de')
                                            ->numeric()
                                            ->default(0),

                                        Forms\Components\Select::make('stock_scope')
                                            ->label('Mostrar existencias de')
                                            ->options([
                                                'current_warehouse' => 'Almacén actual',
                                                'all_warehouses' => 'Todos los almacenes',
                                            ])
                                            ->default('current_warehouse'),

                                        Forms\Components\Select::make('stock_source_location_id')
                                            ->label('Ubicación de existencias')
                                            ->options(fn () => static::optionsFromTable('stock_locations', ['name', 'code']))
                                            ->searchable()
                                            ->preload(),
                                    ]),

                                Forms\Components\Section::make('Pedidos del PDV')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('show_pos_orders')
                                            ->label('Mostrar pedidos de PDV')
                                            ->default(true),

                                        Forms\Components\Select::make('session_limit_mode')
                                            ->label('Límite de sesiones')
                                            ->options([
                                                'current_day' => 'Solo pedidos del día en curso',
                                                'current_session' => 'Solo sesión actual',
                                                'all' => 'Cargar todo',
                                            ])
                                            ->default('current_day'),

                                        Forms\Components\Toggle::make('show_draft_orders')
                                            ->label('Mostrar pedidos en borrador / nuevo')
                                            ->default(true),

                                        Forms\Components\Toggle::make('show_published_orders')
                                            ->label('Mostrar pedidos publicados'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Precios')
                            ->schema([
                                Forms\Components\Section::make('Listas de precios')
                                    ->description('Las listas se toman del módulo de listas de precios. El PDV podrá usar una lista predeterminada y permitir cambiarla según cliente o venta.')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Select::make('available_price_list_ids')
                                            ->label('Listas de precios permitidas')
                                            ->multiple()
                                            ->options(fn () => static::priceListOptions())
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Select::make('default_price_list_id')
                                            ->label('Lista de precios predeterminada')
                                            ->options(fn () => static::priceListOptions())
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Select::make('price_mode')
                                            ->label('Precios del producto')
                                            ->options([
                                                'tax_included' => 'Precio con impuestos incluidos',
                                                'tax_excluded' => 'Precios sin impuestos',
                                            ])
                                            ->default('tax_included'),

                                        Forms\Components\Toggle::make('price_control')
                                            ->label('Control de precios')
                                            ->helperText('Restringe la modificación manual de precios a usuarios responsables.')
                                            ->default(true),

                                        Forms\Components\Toggle::make('line_discounts')
                                            ->label('Descuento por línea')
                                            ->default(true),

                                        Forms\Components\Toggle::make('global_discounts')
                                            ->label('Descuento global'),

                                        Forms\Components\Toggle::make('promotions_enabled')
                                            ->label('Promociones / cupones / fidelidad'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Ticket / Facturación')
                            ->schema([
                                Forms\Components\Section::make('Cierre de caja')
                                    ->description('Define el formato usado para imprimir el ticket y PDF de cierre de sesión.')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Select::make('session_close_format')
                                            ->label('Formato de cierre de caja')
                                            ->options([
                                                'generic' => 'Genérico',
                                                'papelon' => 'Papelón',
                                            ])
                                            ->default('generic')
                                            ->required()
                                            ->native(false)
                                            ->helperText('Genérico para la mayoría de empresas. Papelón usa el corte especial por secciones e impresiones.'),
                                    ]),

                                /*
                                 * BEXIA_V5527D5C_RECEIPT_PRIVACY_SECTION
                                 */
                                Forms\Components\Section::make('Privacidad del ticket')
                                    ->description('Configura cómo se muestra el vendedor/cajero en los tickets impresos. No afecta la pantalla del PDV.')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Select::make('receipt_seller_display_mode')
                                            ->label('Mostrar vendedor en ticket como')
                                            ->options([
                                                'staff_name' => 'Nombre del vendedor/cajero',
                                                'pos_code' => 'Código del PDV / caja',
                                                'session_number' => 'Número de sesión',
                                                'hidden' => 'No mostrar',
                                            ])
                                            ->default('staff_name')
                                            ->native(false)
                                            ->helperText('Usa Código del PDV / caja o No mostrar si no quieres exponer nombres de vendedores.'),
                                    ]),

                                Forms\Components\Section::make('Ticket')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('custom_receipt_header_footer')
                                            ->label('Encabezado y pie personalizados'),

                                        Forms\Components\FileUpload::make('ticket_logo_path')
                                            ->label('Logo del ticket')
                                            ->disk('public')
                                            ->directory('pos/ticket-logos')
                                            ->image()
                                            ->imageEditor()
                                            ->maxSize(2048)
                                            ->helperText('Este logo aparecerá en el ticket de venta.'),

                                        Forms\Components\Toggle::make('auto_print_receipt')
                                            ->label('Impresión automática del recibo'),

                                        Forms\Components\Toggle::make('skip_receipt_preview')
                                            ->label('Saltar vista previa')
                                            ->default(true),

                                        Forms\Components\Toggle::make('show_order_reference_on_ticket')
                                            ->label('Mostrar referencia de orden en ticket')
                                            ->default(true),

                                        Forms\Components\Textarea::make('receipt_header')
                                            ->label('Encabezado del ticket')
                                            ->rows(6),

                                        Forms\Components\Textarea::make('receipt_footer')
                                            ->label('Pie de página del ticket')
                                            ->rows(6),
                                    ]),

                                Forms\Components\Section::make('Facturación con QR')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('use_qr_on_receipt')
                                            ->label('Mostrar QR para facturación'),

                                        Forms\Components\TextInput::make('invoice_qr_url')
                                            ->label('URL de facturación')
                                            ->url()
                                            ->placeholder('https://facturacion.tudominio.com')
                                            ->helperText('Si está activo el QR, esta URL se usará en el ticket para facturar la compra.'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Pagos')
                            ->schema([
                                Forms\Components\Section::make('Métodos de pago')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Select::make('payment_method_ids')
                                            ->label('Métodos de pago permitidos')
                                            ->multiple()
                                            ->options(fn () => static::paymentMethodOptions())
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Se toman de catálogos de facturación si existen. Si no, se muestran métodos básicos.'),

                                        Forms\Components\Select::make('currency_ids')
                                            ->dehydrated(true) // V5.61.3c dehydrated currency_ids
                                            ->label('Monedas permitidas')
                                            ->multiple()
                                            ->options(fn () => static::currencyOptions())
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Select::make('default_currency_id')
                                            ->label('Moneda predeterminada')
                                            ->options(fn () => static::currencyOptions())
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Select::make('cash_denomination_ids')
                                            ->dehydrated(true) // V5.61.3c dehydrated cash_denomination_ids
                                            ->afterStateHydrated(function ($component, $state): void {
                                                $state = is_array($state) ? $state : [];
                                                $component->state(array_values(array_unique(array_map('strval', $state))));
                                            })
                                            ->dehydrateStateUsing(fn ($state): array => array_values(array_unique(array_map('strval', is_array($state) ? $state : [])))) // V5.61.3b: normalizar denominaciones seleccionadas.
                                            ->label('Denominaciones permitidas')
                                            ->multiple()
                                            ->options(fn () => static::cashDenominationOptions())
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Toggle::make('cash_rounding')
                                            ->label('Redondeo de efectivo'),

                                        Forms\Components\Toggle::make('tips_enabled')
                                            ->label('Permitir propinas'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Inventario')
                            ->schema([
                                Forms\Components\Section::make('Movimiento de inventario')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Select::make('inventory_update_mode')
                                            ->label('Gestión de inventario')
                                            ->options([
                                                'real_time' => 'En tiempo real',
                                                'session_close' => 'Al cierre de la sesión',
                                            ])
                                            ->default('real_time'),

                                        Forms\Components\Toggle::make('send_later')
                                            ->label('Permitir enviar después'),

                                        Forms\Components\Select::make('send_later_warehouse_id')
                                            ->label('Almacén para enviar después')
                                            ->options(fn () => static::optionsFromTable('warehouses', ['name', 'code']))
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\TextInput::make('send_later_route_id')
                                            ->label('Ruta de entrega')
                                            ->numeric(),

                                        Forms\Components\TextInput::make('barcode_nomenclature')
                                            ->label('Nomenclatura de código de barras'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Técnico')
                            ->schema([
                                Forms\Components\Section::make('Carga de datos')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('limited_products_loading')
                                            ->label('Limitar carga inicial de productos')
                                            ->default(true),

                                        Forms\Components\TextInput::make('loaded_products_limit')
                                            ->label('Número de productos cargados')
                                            ->numeric()
                                            ->default(500),

                                        Forms\Components\Toggle::make('load_products_in_background')
                                            ->label('Cargar productos restantes en segundo plano')
                                            ->default(true),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('v5486_open_session_status')
                    ->label('Sesión')
                    ->state(fn ($record): string => static::v5486OpenSessionLabel($record))
                    ->badge()
                    ->color(fn ($record): string => static::v5486OpenSessionForPosPoint($record) ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Punto de venta')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('warehouse_id')
                    ->label('Almacén')
                    ->state(fn ($record): string => static::labelFromTable('warehouses', $record->warehouse_id, ['name', 'code']))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('stock_location_id')
                    ->label('Ubicación')
                    ->state(fn ($record): string => static::labelFromTable('stock_locations', $record->stock_location_id, ['name', 'code']))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('default_price_list_id')
                    ->label('Lista predeterminada')
                    ->state(fn ($record): string => static::priceListLabel($record->default_price_list_id))
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'active' ? 'Activo' : 'Inactivo')
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('v5486_enter_open_session')
                    ->label(fn ($record): string => static::v5486OpenSessionForPosPoint($record)
                        ? 'Entrar a ' . (static::v5486OpenSessionForPosPoint($record)->number ?? 'sesión abierta')
                        : 'Sin sesión abierta')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->visible(fn ($record): bool => (bool) static::v5486OpenSessionForPosPoint($record))
                    ->url(fn ($record): ?string => static::v5486OpenSessionUrl($record)),

                Tables\Actions\Action::make('open_pos')
                    ->label('Abrir sesión')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(fn ($record): string => url('/pos/' . $record->id . '/open'))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make()
                    ->label('Configurar'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosPoints::route('/'),
            'create' => Pages\CreatePosPoint::route('/create'),
            'edit' => Pages\EditPosPoint::route('/{record}/edit'),
        ];
    }

    protected static function currentCompanyId(): ?int
    {
        try {
            $tenant = Filament::getTenant();

            if (is_object($tenant) && method_exists($tenant, 'getKey')) {
                return (int) $tenant->getKey();
            }

            if (is_numeric($tenant)) {
                return (int) $tenant;
            }
        } catch (\Throwable $e) {
            //
        }

        $tenant = request()->route('tenant');

        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        if (is_numeric($tenant)) {
            return (int) $tenant;
        }

        return auth()->user()?->company_id ? (int) auth()->user()->company_id : null;
    }

    protected static function priceListOptions(): array
    {
        foreach (static::priceListTables() as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $options = static::optionsFromTable($table, ['name', 'code', 'description']);

            if ($options) {
                return $options;
            }
        }

        return [];
    }

    protected static function priceListTables(): array
    {
        $candidates = [
            'price_lists',
            'price_list',
            'sale_price_lists',
            'sales_price_lists',
            'product_price_lists',
            'product_pricelists',
            'pricelists',
            'price_rules',
        ];

        try {
            $dynamic = collect(DB::select("
                select table_name
                from information_schema.tables
                where table_schema = current_schema()
                  and (
                    table_name ilike '%price%list%'
                    or table_name ilike '%pricelist%'
                    or table_name ilike '%listas%precio%'
                  )
                order by table_name
            "))
                ->pluck('table_name')
                ->all();

            $candidates = array_values(array_unique(array_merge($candidates, $dynamic)));
        } catch (\Throwable $e) {
            //
        }

        return $candidates;
    }

    protected static function priceListLabel(mixed $id): string
    {
        if (! $id) {
            return '—';
        }

        foreach (static::priceListTables() as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $label = static::labelFromTable($table, $id, ['name', 'code', 'description']);

            if ($label !== '—') {
                return $label;
            }
        }

        return '—';
    }

    protected static function paymentMethodOptions(): array
    {
        foreach (static::paymentMethodTables() as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $options = static::optionsFromTable($table, ['code', 'name', 'description']);

            if ($options) {
                return $options;
            }
        }

        return [
            'cash' => '01 - Efectivo',
            'card_credit' => '04 - Tarjeta de crédito',
            'card_debit' => '28 - Tarjeta de débito',
            'transfer' => '03 - Transferencia electrónica',
            'check' => '02 - Cheque nominativo',
            'credit' => '99 - Por definir / crédito',
        ];
    }

    protected static function paymentMethodTables(): array
    {
        $candidates = [
            'sat_payment_forms',
            'sat_payment_form',
            'payment_forms',
            'payment_form_catalogs',
            'cfdi_payment_forms',
            'fiscal_payment_forms',
            'sat_payment_methods',
            'payment_methods',
        ];

        try {
            $dynamic = collect(DB::select("
                select table_name
                from information_schema.tables
                where table_schema = current_schema()
                  and (
                    table_name ilike '%payment%form%'
                    or table_name ilike '%payment%method%'
                    or table_name ilike '%forma%pago%'
                    or table_name ilike '%metodo%pago%'
                    or table_name ilike '%sat%payment%'
                  )
                order by table_name
            "))
                ->pluck('table_name')
                ->all();

            $candidates = array_values(array_unique(array_merge($candidates, $dynamic)));
        } catch (\Throwable $e) {
            //
        }

        return $candidates;
    }

    protected static function optionsFromTable(string $table, array $labelColumns): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);

        $query = DB::table($table);

        $companyId = static::currentCompanyId();

        if ($companyId && in_array('company_id', $columns, true)) {
            $query->where('company_id', $companyId);
        }

        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', true);
        }

        if (in_array('active', $columns, true)) {
            $query->where('active', true);
        }

        $orderColumn = in_array('name', $columns, true)
            ? 'name'
            : (in_array('code', $columns, true) ? 'code' : 'id');

        return $query
            ->orderBy($orderColumn)
            ->limit(500)
            ->get()
            ->mapWithKeys(function ($row) use ($labelColumns, $columns) {
                $parts = [];

                foreach ($labelColumns as $column) {
                    if (in_array($column, $columns, true) && isset($row->{$column}) && trim((string) $row->{$column}) !== '') {
                        $parts[] = trim((string) $row->{$column});
                    }
                }

                if (! $parts) {
                    foreach (['display_name', 'title', 'label', 'nombre', 'descripcion'] as $column) {
                        if (in_array($column, $columns, true) && isset($row->{$column}) && trim((string) $row->{$column}) !== '') {
                            $parts[] = trim((string) $row->{$column});
                            break;
                        }
                    }
                }

                return [$row->id => $parts ? implode(' - ', array_unique($parts)) : ('#' . $row->id)];
            })
            ->all();
    }


    protected static function currencyOptions(): array
    {
        if (! Schema::hasTable('currencies')) {
            return [];
        }

        return static::optionsFromTable('currencies', ['code', 'name']);
    }

    protected static function cashDenominationOptions(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('cash_denominations')) {
            return [];
        }

        $companyId = null;

        try {
            $tenant = class_exists(\Filament\Facades\Filament::class)
                ? \Filament\Facades\Filament::getTenant()
                : null;

            if ($tenant && isset($tenant->id)) {
                $companyId = (int) $tenant->id;
            }
        } catch (\Throwable $e) {
            $companyId = null;
        }

        if (! $companyId) {
            $routeTenant = request()->route('tenant')
                ?? request()->route('company')
                ?? request()->route('company_id')
                ?? request()->segment(2);

            if (is_object($routeTenant) && isset($routeTenant->id)) {
                $companyId = (int) $routeTenant->id;
            } elseif (is_numeric($routeTenant)) {
                $companyId = (int) $routeTenant;
            }
        }

        $query = \Illuminate\Support\Facades\DB::table('cash_denominations')
            ->where('is_active', true);

        if ($companyId && \Illuminate\Support\Facades\Schema::hasColumn('cash_denominations', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $rows = $query
            ->orderByRaw("case when type = 'coin' then 0 else 1 end")
            ->orderBy('value')
            ->orderBy('id')
            ->get();

        return $rows
            ->unique(fn ($row): string => (string) ($row->company_id ?? $companyId) . '|' . (string) $row->value . '|' . (string) $row->type)
            ->mapWithKeys(function ($row): array {
                $name = trim((string) ($row->name ?? 'Denominación'));
                $value = number_format((float) ($row->value ?? 0), 2);

                return [
                    (string) $row->id => $name . ' - $' . $value,
                ];
            })
            ->all();
    }



    protected static function categoryOptions(): array
    {
        foreach (['product_categories', 'categories'] as $table) {
            if (Schema::hasTable($table)) {
                return static::optionsFromTable($table, ['name', 'code']);
            }
        }

        return [];
    }

    protected static function customerOptions(): array
    {
        foreach (['contacts', 'customers', 'clients'] as $table) {
            if (Schema::hasTable($table)) {
                $query = DB::table($table);

                $companyId = static::currentCompanyId();

                if ($companyId && Schema::hasColumn($table, 'company_id')) {
                    $query->where('company_id', $companyId);
                }

                if (Schema::hasColumn($table, 'is_customer')) {
                    $query->where('is_customer', true);
                }

                if (Schema::hasColumn($table, 'is_active')) {
                    $query->where('is_active', true);
                }

                return $query
                    ->orderBy('id')
                    ->limit(500)
                    ->get()
                    ->mapWithKeys(function ($row) {
                        $name = $row->commercial_name
                            ?? $row->name
                            ?? $row->fiscal_name
                            ?? ('Cliente #' . $row->id);

                        $rfc = isset($row->rfc) && $row->rfc ? (' - ' . $row->rfc) : '';

                        return [$row->id => $name . $rfc];
                    })
                    ->all();
            }
        }

        return [];
    }

    protected static function labelFromTable(string $table, mixed $id, array $labelColumns): string
    {
        if (! $id || ! Schema::hasTable($table)) {
            return '—';
        }

        $columns = Schema::getColumnListing($table);

        $row = DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '—';
        }

        $parts = [];

        foreach ($labelColumns as $column) {
            if (in_array($column, $columns, true) && isset($row->{$column}) && trim((string) $row->{$column}) !== '') {
                $parts[] = trim((string) $row->{$column});
            }
        }

        if (! $parts) {
            foreach (['display_name', 'title', 'label', 'nombre', 'descripcion'] as $column) {
                if (in_array($column, $columns, true) && isset($row->{$column}) && trim((string) $row->{$column}) !== '') {
                    $parts[] = trim((string) $row->{$column});
                    break;
                }
            }
        }

        return $parts ? implode(' - ', array_unique($parts)) : ('#' . $id);
    }

    protected static function v5486OpenSessionForPosPoint($record): ?object
    {
        $posPointId = (int) ($record->id ?? 0);

        if ($posPointId <= 0 || ! \Illuminate\Support\Facades\Schema::hasTable('pos_sessions')) {
            return null;
        }

        return \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('pos_point_id', $posPointId)
            ->where('status', 'open')
            ->orderByDesc('id')
            ->first();
    }

    protected static function v5486OpenSessionLabel($record): string
    {
        $session = static::v5486OpenSessionForPosPoint($record);

        if (! $session) {
            return 'Sin sesión abierta';
        }

        return 'Sesión abierta ' . (string) ($session->number ?? ('#' . $session->id));
    }

    protected static function v5486OpenSessionUrl($record): ?string
    {
        $session = static::v5486OpenSessionForPosPoint($record);

        if (! $session) {
            return null;
        }

        return url('/pos/sessions/' . $session->id . '/screen');
    }


}
