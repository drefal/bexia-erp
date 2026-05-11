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

    protected static ?string $navigationLabel = 'Cotizaciones / Órdenes';

    protected static ?int $navigationSort = 300;
protected static ?string $modelLabel = 'cotización';

    protected static ?string $pluralModelLabel = 'cotizaciones / órdenes';
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
                Forms\Components\Section::make('Encabezado de cotización / orden')
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
                                            ->default(fn (): ?int => static::defaultUserWarehouseId())
                                            ->reactive()
                                            ->helperText('Este almacén será usado para reservar y entregar la venta.'),

                                        Forms\Components\Select::make('location_id')
                                            ->label('Ubicación origen')
                                            ->searchable()
                                            ->preload()
                                            ->options(fn (Forms\Get $get): array => static::locationOptions((int) ($get('warehouse_id') ?? 0)))
                                            ->default(fn (): ?int => static::defaultUserLocationId())
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

                Tables\Actions\Action::make('confirm')
                    ->label('Confirmar cotización')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SaleOrder $record): bool => $record->status === 'draft' && static::userCanPermission('sales.confirm'))
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar cotización')
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
        $id = auth()->user()?->default_warehouse_id ?? null;

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


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSaleOrders::route('/'),
            'create' => Pages\CreateSaleOrder::route('/create'),
            'view' => Pages\ViewSaleOrder::route('/{record}'),
            'delivery' => Pages\DeliverSaleOrder::route('/{record}/delivery'),
            'edit' => Pages\EditSaleOrder::route('/{record}/edit'),
        ];
    }
}
