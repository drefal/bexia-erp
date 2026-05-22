<?php

namespace App\Filament\Resources;

use App\Support\ProductVariantSearch;
use App\Filament\Resources\SaleOrderResource\Pages;
use App\Models\SaleOrder;
use App\Models\SaleOrderLine;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class SaleOrderResource extends Resource
{
    protected static ?string $model = SaleOrder::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $tenantRelationshipName = 'saleOrders';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Ventas';

    protected static ?int $navigationSort = 10;
protected static ?string $modelLabel = 'venta';

    protected static ?string $pluralModelLabel = 'ventas';
    protected static ?string $breadcrumb = 'Ventas';

protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
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
        return false;
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
            || $user->can('sales.update');
    }


    public static function canCreate(): bool
    {
        return static::userCanPermission('sales.create');
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::userCanPermission('sales.view');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::userCanPermission('sales.update');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::userCanPermission('sales.delete');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(fn ($record): string => in_array((string) ($record?->status ?? ''), ['confirmed', 'partially_delivered', 'delivered', 'invoiced', 'partially_invoiced', 'closed'], true) ? 'Encabezado de orden de venta' : 'Encabezado de cotización')
                    ->columns(4)
                    ->schema([
                        Forms\Components\ViewField::make('sales_order_status_notice')
                            ->label('')
                            ->view('filament.sales-orders.status-notice')
                            ->viewData(fn (?SaleOrder $record): array => [
                                'saleOrderId' => $record?->id,
                            ])
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('number_display')
                            ->label('Folio')
                            ->content(fn (?SaleOrder $record): string => $record?->number ?: 'Se generará automáticamente al guardar'),

                        Forms\Components\Select::make('customer_contact_id')
                            ->label('Cliente')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(2)
                            ->options(fn (): array => static::initialCustomerOptions())
                            ->getSearchResultsUsing(fn (string $search): array => static::customerSearchOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => static::customerLabel((int) $value))
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                $contactId = (int) ($state ?? 0);
                                $contact = static::contactById($contactId);

                                $set('customer_name', $contact ? static::contactDisplayName($contact) : null);
                                $set('delivery_contact_id', null);
                                $set('delivery_address', $contact ? static::contactAddressText($contact) : null);
                                $set('billing_address', $contact ? static::contactAddressText($contact) : null);

                                if ($contact) {
                                    $set('currency', $contact->customer_currency_code ?: 'MXN');

                                    if (! empty($contact->customer_payment_terms_text)) {
                                        $set('payment_terms', 'custom');
                                    }

                                    if (! empty($contact->customer_payment_method_code)) {
                                        $set('payment_method', $contact->customer_payment_method_code);
                                    }

                                    if (! empty($contact->customer_cfdi_use_code)) {
                                        $set('fiscal_position', $contact->customer_cfdi_use_code);
                                    }

                                    if (! empty($contact->salesperson_user_id)) {
                                        $set('salesperson_user_id', (int) $contact->salesperson_user_id);
                                    }
                                }
                            }),

                        Forms\Components\Hidden::make('customer_name')
                            ->dehydrated(true),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options(static::statusOptions())
                            ->default('draft')
                            ->disabled()
                            ->dehydrated(true),

                        Forms\Components\DateTimePicker::make('order_date')
                            ->label('Fecha de venta')
                            ->seconds(false)
                            ->default(now()),

                        Forms\Components\DatePicker::make('expected_delivery_date')
                            ->label('Fecha esperada entrega'),

                        Forms\Components\Hidden::make('price_list_id')
                            ->default(fn (): ?int => static::defaultPriceListId())
                            ->dehydrated(true),

                        Forms\Components\TextInput::make('currency')
                            ->label('Moneda')
                            ->default('MXN')
                            ->maxLength(8),

                        Forms\Components\ViewField::make('sales_order_price_list_applier')
                            ->label('')
                            ->view('filament.sales-orders.price-list-applier-field')
                            ->viewData(fn (?SaleOrder $record): array => [
                                'saleOrderId' => $record?->id,
                            ])
                            ->dehydrated(false)
                            ->columnSpanFull(),

                    ]),



                Forms\Components\Tabs::make('Detalle')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Líneas')
                            ->schema([
                                Forms\Components\ViewField::make('sale_order_lines_editor')
                                    ->label('')
                                    ->view('filament.sales-orders.lines-inline-field')
                                    ->viewData(fn (?SaleOrder $record): array => [
                                        'saleOrderId' => $record?->id,
                                    ])
                                    ->dehydrated(false),



                            ]),

                        Forms\Components\Tabs\Tab::make('Entrega e inventario')
                            ->schema([
                                Forms\Components\Section::make('Origen de inventario')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\Select::make('warehouse_id')
                                            ->label('Almacén origen')
                                            ->searchable()
                                            ->preload()
                                            ->options(fn (): array => static::warehouseOptions())
                                            ->getOptionLabelUsing(fn ($value): ?string => static::warehouseLabel((int) ($value ?? 0)))
                                            ->default(fn (): ?int => static::defaultOperationalWarehouseId())
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                                $set('location_id', static::defaultOperationalLocationId((int) ($state ?? 0)));
                                            })
                                            ->helperText('Este almacén será usado para reservar y entregar la venta.'),

                                        Forms\Components\Select::make('location_id')
                                            ->label('Ubicación origen')
                                            ->searchable()
                                            ->preload()
                                            ->options(fn (Forms\Get $get): array => static::locationOptions((int) ($get('warehouse_id') ?? 0)))
                                            ->getOptionLabelUsing(fn ($value): ?string => static::locationLabel((int) ($value ?? 0)))
                                            ->default(fn (): ?int => static::defaultOperationalLocationId())
                                            ->helperText('Ubicación desde donde saldrá la mercancía.'),

                                        Forms\Components\Select::make('delivery_policy')
                                            ->label('Política de entrega')
                                            ->default('complete')
                                            ->options([
                                                'asap' => 'Lo antes posible',
                                                'complete' => 'Entrega completa',
                                                'partial' => 'Permitir entregas parciales',
                                            ]),
                                    ]),

                                Forms\Components\Section::make('Dirección de entrega')
                                    ->schema([
                                        Forms\Components\Select::make('delivery_contact_id')
                                            ->label('Dirección de entrega registrada')
                                            ->searchable()
                                            ->preload()
                                            ->options(fn (Forms\Get $get): array => static::deliveryAddressOptions((int) ($get('../../customer_contact_id') ?? $get('customer_contact_id') ?? 0)))
                                            ->visible(fn (Forms\Get $get): bool => count(static::deliveryAddressOptions((int) ($get('../../customer_contact_id') ?? $get('customer_contact_id') ?? 0))) > 0)
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                                $contactId = (int) ($state ?? 0);

                                                if ($contactId <= 0) {
                                                    $main = static::contactById((int) ($get('../../customer_contact_id') ?? $get('customer_contact_id') ?? 0));
                                                    $set('delivery_address', $main ? static::contactAddressText($main) : null);
                                                    return;
                                                }

                                                $address = static::contactById($contactId);
                                                $set('delivery_address', $address ? static::contactAddressText($address) : null);
                                            })
                                            ->columnSpanFull(),

                                        Forms\Components\Textarea::make('delivery_address')
                                            ->label('Dirección de entrega')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Facturación y pagos')
                            ->schema([
                                Forms\Components\Section::make('Datos de facturación')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\Textarea::make('billing_address')
                                            ->label('Dirección de facturación')
                                            ->rows(3)
                                            ->columnSpanFull(),

                                        Forms\Components\Select::make('payment_terms')
                                            ->label('Términos de pago')
                                            ->options([
                                                'cash' => 'Contado',
                                                'credit_7' => 'Crédito 7 días',
                                                'credit_15' => 'Crédito 15 días',
                                                'credit_30' => 'Crédito 30 días',
                                                'custom' => 'Personalizado',
                                            ]),

                                        Forms\Components\Select::make('payment_method')
                                            ->label('Forma de pago')
                                            ->options([
                                                'undefined' => 'Por definir',
                                                'cash' => 'Efectivo',
                                                'transfer' => 'Transferencia',
                                                'card' => 'Tarjeta',
                                                'check' => 'Cheque',
                                                'credit' => 'Crédito',
                                            ])
                                            ->default('undefined'),

                                        Forms\Components\TextInput::make('fiscal_position')
                                            ->label('Posición fiscal')
                                            ->maxLength(255),

                                        Forms\Components\Select::make('invoice_status')
                                            ->label('Estado de facturación')
                                            ->default('not_invoiced')
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->options([
                                                'not_invoiced' => 'No facturada',
                                                'to_invoice' => 'Por facturar',
                                                'invoiced' => 'Facturada',
                                            ]),

                                        Forms\Components\Select::make('payment_status')
                                            ->label('Estado de pago')
                                            ->default('unpaid')
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->options([
                                                'unpaid' => 'No pagada',
                                                'partial' => 'Pago parcial',
                                                'paid' => 'Pagada',
                                            ]),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Seguimiento / origen')
                            ->schema([
                                Forms\Components\Section::make('Ventas')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\Select::make('salesperson_user_id')
                                            ->label('Vendedor')
                                            ->searchable()
                                            ->preload()
                                            ->options(fn (): array => static::userOptions()),

                                        Forms\Components\TextInput::make('sales_team')
                                            ->label('Equipo de ventas')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('customer_reference')
                                            ->label('Referencia del cliente')
                                            ->maxLength(255),

                                        Forms\Components\Select::make('margin_approval_user_id')
                                            ->label('Aprobador de margen')
                                            ->searchable()
                                            ->preload()
                                            ->options(fn (): array => static::userOptions())
                                            ->default(fn (): ?int => static::defaultMarginApproverId())
                                            ->helperText('Se solicitará aprobación a este usuario si hay margen bajo o pérdida.'),
                                    ]),

                                Forms\Components\Section::make('Origen')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\Select::make('source_type')
                                            ->label('Origen')
                                            ->default('manual')
                                            ->options([
                                                'manual' => 'Manual',
                                                'crm_opportunity' => 'CRM / oportunidad',
                                                'pos_ticket' => 'PDV / ticket',
                                                'ecommerce_order' => 'Ecommerce',
                                                'other' => 'Otro',
                                            ]),

                                        Forms\Components\TextInput::make('source_reference')
                                            ->label('Referencia origen')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('crm_opportunity_reference')
                                            ->label('Oportunidad CRM')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('campaign')
                                            ->label('Campaña')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('medium')
                                            ->label('Medio')
                                            ->maxLength(255),

                                        Forms\Components\Textarea::make('notes')
                                            ->label('Notas internas')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
                                Forms\Components\ViewField::make('sales_order_history')
                                    ->columnSpanFull()
                                    ->label('')
                                    ->view('filament.sales-orders.history-field')
                                    ->viewData(fn (?SaleOrder $record): array => [
                                        'saleOrderId' => $record?->id,
                                    ])
                                    ->dehydrated(false),

            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('priceList.name')
                    ->label('Lista precios')
                    ->placeholder('—')
                    ->toggleable(),


                Tables\Columns\TextColumn::make('billing_state_portal_sales')
                    ->label('Facturación')
                    ->badge()
                    ->state(function ($record): string {
                        if (\Illuminate\Support\Facades\Schema::hasTable('invoices')
                            && \Illuminate\Support\Facades\DB::table('invoices')
                                ->where('source_type', 'sales_order')
                                ->where('source_id', (int) $record->id)
                                ->exists()) {
                            return 'Facturado';
                        }

                        return match ((string) ($record->invoice_status ?? '')) {
                            'invoiced' => 'Facturado',
                            'to_invoice' => 'Por facturar',
                            default => 'No facturado',
                        };
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Facturado' => 'success',
                        'Por facturar' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->getStateUsing(fn (SaleOrder $record): string => self::quoteDisplayStatusLabel($record))
                    ->color(fn (SaleOrder $record): string => self::quoteDisplayStatusColor($record))
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'confirmed',
                        'warning' => 'partially_delivered',
                        'success' => 'delivered',
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('source_type')
                    ->label('Origen')
                    ->formatStateUsing(fn (?string $state): string => static::sourceTypeLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => match ((string) $state) {
                        'duplicated', 'duplicate' => 'info',
                        'manual' => 'gray',
                        'crm' => 'success',
                        'pos' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),


                Tables\Columns\TextColumn::make('source_reference')
                    ->label('Referencia origen')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('margin_approval_status')
                    ->label('Aprobación margen')
                    ->formatStateUsing(fn (?string $state): string => static::marginApprovalStatusLabel($state))
                    ->colors([
                        'gray' => 'not_required',
                        'warning' => 'required',
                        'info' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_with_tax')
                    ->label('Total')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('order_date')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(static::statusOptions()),
            ])
            // V5.61.2e2: click en fila abre Ver, no Editar.
            ->recordUrl(fn (SaleOrder $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                Tables\Actions\Action::make('deliver_order')
                    ->label('Entrega')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->visible(fn (SaleOrder $record): bool => in_array((string) $record->status, ['confirmed', 'partially_delivered'], true))
                    ->url(fn (SaleOrder $record): string => route('sales-orders.deliveries.page', ['saleOrder' => $record])),



                Tables\Actions\Action::make('generate_internal_invoice')
                    ->label('Generar factura interna')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generar factura interna')
                    ->modalDescription('Esto creará una factura interna en borrador desde esta venta. No timbra CFDI todavía.')
                    ->visible(function (SaleOrder $record): bool {
                        if ((string) ($record->status ?? '') === 'draft') {
                            return false;
                        }

                        if (! \Illuminate\Support\Facades\Schema::hasTable('invoices')) {
                            return false;
                        }

                        if (\Illuminate\Support\Facades\DB::table('invoices')
                            ->where('source_type', 'sales_order')
                            ->where('source_id', (int) $record->id)
                            ->exists()) {
                            return false;
                        }

                        return \Illuminate\Support\Facades\DB::table('sales_order_lines')
                            ->where('sales_order_id', (int) $record->id)
                            ->exists();
                    })
                    ->action(function (SaleOrder $record): void {
                        try {
                            $invoiceId = app(\App\Support\InternalInvoiceBuilder::class)
                                ->createFromSalesOrder((int) $record->id, auth()->id());

                            $invoice = \Illuminate\Support\Facades\DB::table('invoices')
                                ->where('id', $invoiceId)
                                ->first();

                            $record->refresh();

                            \Filament\Notifications\Notification::make()
                                ->title('Factura interna creada')
                                ->body('Folio: ' . ($invoice->number ?? ('#' . $invoiceId)))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('No se pudo crear la factura interna')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('view_internal_invoice')
                    ->label('Ver factura interna')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->visible(fn (SaleOrder $record): bool => \Illuminate\Support\Facades\Schema::hasTable('invoices')
                        && \Illuminate\Support\Facades\DB::table('invoices')
                            ->where('source_type', 'sales_order')
                            ->where('source_id', (int) $record->id)
                            ->exists())
                    ->url(function (SaleOrder $record): string {
                        $invoiceId = \Illuminate\Support\Facades\DB::table('invoices')
                            ->where('source_type', 'sales_order')
                            ->where('source_id', (int) $record->id)
                            ->value('id');

                        return $invoiceId
                            ? \App\Filament\Resources\InvoiceResource::getUrl('view', ['record' => $invoiceId])
                            : '#';
                    }),

                self::sendQuoteToPosTableAction(),
                Tables\Actions\Action::make('confirm')
                    ->label('Convertir a orden de venta')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SaleOrder $record): bool => $record->status === 'draft' && static::isQuoteValidatedForPos($record) && static::userCanPermission('sales.confirm'))
                    ->requiresConfirmation()
                    ->modalHeading('Convertir a orden de venta')
                    ->modalDescription('La cotización se convertirá en orden de venta. Este paso todavía no afecta inventario.')
                    ->action(function (SaleOrder $record): void {
                        if (! $record->lines()->exists()) {
                            Notification::make()
                                ->title('No se puede confirmar')
                                ->body('La venta debe tener al menos un producto.')
                                ->warning()
                                ->send();

                            return;
                        }

                        if (! \App\Filament\Resources\SaleOrderResource::ensureMarginApprovalBeforeConfirm($record)) {
                            return;
                        }

                        $record->confirm();

                        Notification::make()
                            ->title('Cotización convertida en orden de venta')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('approve_margin')
                    ->label('Aprobar margen')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (SaleOrder $record): bool => static::canApproveMargin($record))
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar margen de venta')
                    ->modalDescription('La venta podrá confirmarse aunque tenga líneas con margen bajo o pérdida.')
                    ->action(function (SaleOrder $record): void {
                        DB::table('sales_orders')
                            ->where('id', $record->id)
                            ->update(static::filterSalesOrderColumns([
                                'margin_approval_status' => 'approved',
                                'margin_approved_by_user_id' => auth()->id(),
                                'margin_approved_at' => now(),
                                'margin_rejected_by_user_id' => null,
                                'margin_rejected_at' => null,
                                'margin_rejection_reason' => null,
                                'updated_at' => now(),
                            ]));

                        Notification::make()
                            ->title('Margen aprobado')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject_margin')
                    ->label('Rechazar margen')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SaleOrder $record): bool => static::canApproveMargin($record))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (SaleOrder $record, array $data): void {
                        DB::table('sales_orders')
                            ->where('id', $record->id)
                            ->update(static::filterSalesOrderColumns([
                                'margin_approval_status' => 'rejected',
                                'margin_rejected_by_user_id' => auth()->id(),
                                'margin_rejected_at' => now(),
                                'margin_rejection_reason' => $data['reason'] ?? null,
                                'updated_at' => now(),
                            ]));

                        Notification::make()
                            ->title('Margen rechazado')
                            ->warning()
                            ->send();
                    }),
                Tables\Actions\Action::make('return_to_quote')
                    ->label('Regresar a cotización')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (SaleOrder $record): bool => \App\Support\SalesApprovalWorkflow::canReturnToQuote($record))
                    ->requiresConfirmation()
                    ->modalHeading('Regresar orden a cotización')
                    ->modalDescription('Solo se permite si no tiene entregas, facturas ni pagos.')
                    ->action(function (SaleOrder $record): void {
                        \App\Support\SalesApprovalWorkflow::returnToQuote($record);

                        Notification::make()
                            ->title('Orden regresada a cotización')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('cancel_quote')
                    ->label('Cancelar cotización')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SaleOrder $record): bool => $record->status === 'draft' && static::userCanPermission('sales.cancel'))
                    ->requiresConfirmation()
                    ->modalHeading('Cancelar cotización')
                    ->modalDescription('La cotización quedará cancelada. No se borrará el historial.')
                    ->action(function (SaleOrder $record): void {
                        DB::table('sales_orders')
                            ->where('id', $record->id)
                            ->update(static::filterSalesOrderColumns([
                                'status' => 'cancelled',
                                'updated_at' => now(),
                            ]));

                        Notification::make()
                            ->title('Cotización cancelada')
                            ->success()
                            ->send();
                    }),



                Tables\Actions\Action::make('request_order_reapproval')
                    ->label('Enviar a aprobación')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn (SaleOrder $record): bool => \App\Support\SalesApprovalWorkflow::needsOrderReapproval($record))
                    ->requiresConfirmation()
                    ->modalHeading('Enviar orden a aprobación')
                    ->modalDescription('La orden fue modificada después de su aprobación. Se enviará nuevamente al flujo configurado.')
                    ->action(function (SaleOrder $record): void {
                        $request = \App\Support\SalesApprovalWorkflow::requestOrderReapproval($record);

                        Notification::make()
                            ->title('Orden enviada a aprobación')
                            ->body('Solicitud #' . ($request->id ?? ''))
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\Action::make('print_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->visible(fn (SaleOrder $record): bool => static::userCanPermission('sales.print_pdf') || static::userCanPermission('sales.view'))
                    ->url(
                        fn (SaleOrder $record): string => route('sales.orders.print', ['saleOrder' => $record->id]),
                        shouldOpenInNewTab: true
                    ),

                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('lines');
    }

    public static function statusOptions(): array
    {
        return [
            'draft' => 'Cotización',
            'confirmed' => 'Orden de venta',
            'partially_delivered' => 'Parcialmente entregada',
            'delivered' => 'Entregada',
            'cancelled' => 'Cancelada',
        ];
    }


    public static function statusLabel(?string $state): string
    {
        return static::statusOptions()[(string) $state] ?? ucfirst(str_replace('_', ' ', (string) $state));
    }

    public static function sourceLabel(?string $state): string
    {
        return match ((string) $state) {
            'manual' => 'Manual',
            'crm_opportunity' => 'CRM / oportunidad',
            'pos_ticket' => 'PDV / ticket',
            'ecommerce_order' => 'Ecommerce',
            'other' => 'Otro',
            default => $state ?: 'Manual',
        };
    }


    protected static function userOptions(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        return DB::table('users')
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->mapWithKeys(function ($user): array {
                $name = trim((string) ($user->name ?? ''));
                $email = trim((string) ($user->email ?? ''));

                $label = $name !== '' ? $name : $email;

                if ($label === '') {
                    $label = 'Usuario #' . $user->id;
                }

                return [(int) $user->id => $label];
            })
            ->all();
    }

    protected static function tenantCompanyId(): int
    {
        if (Filament::getTenant()) {
            return (int) Filament::getTenant()->getKey();
        }

        return (int) (request()->route('tenant') ?? auth()->user()?->company_id ?? 0);
    }



    protected static function initialCustomerOptions(): array
    {
        return static::customerSearchOptions('', 50);
    }

    protected static function customerSearchOptions(string $search, int $limit = 80): array
    {
        if (! Schema::hasTable('contacts')) {
            return [];
        }

        $companyId = static::tenantCompanyId();
        $search = trim($search);

        $query = DB::table('contacts')
            ->where('company_id', $companyId)
            ->where('is_customer', true)
            ->where('is_active', true)
            ->whereNull('deleted_at');

        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';

            $query->where(function ($q) use ($like) {
                foreach (['name', 'commercial_name', 'fiscal_name', 'rfc', 'email', 'phone', 'mobile'] as $column) {
                    if (Schema::hasColumn('contacts', $column)) {
                        $q->orWhereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", [$like]);
                    }
                }
            });
        }

        return $query
            ->orderBy('commercial_name')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn ($contact): array => [
                (int) $contact->id => static::contactDisplayName($contact),
            ])
            ->all();
    }

    protected static function contactById(int $contactId): ?object
    {
        if ($contactId <= 0 || ! Schema::hasTable('contacts')) {
            return null;
        }

        return DB::table('contacts')->where('id', $contactId)->first();
    }

    protected static function customerLabel(int $contactId): ?string
    {
        $contact = static::contactById($contactId);

        return $contact ? static::contactDisplayName($contact) : null;
    }

    protected static function contactDisplayName(object $contact): string
    {
        $name = trim((string) ($contact->commercial_name ?: $contact->name ?: $contact->fiscal_name ?: ''));
        $rfc = trim((string) ($contact->rfc ?? ''));

        if ($name === '') {
            $name = 'Contacto #' . $contact->id;
        }

        return $rfc !== '' ? "{$name} ({$rfc})" : $name;
    }

    protected static function contactAddressText(?object $contact): ?string
    {
        if (! $contact) {
            return null;
        }

        $parts = [];

        $streetLine = trim(implode(' ', array_filter([
            trim((string) ($contact->street ?? '')),
            trim((string) ($contact->exterior_number ?? '')),
            trim((string) ($contact->interior_number ?? '')) !== '' ? 'Int. ' . trim((string) ($contact->interior_number ?? '')) : '',
        ])));

        if ($streetLine !== '') {
            $parts[] = $streetLine;
        }

        foreach ([
            'street2',
            'neighborhood',
            'locality',
            'municipality',
            'city',
            'state',
            'postal_code',
            'country',
        ] as $column) {
            $value = trim((string) ($contact->{$column} ?? ''));

            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return count($parts) > 0 ? implode(', ', array_unique($parts)) : null;
    }

    protected static function deliveryAddressOptions(int $customerContactId): array
    {
        if ($customerContactId <= 0 || ! Schema::hasTable('contacts')) {
            return [];
        }

        $companyId = static::tenantCompanyId();

        return DB::table('contacts')
            ->where('company_id', $companyId)
            ->where('parent_contact_id', $customerContactId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereIn('address_type', ['delivery', 'shipping', 'entrega'])
                  ->orWhere('contact_type', 'delivery')
                  ->orWhere('contact_type', 'shipping');
            })
            ->orderBy('branch_name')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function ($contact): array {
                $label = trim((string) ($contact->branch_name ?: $contact->name ?: $contact->commercial_name ?: 'Dirección #' . $contact->id));
                $address = static::contactAddressText($contact);

                return [(int) $contact->id => $address ? "{$label} - {$address}" : $label];
            })
            ->all();
    }

    protected static function defaultUserWarehouseId(): ?int
    {
        return static::defaultOperationalWarehouseId();
    }



    protected static function defaultOperationalWarehouseId(): ?int
    {
        $companyId = static::tenantCompanyId();

        if ($companyId <= 0) {
            return null;
        }

        $userWarehouseId = (int) (auth()->user()?->default_warehouse_id ?? 0);

        if (static::warehouseBelongsToTenant($userWarehouseId, $companyId)) {
            return $userWarehouseId;
        }

        $companyWarehouseId = static::companyDefaultWarehouseId($companyId);

        if (static::warehouseBelongsToTenant($companyWarehouseId, $companyId)) {
            return $companyWarehouseId;
        }

        return static::firstActiveWarehouseId($companyId);
    }

    protected static function defaultOperationalLocationId(?int $warehouseId = null): ?int
    {
        $companyId = static::tenantCompanyId();

        if ($companyId <= 0) {
            return null;
        }

        $warehouseId = $warehouseId && $warehouseId > 0
            ? (int) $warehouseId
            : static::defaultOperationalWarehouseId();

        if (! $warehouseId || ! static::warehouseBelongsToTenant((int) $warehouseId, $companyId)) {
            return null;
        }

        $userLocationId = (int) (auth()->user()?->default_location_id ?? 0);

        if (static::locationBelongsToTenantAndWarehouse($userLocationId, $companyId, (int) $warehouseId)) {
            return $userLocationId;
        }

        $companyLocationId = static::companyDefaultLocationId($companyId);

        if (static::locationBelongsToTenantAndWarehouse($companyLocationId, $companyId, (int) $warehouseId)) {
            return $companyLocationId;
        }

        return static::firstActiveLocationId($companyId, (int) $warehouseId);
    }

    protected static function companyDefaultWarehouseId(int $companyId): ?int
    {
        if ($companyId <= 0 || ! Schema::hasTable('companies') || ! Schema::hasColumn('companies', 'default_warehouse_id')) {
            return null;
        }

        $id = DB::table('companies')
            ->where('id', $companyId)
            ->value('default_warehouse_id');

        return $id ? (int) $id : null;
    }

    protected static function companyDefaultLocationId(int $companyId): ?int
    {
        if ($companyId <= 0 || ! Schema::hasTable('companies') || ! Schema::hasColumn('companies', 'default_location_id')) {
            return null;
        }

        $id = DB::table('companies')
            ->where('id', $companyId)
            ->value('default_location_id');

        return $id ? (int) $id : null;
    }

    protected static function warehouseBelongsToTenant(?int $warehouseId, int $companyId): bool
    {
        $warehouseId = (int) ($warehouseId ?? 0);

        if ($warehouseId <= 0 || $companyId <= 0 || ! Schema::hasTable('warehouses')) {
            return false;
        }

        $query = DB::table('warehouses')->where('id', $warehouseId);

        if (Schema::hasColumn('warehouses', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('warehouses', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->exists();
    }

    protected static function locationBelongsToTenantAndWarehouse(?int $locationId, int $companyId, int $warehouseId): bool
    {
        $locationId = (int) ($locationId ?? 0);

        if ($locationId <= 0 || $companyId <= 0 || $warehouseId <= 0 || ! Schema::hasTable('stock_locations')) {
            return false;
        }

        $query = DB::table('stock_locations')
            ->where('id', $locationId)
            ->where('warehouse_id', $warehouseId);

        if (Schema::hasColumn('stock_locations', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('stock_locations', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->exists();
    }

    protected static function firstActiveWarehouseId(int $companyId): ?int
    {
        if ($companyId <= 0 || ! Schema::hasTable('warehouses')) {
            return null;
        }

        $query = DB::table('warehouses');

        if (Schema::hasColumn('warehouses', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('warehouses', 'is_active')) {
            $query->where('is_active', true);
        }

        $id = $query
            ->orderBy('name')
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    protected static function firstActiveLocationId(int $companyId, int $warehouseId): ?int
    {
        if ($companyId <= 0 || $warehouseId <= 0 || ! Schema::hasTable('stock_locations')) {
            return null;
        }

        $query = DB::table('stock_locations')
            ->where('warehouse_id', $warehouseId);

        if (Schema::hasColumn('stock_locations', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('stock_locations', 'is_active')) {
            $query->where('is_active', true);
        }

        $id = $query
            ->orderBy('name')
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }



    protected static function defaultUserLocationId(): ?int
    {
        $id = auth()->user()?->default_location_id ?? null;

        return $id ? (int) $id : null;
    }

    protected static function priceListOptions(): array
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

    protected static function defaultPriceListId(): ?int
    {
        if (! Schema::hasTable('sales_price_lists')) {
            return null;
        }

        $companyId = static::tenantCompanyId();

        $id = DB::table('sales_price_lists')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }


    protected static function productHasVariants(int $productId): bool
    {
        if ($productId <= 0 || ! Schema::hasTable('products')) {
            return false;
        }

        $query = DB::table('products')
            ->where('parent_product_id', $productId);

        if (Schema::hasColumn('products', 'is_variant')) {
            $query->where('is_variant', true);
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->exists();
    }

    protected static function variantOptions(int $productId): array
    {
        if ($productId <= 0 || ! Schema::hasTable('products')) {
            return [];
        }

        $query = DB::table('products')
            ->where('parent_product_id', $productId);

        if (Schema::hasColumn('products', 'is_variant')) {
            $query->where('is_variant', true);
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($variant): array => [
                (int) $variant->id => static::variantLabel($variant),
            ])
            ->all();
    }

    protected static function variantLabelById(int $variantId): ?string
    {
        if ($variantId <= 0 || ! Schema::hasTable('products')) {
            return null;
        }

        $variant = DB::table('products')
            ->where('id', $variantId)
            ->first();

        return $variant ? static::variantLabel($variant) : null;
    }

    protected static function variantLabel(object $variant): string
    {
        $group = '';

        if (property_exists($variant, 'variant_group')) {
            $group = trim((string) ($variant->variant_group ?? ''));
        }

        $value = '';

        foreach (['variant_value', 'variant_name', 'color', 'name'] as $column) {
            if (property_exists($variant, $column) && trim((string) ($variant->{$column} ?? '')) !== '') {
                $value = trim((string) $variant->{$column});
                break;
            }
        }

        if ($value === '') {
            return 'Variante #' . ($variant->id ?? '');
        }

        return $group !== '' ? $group . ': ' . $value : $value;
    }


    protected static function priceForProduct(int $productId, int $variantId, int $priceListId, float $fallback, array $visited = []): float
    {
        if ($priceListId <= 0 || ! Schema::hasTable('sales_price_lists')) {
            return $fallback;
        }

        if (in_array($priceListId, $visited, true)) {
            return $fallback;
        }

        $visited[] = $priceListId;

        $priceList = DB::table('sales_price_lists')
            ->where('id', $priceListId)
            ->where('is_active', true)
            ->first();

        if (! $priceList) {
            return $fallback;
        }

        $calculationType = (string) ($priceList->calculation_type ?? 'items');

        if ($calculationType === 'formula') {
            $basis = (string) ($priceList->formula_basis ?? 'price_list');
            $adjustmentPercent = (float) ($priceList->adjustment_percent ?? 0);

            if ($basis === 'product_cost') {
                $baseCost = static::productCostWithoutTax($productId, $variantId, $fallback);

                return round($baseCost * (1 + ($adjustmentPercent / 100)), 6);
            }

            $basePriceListId = (int) ($priceList->base_price_list_id ?? 0);

            if ($basePriceListId <= 0 || $basePriceListId === $priceListId) {
                return $fallback;
            }

            $basePriceWithoutTax = static::priceForProduct(
                $productId,
                $variantId,
                $basePriceListId,
                $fallback,
                $visited
            );

            return round($basePriceWithoutTax * (1 + ($adjustmentPercent / 100)), 6);
        }

        if (! Schema::hasTable('sales_price_list_items')) {
            return $fallback;
        }

        $today = now()->toDateString();

        $query = DB::table('sales_price_list_items')
            ->where('sales_price_list_id', $priceListId)
            ->where('is_active', true)
            ->where('product_id', $productId)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
            });

        if ($variantId > 0) {
            $specific = (clone $query)
                ->where('product_variant_id', $variantId)
                ->orderByDesc('min_quantity')
                ->first();

            if ($specific) {
                return (float) $specific->price_without_tax;
            }
        }

        $generic = $query
            ->whereNull('product_variant_id')
            ->orderByDesc('min_quantity')
            ->first();

        return $generic ? (float) $generic->price_without_tax : $fallback;
    }




    protected static function warehouseLabel(int $warehouseId): ?string
    {
        if ($warehouseId <= 0 || ! Schema::hasTable('warehouses')) {
            return null;
        }

        $warehouse = DB::table('warehouses')->where('id', $warehouseId)->first();

        if (! $warehouse) {
            return null;
        }

        $code = trim((string) ($warehouse->code ?? ''));
        $name = trim((string) ($warehouse->name ?? ''));

        if ($name === '') {
            $name = 'Almacén #' . $warehouseId;
        }

        return $code !== '' ? $code . ' - ' . $name : $name;
    }

    protected static function locationLabel(int $locationId): ?string
    {
        if ($locationId <= 0 || ! Schema::hasTable('stock_locations')) {
            return null;
        }

        $location = DB::table('stock_locations')->where('id', $locationId)->first();

        if (! $location) {
            return null;
        }

        $code = trim((string) ($location->code ?? ''));
        $name = trim((string) ($location->name ?? ''));

        if ($name === '') {
            $name = 'Ubicación #' . $locationId;
        }

        return $code !== '' ? $code . ' - ' . $name : $name;
    }


    protected static function warehouseOptions(): array
    {
        if (! Schema::hasTable('warehouses')) {
            return [];
        }

        $companyId = static::tenantCompanyId();

        return DB::table('warehouses')
            ->when($companyId > 0 && Schema::hasColumn('warehouses', 'company_id'), fn ($q) => $q->where('company_id', $companyId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function locationOptions(int $warehouseId = 0): array
    {
        if (! Schema::hasTable('stock_locations')) {
            return [];
        }

        $companyId = static::tenantCompanyId();

        return DB::table('stock_locations')
            ->when($companyId > 0 && Schema::hasColumn('stock_locations', 'company_id'), fn ($q) => $q->where('company_id', $companyId))
            ->when($warehouseId > 0, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function productSearchOptions(string $search): array
    {
        return ProductVariantSearch::options($search, static::tenantCompanyId(), true);
    }


    protected static function productOptionLabel(string $value): ?string
    {
        return ProductVariantSearch::labelFromKey($value, static::tenantCompanyId());
    }


    protected static function productInfoFromKey(string $value, int $priceListId = 0): array
    {
        $info = ProductVariantSearch::infoFromKey($value, static::tenantCompanyId());

        $productId = (int) ($info['product_id'] ?? 0);
        $variantId = (int) ($info['product_variant_id'] ?? 0);
        $fallback = (float) ($info['sale_price'] ?? 0);

        $info['sale_price'] = static::priceForProduct($productId, $variantId, $priceListId, $fallback);

        return $info;
    }


    protected static function productLabel(int $productId, int $variantId = 0): string
    {
        $product = $productId > 0
            ? DB::table('products')->where('id', $productId)->first()
            : null;

        if (! $product) {
            return 'Producto #' . $productId;
        }

        $label = static::baseProductLabel($product);

        if ($variantId > 0) {
            $variant = DB::table('products')->where('id', $variantId)->first();

            if ($variant) {
                $variantText = static::variantLabel($variant);

                if ($variantText !== '—') {
                    $label .= ' / ' . $variantText;
                }
            }
        }

        return $label;
    }

    protected static function baseProductLabel(object $product): string
    {
        $ref = trim((string) ($product->internal_reference ?? $product->sku ?? $product->barcode ?? ''));
        $name = trim((string) ($product->name ?? ('Producto #' . $product->id)));

        return trim(($ref !== '' ? $ref . ' - ' : '') . $name);
    }



    protected static function productCostWithoutTax(int $productId, int $variantId, float $fallback = 0): float
    {
        if (! Schema::hasTable('products')) {
            return $fallback;
        }

        $product = $productId > 0
            ? DB::table('products')->where('id', $productId)->first()
            : null;

        $variant = $variantId > 0
            ? DB::table('products')->where('id', $variantId)->first()
            : null;

        $source = $variant ?: $product;

        if (! $source) {
            return $fallback;
        }

        foreach ([
            'average_cost_without_tax',
            'standard_cost',
            'purchase_price',
            'last_purchase_cost',
        ] as $column) {
            if (property_exists($source, $column) && $source->{$column} !== null && (float) $source->{$column} > 0) {
                return (float) $source->{$column};
            }
        }

        if ($variant && $product) {
            foreach ([
                'average_cost_without_tax',
                'standard_cost',
                'purchase_price',
                'last_purchase_cost',
            ] as $column) {
                if (property_exists($product, $column) && $product->{$column} !== null && (float) $product->{$column} > 0) {
                    return (float) $product->{$column};
                }
            }
        }

        return $fallback;
    }

    protected static function fillLineTotals(Forms\Set $set, Forms\Get $get): void
    {
        $qty = (float) ($get('quantity') ?? 0);
        $price = (float) ($get('unit_price_without_tax') ?? 0);
        $taxRate = (float) ($get('tax_rate') ?? 0);

        $subtotal = round($qty * $price, 6);
        $tax = round($subtotal * ($taxRate / 100), 6);
        $total = round($subtotal + $tax, 6);

        $set('unit_price_with_tax', round($price * (1 + ($taxRate / 100)), 6));
        $set('line_total_without_tax', $subtotal);
        $set('line_tax', $tax);
        $set('line_total_with_tax', $total);
    }

    protected static function prepareLineData(array $data): array
    {
        if (! empty($data['product_key'])) {
            $info = static::productInfoFromKey((string) $data['product_key']);

            $data['product_id'] = $info['product_id'];
            $data['product_variant_id'] = $info['product_variant_id'];
            $data['product_label'] = $info['product_label'];
            $data['variant_label'] = $info['variant_label'];
        }

        unset($data['product_key']);

        $data['company_id'] = static::tenantCompanyId();

        $qty = (float) ($data['quantity'] ?? 0);
        $price = (float) ($data['unit_price_without_tax'] ?? 0);
        $taxRate = (float) ($data['tax_rate'] ?? 0);

        $subtotal = round($qty * $price, 6);
        $tax = round($subtotal * ($taxRate / 100), 6);

        $data['unit_price_with_tax'] = round($price * (1 + ($taxRate / 100)), 6);
        $data['line_total_without_tax'] = $subtotal;
        $data['line_tax'] = $tax;
        $data['line_total_with_tax'] = round($subtotal + $tax, 6);

        $requested = (float) ($data['quantity'] ?? 0);
        $delivered = (float) ($data['delivered_quantity'] ?? 0);

        if ($requested > 0 && $delivered >= $requested) {
            $data['delivery_status'] = 'delivered';
        } elseif ($delivered > 0) {
            $data['delivery_status'] = 'partial';
        } else {
            $data['delivery_status'] = 'pending';
        }

        return $data;
    }


    protected static function marginPreviewHtml(int $productId, int $variantId, float $priceWithoutTax): HtmlString
    {
        $info = static::marginInfo($productId, $variantId, $priceWithoutTax);

        $background = match ($info['status']) {
            'success' => '#dcfce7',
            'warning' => '#fef9c3',
            'danger' => '#fee2e2',
            default => '#f3f4f6',
        };

        $color = match ($info['status']) {
            'success' => '#166534',
            'warning' => '#854d0e',
            'danger' => '#991b1b',
            default => '#374151',
        };

        $icon = match ($info['status']) {
            'success' => '🟢',
            'warning' => '🟡',
            'danger' => '🔴',
            default => '⚪',
        };

        $html = sprintf(
            '<div style="display:inline-block;padding:6px 9px;border-radius:10px;background:%s;color:%s;font-weight:700;font-size:12px;line-height:1.2;">%s %s<br><span style="font-weight:500;font-size:11px;">Costo: $%s · Margen: %s%%</span></div>',
            $background,
            $color,
            $icon,
            e($info['label']),
            number_format((float) $info['unit_cost'], 2),
            number_format((float) $info['margin_percent'], 2)
        );

        return new HtmlString($html);
    }

    protected static function marginInfo(int $productId, int $variantId, float $priceWithoutTax): array
    {
        $unitCost = static::productCostWithoutTax($productId, $variantId, 0);

        $unitMargin = round($priceWithoutTax - $unitCost, 6);
        $marginPercent = $priceWithoutTax > 0 ? round(($unitMargin / $priceWithoutTax) * 100, 4) : 0;

        $status = 'no_cost';
        $label = 'Sin costo';

        if ($unitCost > 0) {
            if ($priceWithoutTax <= $unitCost) {
                $status = 'danger';
                $label = 'Pérdida';
            } elseif ($marginPercent < 15) {
                $status = 'warning';
                $label = 'Margen bajo';
            } else {
                $status = 'success';
                $label = 'Precio sano';
            }
        }

        return [
            'unit_cost' => $unitCost,
            'unit_margin' => $unitMargin,
            'margin_percent' => $marginPercent,
            'status' => $status,
            'label' => $label,
        ];
    }



    public static function marginApprovalStatusLabel(?string $state): string
    {
        return match ((string) $state) {
            'required' => 'Requiere aprobación',
            'pending' => 'Pendiente',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'not_required', '' => 'No requiere',
            default => ucfirst(str_replace('_', ' ', (string) $state)),
        };
    }

    public static function ensureMarginApprovalBeforeConfirm(SaleOrder $record): bool
    {
        $result = \App\Support\SalesApprovalWorkflow::ensureCanConfirm($record);

        if ($result['ok'] ?? false) {
            return true;
        }

        Notification::make()
            ->title('La cotización requiere aprobación')
            ->body($result['message'] ?? 'Se generó una solicitud de aprobación.')
            ->warning()
            ->send();

        return false;
    }


    public static function canApproveMargin(SaleOrder $record): bool
    {
        if (! in_array((string) ($record->margin_approval_status ?? ''), ['required', 'pending', 'rejected'], true)) {
            return false;
        }

        $summary = static::marginRiskSummary($record);

        if (! $summary['requires_approval']) {
            return false;
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            return true;
        }

        if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
            return true;
        }

        if ((int) ($record->margin_approval_user_id ?? 0) === (int) $user->id) {
            return true;
        }

        return $user->can('sales.approve_margin');
    }

    protected static function marginRiskSummary(SaleOrder $record): array
    {
        if (! Schema::hasTable('sales_order_lines')) {
            return [
                'requires_approval' => false,
                'warning_count' => 0,
                'danger_count' => 0,
                'reason' => null,
            ];
        }

        $risk = DB::table('sales_order_lines')
            ->where('sales_order_id', $record->id)
            ->whereIn('margin_status', ['warning', 'danger'])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN margin_status = 'danger' THEN 1 ELSE 0 END), 0) as danger_count,
                COALESCE(SUM(CASE WHEN margin_status = 'warning' THEN 1 ELSE 0 END), 0) as warning_count
            ")
            ->first();

        $dangerCount = (int) ($risk->danger_count ?? 0);
        $warningCount = (int) ($risk->warning_count ?? 0);

        $parts = [];

        if ($dangerCount > 0) {
            $parts[] = "{$dangerCount} línea(s) con precio debajo del costo";
        }

        if ($warningCount > 0) {
            $parts[] = "{$warningCount} línea(s) con margen bajo";
        }

        return [
            'requires_approval' => ($dangerCount + $warningCount) > 0,
            'warning_count' => $warningCount,
            'danger_count' => $dangerCount,
            'reason' => count($parts) > 0 ? implode('. ', $parts) . '.' : null,
        ];
    }

    protected static function defaultMarginApproverId(): ?int
    {
        $workflowApproverId = static::defaultMarginApproverFromWorkflow();

        if ($workflowApproverId) {
            return $workflowApproverId;
        }

        $user = auth()->user();

        if ($user && isset($user->approval_manager_user_id) && $user->approval_manager_user_id) {
            return (int) $user->approval_manager_user_id;
        }

        if (Schema::hasTable('users') && Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
            $adminId = DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereIn('roles.name', ['Admin Empresa', 'Admin Grupo', 'admin', 'Administrador'])
                ->orderBy('users.id')
                ->value('users.id');

            if ($adminId) {
                return (int) $adminId;
            }
        }

        return auth()->id() ? (int) auth()->id() : null;
    }

    protected static function defaultMarginApproverFromWorkflow(): ?int
    {
        $order = new SaleOrder();

        $order->company_id = static::tenantCompanyId();

        $workflow = \App\Support\SalesApprovalWorkflow::findApplicableWorkflow($order, ['sales_quote', 'sales_margin_approval']);

        return \App\Support\SalesApprovalWorkflow::firstApproverUserId($workflow);
    }



    public static function filterSalesOrderColumns(array $data): array
    {
        if (! Schema::hasTable('sales_orders')) {
            return [];
        }

        $columns = Schema::getColumnListing('sales_orders');

        return array_filter(
            $data,
            fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }



    public static function listTabForRecord(SaleOrder $record): string
    {
        $approvalStatus = (string) ($record->margin_approval_status ?? '');

        if (in_array($approvalStatus, ['required', 'pending', 'rejected'], true)) {
            return 'por_aprobar';
        }

        return match ((string) ($record->status ?? '')) {
            'draft' => 'cotizaciones',
            'confirmed', 'partially_delivered', 'delivered' => 'ordenes',
            'cancelled' => 'canceladas',
            default => 'todas',
        };
    }

    public static function listUrlForTab(?string $tab = null): string
    {
        return static::getUrl('index', [
            'activeTab' => $tab ?: 'cotizaciones',
        ]);
    }



    public static function duplicateAsQuote(SaleOrder $record): SaleOrder
    {
        $newId = DB::transaction(function () use ($record): int {
            $now = now();

            $orderColumns = Schema::getColumnListing('sales_orders');

            $original = DB::table('sales_orders')
                ->where('id', $record->id)
                ->first();

            if (! $original) {
                throw new \RuntimeException('No se encontró la cotización/orden original.');
            }

            $data = [];

            foreach ($orderColumns as $column) {
                if (in_array($column, ['id', 'created_at', 'updated_at'], true)) {
                    continue;
                }

                $data[$column] = $original->{$column} ?? null;
            }

            $data['number'] = static::nextSalesOrderNumber((int) ($original->company_id ?? 0));
            $data['status'] = 'draft';
            $data['order_date'] = $now;
            $data['expected_delivery_date'] = null;
            $data['source_type'] = 'duplicated';
            $data['source_id'] = $record->id;
            $data['source_reference'] = $original->number ?? null;
            $data['delivered_total_quantity'] = 0;
            $data['confirmed_at'] = null;
            $data['confirmed_by_user_id'] = null;
            $data['created_by_user_id'] = auth()->id();
            $data['invoice_status'] = 'not_invoiced';
            $data['payment_status'] = 'unpaid';

            foreach ([
                'margin_approval_required',
                'margin_approval_status',
                'margin_approval_user_id',
                'margin_approval_reason',
                'margin_approval_requested_at',
                'margin_approved_by_user_id',
                'margin_approved_at',
                'margin_rejected_by_user_id',
                'margin_rejected_at',
                'margin_rejection_reason',
            ] as $approvalColumn) {
                if (! array_key_exists($approvalColumn, $data)) {
                    continue;
                }

                $data[$approvalColumn] = match ($approvalColumn) {
                    'margin_approval_required' => false,
                    'margin_approval_status' => 'not_required',
                    default => null,
                };
            }

            $data['created_at'] = $now;
            $data['updated_at'] = $now;

            if (array_key_exists('price_list_applied_id', array_flip($orderColumns))) {
                $data['price_list_applied_id'] = $data['price_list_id'] ?? null;
            }

            if (array_key_exists('price_list_applied_at', array_flip($orderColumns))) {
                $data['price_list_applied_at'] = $now;
            }

            $data = static::filterSalesOrderColumns($data);

            $newId = (int) DB::table('sales_orders')->insertGetId($data);

            if (Schema::hasTable('sales_order_lines')) {
                $lineColumns = Schema::getColumnListing('sales_order_lines');

                DB::table('sales_order_lines')
                    ->where('sales_order_id', $record->id)
                    ->orderBy('id')
                    ->get()
                    ->each(function ($line) use ($lineColumns, $newId, $now): void {
                        $lineData = [];

                        foreach ($lineColumns as $column) {
                            if (in_array($column, ['id', 'created_at', 'updated_at'], true)) {
                                continue;
                            }

                            $lineData[$column] = $line->{$column} ?? null;
                        }

                        $lineData['sales_order_id'] = $newId;
                        $lineData['created_at'] = $now;
                        $lineData['updated_at'] = $now;

                        $lineData = array_filter(
                            $lineData,
                            fn ($value, $key) => in_array($key, $lineColumns, true),
                            ARRAY_FILTER_USE_BOTH
                        );

                        DB::table('sales_order_lines')->insert($lineData);
                    });
            }

            if (class_exists(\App\Support\SalesApprovalWorkflow::class)) {
                $newOrder = DB::table('sales_orders')->where('id', $newId)->first();

                if ($newOrder) {
                    \App\Support\SalesApprovalWorkflow::logEvent(
                        $newOrder,
                        'duplicated',
                        'Cotización duplicada',
                        'Se creó a partir de ' . ($original->number ?? ('ID ' . $record->id)) . '.',
                        [
                            'source_sales_order_id' => $record->id,
                            'source_number' => $original->number ?? null,
                        ],
                        auth()->id()
                    );
                }
            }


            if (class_exists(\App\Support\SalesApprovalWorkflow::class)) {
                $newOrder = DB::table('sales_orders')->where('id', $newId)->first();

                if ($newOrder) {
                    $summary = \App\Support\SalesApprovalWorkflow::approvalRequirementSummary($newOrder);
                    $requiresApproval = (bool) ($summary['requires_approval'] ?? false);

                    DB::table('sales_orders')
                        ->where('id', $newId)
                        ->update(static::filterSalesOrderColumns([
                            'margin_approval_required' => $requiresApproval,
                            'margin_approval_status' => $requiresApproval ? 'required' : 'not_required',
                            'margin_approval_reason' => $summary['reason'] ?? null,
                            'margin_approval_requested_at' => null,
                            'margin_approved_by_user_id' => null,
                            'margin_approved_at' => null,
                            'margin_rejected_by_user_id' => null,
                            'margin_rejected_at' => null,
                            'margin_rejection_reason' => null,
                            'updated_at' => now(),
                        ]));

                    \App\Support\SalesApprovalWorkflow::logEvent(
                        $newOrder,
                        'approval_evaluated',
                        'Aprobación evaluada',
                        $requiresApproval
                            ? 'La cotización duplicada requiere aprobación según el flujo configurado.'
                            : 'La cotización duplicada no requiere aprobación.',
                        [
                            'requires_approval' => $requiresApproval,
                            'reason' => $summary['reason'] ?? null,
                        ],
                        auth()->id()
                    );
                }
            }

            return $newId;
        });

        return SaleOrder::query()->findOrFail($newId);
    }

    protected static function nextSalesOrderNumber(int $companyId = 0): string
    {
        $prefix = 'VTA-' . now()->format('Ymd') . '-';

        $query = DB::table('sales_orders')
            ->where('number', 'like', $prefix . '%');

        if ($companyId > 0 && Schema::hasColumn('sales_orders', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $last = $query
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($last && preg_match('/(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }



    public static function sourceTypeLabel(?string $state): string
    {
        return match ((string) $state) {
            'manual' => 'Manual',
            'duplicated' => 'Duplicada',
            'duplicate' => 'Duplicada',
            'crm' => 'CRM',
            'pos' => 'Punto de venta',
            'ecommerce' => 'E-commerce',
            'api' => 'API',
            default => $state ? ucfirst(str_replace('_', ' ', (string) $state)) : '—',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | V5.61.2a Cotizacion a PDV
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | V5.61.2f Validar cotizacion para PDV
    |--------------------------------------------------------------------------
    */

    public static function canValidateQuote(SaleOrder $record): bool
    {
        if ((string) ($record->status ?? '') !== 'draft') {
            return false;
        }

        if (! self::quoteHasLines($record)) {
            return false;
        }

        $validationStatus = self::quoteValidationStatus($record);

        if (in_array($validationStatus, ['validated', 'pending_approval'], true)) {
            return false;
        }

        return true;
    }

    public static function isQuoteValidatedForPos(SaleOrder $record): bool
    {
        if ((string) ($record->status ?? '') !== 'draft') {
            return false;
        }

        if (! self::quoteHasLines($record)) {
            return false;
        }

        $validationStatus = self::quoteValidationStatus($record);
        $approvalStatus = (string) ($record->margin_approval_status ?? 'not_required');

        if ($validationStatus === 'validated') {
            return true;
        }

        if ($validationStatus === 'pending_approval' && $approvalStatus === 'approved') {
            return true;
        }

        return false;
    }

    public static function quoteValidationStatus(SaleOrder $record): string
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_validation_status')) {
            return (string) ($record->quote_validation_status ?? 'not_validated');
        }

        return 'not_validated';
    }


    public static function validateQuoteForPos(SaleOrder $record): array
    {
        if (! self::quoteHasLines($record)) {
            return [
                'ok' => false,
                'status' => 'missing_lines',
                'message' => 'Agrega al menos un producto antes de validar la cotización.',
            ];
        }

        if ((string) ($record->status ?? '') !== 'draft') {
            return [
                'ok' => false,
                'status' => 'invalid_status',
                'message' => 'Solo se pueden validar cotizaciones en borrador.',
            ];
        }

        $currentValidationStatus = self::quoteValidationStatus($record);

        if ($currentValidationStatus === 'validated') {
            return [
                'ok' => true,
                'status' => 'already_validated',
                'message' => 'La cotización ya está validada.',
            ];
        }

        $approvalRequired = (bool) ($record->margin_approval_required ?? false);
        $approvalStatus = (string) ($record->margin_approval_status ?? 'not_required');
        $now = now();

        if ($approvalRequired && $approvalStatus !== 'approved') {
            $data = [
                'margin_approval_status' => 'pending',
                'margin_approval_requested_at' => $now,
                'margin_approval_user_id' => auth()->id(),
                'margin_approval_reason' => 'Cotización enviada a validación antes de enviarse a PDV o convertirse en orden de venta.',
                'updated_at' => $now,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_validation_status')) {
                $data['quote_validation_status'] = 'pending_approval';
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_validation_message')) {
                $data['quote_validation_message'] = 'Pendiente de aprobación.';
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'approval_snapshot_at')) {
                $data['approval_snapshot_at'] = $now;
            }

            $record->forceFill($data)->save();

            return [
                'ok' => true,
                'status' => 'pending_approval',
                'message' => 'La cotización fue enviada a aprobación.',
            ];
        }

        $data = [
            'updated_at' => $now,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_validation_status')) {
            $data['quote_validation_status'] = 'validated';
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_validated_at')) {
            $data['quote_validated_at'] = $now;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_validated_by_user_id')) {
            $data['quote_validated_by_user_id'] = auth()->id();
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_validation_message')) {
            $data['quote_validation_message'] = 'Cotización validada para PDV / orden de venta.';
        }

        if (! $approvalRequired && \Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'margin_approval_status')) {
            $data['margin_approval_required'] = false;
            $data['margin_approval_status'] = 'not_required';
        }

        if ($approvalStatus === 'approved' && \Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'margin_approved_at')) {
            $data['margin_approved_at'] = $record->margin_approved_at ?: $now;
        }

        $record->forceFill($data)->save();

        return [
            'ok' => true,
            'status' => 'validated',
            'message' => 'La cotización quedó validada. Ya puede enviarse a PDV o convertirse en orden de venta.',
        ];
    }


    public static function validateQuoteHeaderAction(SaleOrder $record): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('validate_quote')
            ->label('Validar cotización')
            ->icon('heroicon-o-check-badge')
            ->color('warning')
            ->visible(fn (): bool => self::canValidateQuote($record))
            ->requiresConfirmation()
            ->modalHeading('Validar cotización')
            ->modalDescription('Se validará la cotización antes de enviarla a PDV o convertirla en orden de venta. Si requiere aprobación, quedará pendiente.')
            ->modalSubmitActionLabel('Validar')
            ->action(function () use ($record): void {
                $result = self::validateQuoteForPos($record);

                \Filament\Notifications\Notification::make()
                    ->title(($result['ok'] ?? false) ? 'Cotización validada' : 'No se pudo validar')
                    ->body((string) ($result['message'] ?? ''))
                    ->{($result['ok'] ?? false) ? 'success' : 'danger'}()
                    ->send();
            });
    }

    public static function sendQuoteToPosTableAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('send_quote_to_pos')
            ->label('Enviar a PDV')
            ->icon('heroicon-o-shopping-cart')
            ->color('success')
            ->visible(fn (SaleOrder $record): bool => self::canSendQuoteToPos($record))
            ->modalHeading('Enviar cotización a PDV')
            ->modalSubmitActionLabel('Generar ticket pendiente')
            ->modalCancelActionLabel(fn (SaleOrder $record): string => self::quoteHasPendingPosTicket($record) ? 'Cerrar ventana' : 'Cancelar')
            ->modalSubmitAction(fn ($action, SaleOrder $record) => $action
                ->label('Generar ticket pendiente')
                ->visible(fn (): bool => ! self::quoteHasPendingPosTicket($record)))
            ->modalWidth('2xl')
            ->form(fn (SaleOrder $record): array => self::quoteToPosFormSchema($record))
            ->action(fn (SaleOrder $record, array $data): mixed => self::sendQuoteToPosFromAction($record, $data));
    }


    public static function sendQuoteToPosHeaderAction(SaleOrder $record): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('send_quote_to_pos')
            ->label('Enviar a PDV')
            ->icon('heroicon-o-shopping-cart')
            ->color('success')
            ->visible(fn (): bool => self::canSendQuoteToPos($record))
            ->modalHeading('Enviar cotización a PDV')
            ->modalSubmitActionLabel('Generar ticket pendiente')
            ->modalCancelActionLabel(fn (): string => self::quoteHasPendingPosTicket($record) ? 'Cerrar ventana' : 'Cancelar')
            ->modalSubmitAction(fn ($action) => $action
                ->label('Generar ticket pendiente')
                ->visible(fn (): bool => ! self::quoteHasPendingPosTicket($record)))
            ->modalWidth('2xl')
            ->form(fn (): array => self::quoteToPosFormSchema($record))
            ->action(fn (array $data): mixed => self::sendQuoteToPosFromAction($record, $data));
    }


    public static function canSendQuoteToPos(SaleOrder $record): bool
    {
        if (! self::isQuoteValidatedForPos($record)) {
            return false;
        }

        if ((string) ($record->payment_status ?? '') === 'paid') {
            return false;
        }

        if (! self::hasOpenPosSessionForQuote($record)) {
            return false;
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('sales_quote_pos_tickets')) {
            $hasPendingTicket = \Illuminate\Support\Facades\DB::table('sales_quote_pos_tickets')
                ->where('sales_order_id', (int) $record->getKey())
                ->whereIn('status', ['pending', 'sent'])
                ->exists();

            if ($hasPendingTicket) {
                return false;
            }
        }

        return true;
    }

    public static function hasOpenPosSessionForQuote(SaleOrder $record): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('pos_points')) {
            return false;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('pos_sessions')) {
            return false;
        }

        $companyId = (int) ($record->company_id ?? 0);

        if ($companyId <= 0) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('pos_points as pp')
            ->join('pos_sessions as ps', 'ps.pos_point_id', '=', 'pp.id')
            ->where('pp.company_id', $companyId)
            ->where('pp.status', 'active')
            ->where('ps.status', 'open')
            ->exists();
    }





    public static function quoteHasLines(SaleOrder $record): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_order_lines')) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('sales_order_lines')
            ->where('sales_order_id', (int) $record->getKey())
            ->where('quantity', '>', 0)
            ->exists();
    }

    public static function quoteToPosFormSchema(SaleOrder $record): array
    {
        return [
            \Filament\Forms\Components\Placeholder::make('pending_pos_ticket_notice')
                ->label('')
                ->content(fn () => self::quotePendingPosTicketNotice($record))
                ->visible(function () use ($record): bool {
                    if (! \Illuminate\Support\Facades\Schema::hasTable('sales_quote_pos_tickets')) {
                        return false;
                    }

                    return \Illuminate\Support\Facades\DB::table('sales_quote_pos_tickets')
                        ->where('sales_order_id', (int) $record->getKey())
                        ->whereIn('status', ['pending', 'sent'])
                        ->exists();
                }),

            \Filament\Forms\Components\Placeholder::make('quote_summary')
                ->label('Cotización')
                ->content(function () use ($record): \Illuminate\Support\HtmlString {
                    $number = htmlspecialchars((string) ($record->number ?? 'Sin folio'), ENT_QUOTES, 'UTF-8');
                    $customer = htmlspecialchars((string) ($record->customer_name ?? 'Cliente no definido'), ENT_QUOTES, 'UTF-8');
                    $total = number_format((float) ($record->total_with_tax ?? 0), 2);

                    return new \Illuminate\Support\HtmlString(
                        '<div style="line-height:1.45">'
                        . '<strong>' . $number . '</strong><br>'
                        . 'Cliente: ' . $customer . '<br>'
                        . 'Total cotizado: $' . $total . '<br>'
                        . '<span style="color:#64748b">Los precios, descuentos e impuestos se copiarán exactamente desde la cotización. El PDV no aplicará listas de precios.</span>'
                        . '</div>'
                    );
                }),

            \Filament\Forms\Components\Select::make('pos_point_id')
                ->label('PDV destino')
                ->options(fn (): array => self::quoteToPosPointOptions($record))
                ->searchable()
                ->live()
                ->required()
                ->helperText('Solo se muestran PDVs activos con sesión abierta. Al generar el ticket se validará la existencia contra el almacén y ubicación configurados en ese PDV.'),

            \Filament\Forms\Components\Placeholder::make('pos_validation_preview')
                ->label('Validación')
                ->content(function (\Filament\Forms\Get $get) use ($record): \Illuminate\Support\HtmlString {
                    $posPointId = (int) ($get('pos_point_id') ?? 0);

                    if ($posPointId <= 0) {
                        return new \Illuminate\Support\HtmlString(
                            '<div style="padding:10px;border-radius:10px;background:#f8fafc;color:#64748b;">Selecciona un PDV con sesión abierta para validar existencia.</div>'
                        );
                    }

                    try {
                        $result = app(\App\Support\Sales\QuoteToPendingPosTicketService::class)
                            ->validateQuoteForPosPoint((int) $record->getKey(), $posPointId);
                    } catch (\Throwable $e) {
                        return new \Illuminate\Support\HtmlString(
                            '<div style="padding:10px;border-radius:10px;background:#fef2f2;color:#991b1b;">'
                            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                            . '</div>'
                        );
                    }

                    $errors = $result['errors'] ?? [];
                    $warnings = $result['warnings'] ?? [];
                    $stockLines = $result['stock_lines'] ?? [];

                    if (! empty($errors)) {
                        $html = '<div style="padding:10px;border-radius:10px;background:#fef2f2;color:#991b1b;">';
                        $html .= '<strong>No se puede generar el ticket pendiente:</strong><ul style="margin:6px 0 0 18px;">';

                        foreach ($errors as $error) {
                            $html .= '<li>' . htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') . '</li>';
                        }

                        $html .= '</ul></div>';

                        return new \Illuminate\Support\HtmlString($html);
                    }

                    $html = '<div style="padding:10px;border-radius:10px;background:#ecfdf5;color:#065f46;">';
                    $html .= '<strong>Validación correcta.</strong><br>El PDV tiene sesión abierta y la existencia es suficiente para los productos inventariables.';

                    if (! empty($warnings)) {
                        $html .= '<ul style="margin:6px 0 0 18px;">';

                        foreach ($warnings as $warning) {
                            $html .= '<li>' . htmlspecialchars((string) $warning, ENT_QUOTES, 'UTF-8') . '</li>';
                        }

                        $html .= '</ul>';
                    }

                    if (! empty($stockLines)) {
                        $html .= '<div style="margin-top:8px;font-size:12px;color:#065f46;">';

                        foreach ($stockLines as $line) {
                            $product = htmlspecialchars((string) ($line['product_name'] ?? 'Producto'), ENT_QUOTES, 'UTF-8');
                            $required = $line['required_quantity'] ?? '';
                            $available = $line['available_quantity'] ?? 'N/A';
                            $status = htmlspecialchars((string) ($line['status'] ?? ''), ENT_QUOTES, 'UTF-8');

                            $html .= $product . ' — requerido: ' . $required . ', disponible: ' . $available . ' (' . $status . ')<br>';
                        }

                        $html .= '</div>';
                    }

                    $html .= '</div>';

                    return new \Illuminate\Support\HtmlString($html);
                }),

            \Filament\Forms\Components\Textarea::make('note')
                ->label('Nota para caja')
                ->rows(3)
                ->maxLength(500)
                ->placeholder('Ejemplo: Cliente pasará hoy a pagar la cotización.'),
        ];
    }

    public static function quotePendingPosTicketNotice(SaleOrder $record): \Illuminate\Support\HtmlString
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_quote_pos_tickets')) {
            return new \Illuminate\Support\HtmlString('');
        }

        $ticket = \Illuminate\Support\Facades\DB::table('sales_quote_pos_tickets as sqpt')
            ->leftJoin('pos_orders as po', 'po.id', '=', 'sqpt.pos_order_id')
            ->leftJoin('pos_points as pp', 'pp.id', '=', 'sqpt.pos_point_id')
            ->leftJoin('pos_sessions as ps', 'ps.id', '=', 'sqpt.pos_session_id')
            ->where('sqpt.sales_order_id', (int) $record->getKey())
            ->whereIn('sqpt.status', ['pending', 'sent'])
            ->orderByDesc('sqpt.id')
            ->select([
                'sqpt.id',
                'sqpt.public_token',
                'sqpt.status',
                'sqpt.pos_order_id',
                'sqpt.pos_point_id',
                'sqpt.pos_session_id',
                'po.number as pos_order_number',
                'po.total as pos_order_total',
                'pp.name as pos_point_name',
                'pp.code as pos_point_code',
                'ps.number as pos_session_number',
            ])
            ->first();

        if (! $ticket) {
            return new \Illuminate\Support\HtmlString('');
        }

        $number = htmlspecialchars((string) ($ticket->pos_order_number ?? 'Ticket pendiente'), ENT_QUOTES, 'UTF-8');
        $pos = htmlspecialchars(trim((string) ($ticket->pos_point_name ?? 'PDV') . ' ' . (($ticket->pos_point_code ?? '') ? '(' . $ticket->pos_point_code . ')' : '')), ENT_QUOTES, 'UTF-8');
        $total = number_format((float) ($ticket->pos_order_total ?? 0), 2);

        $printUrl = $ticket->pos_order_id
            ? route('pos.orders.pending-ticket.print', ['order' => (int) $ticket->pos_order_id])
            : null;

        $html = '<div style="padding:12px;border-radius:12px;background:#ecfdf5;color:#065f46;border:1px solid #bbf7d0;">';
        $html .= '<strong>Ticket pendiente generado correctamente.</strong><br>';
        $html .= 'Ticket: <strong>' . $number . '</strong><br>';
        $html .= 'PDV: ' . $pos . '<br>';
        $html .= 'Total: $' . $total . '<br>';

        $html .= '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">';

        if ($printUrl) {
            $html .= '<a href="' . htmlspecialchars($printUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" style="display:inline-block;padding:7px 10px;border-radius:8px;background:#2563eb;color:white;text-decoration:none;font-weight:600;">Imprimir ticket</a>';
        }


        $html .= '</div>';
        $html .= '<div style="margin-top:8px;font-size:12px;color:#047857;">El cajero lo verá en PDV &gt; Tickets pendientes.</div>';
        $html .= '</div>';

        return new \Illuminate\Support\HtmlString($html);
    }


    public static function quoteHasPendingPosTicket(SaleOrder $record): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_quote_pos_tickets')) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('sales_quote_pos_tickets')
            ->where('sales_order_id', (int) $record->getKey())
            ->whereIn('status', ['pending', 'sent'])
            ->exists();
    }

    public static function quoteIsSentToPos(SaleOrder $record): bool
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_pos_payment_status')) {
            if ((string) ($record->quote_pos_payment_status ?? '') === 'sent') {
                return true;
            }
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_quote_pos_tickets')) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('sales_quote_pos_tickets')
            ->where('sales_order_id', (int) $record->getKey())
            ->whereIn('status', ['pending', 'sent'])
            ->exists();
    }

    public static function quoteIsPaidInPos(SaleOrder $record): bool
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_pos_payment_status')) {
            if ((string) ($record->quote_pos_payment_status ?? '') === 'paid') {
                return true;
            }
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_quote_pos_tickets')) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('sales_quote_pos_tickets')
            ->where('sales_order_id', (int) $record->getKey())
            ->where('status', 'paid')
            ->exists();
    }

    public static function quoteDisplayStatusLabel(SaleOrder $record): string
    {
        if (self::quoteIsPaidInPos($record)) {
            return 'Cobrado en PDV';
        }

        if (self::quoteIsSentToPos($record)) {
            return 'Enviado a PDV';
        }

        $status = (string) ($record->status ?? '');

        return match ($status) {
            'draft' => 'Cotización',
            'pending_approval' => 'Pendiente de aprobación',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'cancelled' => 'Cancelada',
            'confirmed' => 'Orden de venta',
            'partially_delivered' => 'Parcialmente entregada',
            'delivered' => 'Entregada',
            default => \Illuminate\Support\Str::headline(str_replace('_', ' ', $status ?: 'cotizacion')),
        };
    }

    public static function quoteDisplayStatusColor(SaleOrder $record): string
    {
        if (self::quoteIsPaidInPos($record)) {
            return 'success';
        }

        if (self::quoteIsSentToPos($record)) {
            return 'warning';
        }

        $status = (string) ($record->status ?? '');

        return match ($status) {
            'approved' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'danger',
            'pending_approval' => 'warning',
            'confirmed' => 'info',
            'partially_delivered' => 'info',
            'delivered' => 'success',
            default => 'gray',
        };
    }








    public static function quoteToPosPointOptions(SaleOrder $record): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('pos_points')) {
            return [];
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('pos_sessions')) {
            return [];
        }

        $companyId = (int) ($record->company_id ?? 0);

        $warehouseLabel = function ($warehouseId): string {
            $warehouseId = (int) ($warehouseId ?? 0);

            if ($warehouseId <= 0) {
                return 'Sin almacén';
            }

            foreach (['stock_warehouses', 'warehouses', 'inventory_warehouses'] as $table) {
                if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                    continue;
                }

                $warehouse = \Illuminate\Support\Facades\DB::table($table)
                    ->where('id', $warehouseId)
                    ->first();

                if (! $warehouse) {
                    continue;
                }

                $name = '';
                $code = '';

                foreach (['name', 'warehouse_name', 'display_name', 'description'] as $column) {
                    if (property_exists($warehouse, $column) && trim((string) $warehouse->{$column}) !== '') {
                        $name = trim((string) $warehouse->{$column});
                        break;
                    }
                }

                foreach (['code', 'warehouse_code', 'reference', 'short_code'] as $column) {
                    if (property_exists($warehouse, $column) && trim((string) $warehouse->{$column}) !== '') {
                        $code = trim((string) $warehouse->{$column});
                        break;
                    }
                }

                $label = $name !== '' ? $name : ('Almacén #' . $warehouseId);

                if ($code !== '') {
                    $label .= ' - ' . $code;
                }

                return $label;
            }

            return 'Almacén #' . $warehouseId;
        };

        $locationLabel = function ($locationId): string {
            $locationId = (int) ($locationId ?? 0);

            if ($locationId <= 0) {
                return 'Sin ubicación';
            }

            if (! \Illuminate\Support\Facades\Schema::hasTable('stock_locations')) {
                return 'Ubicación #' . $locationId;
            }

            $location = \Illuminate\Support\Facades\DB::table('stock_locations')
                ->where('id', $locationId)
                ->first();

            if (! $location) {
                return 'Ubicación #' . $locationId;
            }

            $name = trim((string) ($location->name ?? ''));
            $code = trim((string) ($location->code ?? ''));

            $label = $name !== '' ? $name : ('Ubicación #' . $locationId);

            if ($code !== '') {
                $label .= ' - ' . $code;
            }

            return $label;
        };

        return \Illuminate\Support\Facades\DB::table('pos_points as pp')
            ->join('pos_sessions as ps', 'ps.pos_point_id', '=', 'pp.id')
            ->where('pp.company_id', $companyId)
            ->where('pp.status', 'active')
            ->where('ps.status', 'open')
            ->orderBy('pp.name')
            ->select([
                'pp.*',
                'ps.id as open_session_id',
                'ps.number as open_session_number',
            ])
            ->get()
            ->mapWithKeys(function ($posPoint) use ($warehouseLabel, $locationLabel): array {
                $name = trim((string) ($posPoint->name ?? 'PDV'));
                $code = trim((string) ($posPoint->code ?? ''));

                $posLabel = $name;

                if ($code !== '') {
                    $posLabel .= ' (' . $code . ')';
                }

                $warehouse = $warehouseLabel($posPoint->warehouse_id ?? null);
                $location = $locationLabel($posPoint->stock_location_id ?? null);
                $session = 'Sesión abierta #' . (int) $posPoint->open_session_id;

                return [
                    (int) $posPoint->id => $posLabel . ' - ' . $warehouse . ' / ' . $location . ' - ' . $session,
                ];
            })
            ->all();
    }




    public static function sendQuoteToPosFromAction(SaleOrder $record, array $data): void
    {
        try {
            $result = app(\App\Support\Sales\QuoteToPendingPosTicketService::class)
                ->createPendingTicket(
                    salesOrderId: (int) $record->getKey(),
                    posPointId: (int) ($data['pos_point_id'] ?? 0),
                    posSessionId: null,
                    userId: auth()->id(),
                    note: $data['note'] ?? null,
                );


            // V5.61.2p2: marcar cotizacion como enviada a PDV.
            if (\Illuminate\Support\Facades\Schema::hasTable('sales_orders')) {
                $quoteUpdate = [
                    'updated_at' => now(),
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_pos_payment_status')) {
                    $quoteUpdate['quote_pos_payment_status'] = 'sent';
                }

                if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_pos_order_id') && ! empty($result['pos_order_id'])) {
                    $quoteUpdate['quote_pos_order_id'] = (int) $result['pos_order_id'];
                }

                \Illuminate\Support\Facades\DB::table('sales_orders')
                    ->where('id', (int) $record->getKey())
                    ->update($quoteUpdate);
            }

            \Filament\Notifications\Notification::make()
                ->title('Ticket pendiente generado')
                ->body('Ticket ' . ($result['pos_order_number'] ?? '') . ' creado desde la cotización. También quedó visible dentro del modal.')
                ->success()
                ->send();

            // V5.61.2l: mantener el modal abierto para mostrar liga del ticket generado.
            throw new \Filament\Support\Exceptions\Halt();
        } catch (\Throwable $e) {
            \Filament\Notifications\Notification::make()
                ->title('No se pudo enviar a PDV')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw new \Filament\Support\Exceptions\Halt();
        }
    }




        public static function getPages(): array
    {
        return [
            'index' => Pages\ListSaleOrders::route('/'),
            'quotes' => Pages\ListSaleQuotes::route('/quotes'),
            'orders' => Pages\ListSaleOrdersOnly::route('/orders'),
            'create' => Pages\CreateSaleOrder::route('/create'),
            'view' => Pages\ViewSaleOrder::route('/{record}'),
            'edit' => Pages\EditSaleOrder::route('/{record}/edit'),
        ];
    }

}
