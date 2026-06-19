<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RepairOrderResource\Pages;
use App\Models\RepairOrder;
use App\Models\ServiceCaseEvent;
use App\Support\Service\ServiceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RepairOrderResource extends Resource
{
    protected static ?string $model = RepairOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Atencion y Servicio';

    protected static ?string $navigationLabel = 'Reparaciones';

    protected static ?string $modelLabel = 'reparacion';

    protected static ?string $pluralModelLabel = 'reparaciones';

    protected static ?int $navigationSort = 20;

    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return ServiceAccess::can([
            'service.menu.view',
            'service.repairs.view',
            'service.repairs.create',
            'service.repairs.update',
        ]);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return ServiceAccess::can('service.repairs.create');
    }

    public static function canEdit(Model $record): bool
    {
        if (in_array((string) ($record->status ?? ''), ['entregado', 'cerrado', 'rechazado', 'cancelado'], true)) {
            return static::canReopen();
        }

        return ServiceAccess::can('service.repairs.update');
    }

    public static function canDelete(Model $record): bool
    {
        return ServiceAccess::can('service.repairs.delete');
    }

    public static function canDeleteAny(): bool
    {
        return ServiceAccess::can('service.repairs.delete');
    }

    public static function canApproveWarranty(): bool
    {
        return ServiceAccess::can('service.repairs.approve_warranty');
    }

    public static function canRejectWarranty(): bool
    {
        return ServiceAccess::can('service.repairs.reject_warranty');
    }

    public static function canAuthorizeDelivery(): bool
    {
        return ServiceAccess::can('service.repairs.authorize_delivery');
    }

    public static function canReopen(): bool
    {
        return ServiceAccess::can('service.repairs.reopen');
    }

    public static function getEloquentQuery(): Builder
    {
        $model = static::getModel();

        $query = $model::query();

        $companyId = ServiceAccess::currentCompanyId();

        if ($companyId && ServiceAccess::tableHasCompany('repair_orders')) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Section::make('Cierre económico y utilidad')
                    ->description('Captura el costo interno por hora para calcular la ganancia real de mano de obra. La tarifa de venta al cliente sigue siendo la tarifa por hora configurada en la reparación.')
                    ->schema([
                        Forms\Components\TextInput::make('labor_internal_hour_cost')
                            ->label('Costo interno por hora')
                            ->numeric()
                            ->prefix('$')
                            ->step('0.01')
                            ->minValue(0)
                            ->disabled(fn ($record): bool => $record && (
                                (int) ($record->account_receivable_id ?? 0) > 0
                                || in_array((string) ($record->economic_status ?? ''), ['receivable_created', 'partially_charged', 'charged'], true)
                                || in_array((string) ($record->economic_payment_status ?? ''), ['partial', 'paid'], true)
                            ))
                            ->helperText('Costo real interno de la empresa por cada hora del técnico. Se usa solo para calcular utilidad; no cambia el precio cobrado al cliente.'),
                    ])
                    ->columns(1)
                    ->collapsible(),


                \Filament\Forms\Components\Placeholder::make('service_order_status_header')
                    ->label('')
                    ->content(function ($record): \Illuminate\Support\HtmlString {
                        $folio = e((string) ($record?->folio ?? 'Nueva orden'));
                        $stage = (string) ($record?->workflow_stage ?? $record?->status ?? 'quote_draft');

                        $labels = [
                            'quote_draft' => 'Borrador',
                            'pending_approval' => 'Pendiente de aprobación',
                            'quote_approved' => 'Aprobada / pendiente reparación',
                            'in_repair' => 'En reparación',
                            'repaired' => 'Reparado',
                            'supervisor_review' => 'Revisión supervisor',
                            'ready_for_delivery' => 'Listo para entrega',
                            'delivered' => 'Entregado',
                            'cancelled' => 'Cancelado',
                        ];

                        $styles = [
                            'quote_draft' => 'background:#fef3c7;border:1px solid #f59e0b;color:#78350f;',
                            'pending_approval' => 'background:#ffedd5;border:1px solid #fb923c;color:#7c2d12;',
                            'quote_approved' => 'background:#dbeafe;border:1px solid #60a5fa;color:#1e3a8a;',
                            'in_repair' => 'background:#e0e7ff;border:1px solid #818cf8;color:#312e81;',
                            'repaired' => 'background:#dcfce7;border:1px solid #22c55e;color:#14532d;',
                            'supervisor_review' => 'background:#fae8ff;border:1px solid #d946ef;color:#701a75;',
                            'ready_for_delivery' => 'background:#ccfbf1;border:1px solid #14b8a6;color:#134e4a;',
                            'delivered' => 'background:#f3f4f6;border:1px solid #9ca3af;color:#111827;',
                            'cancelled' => 'background:#fee2e2;border:1px solid #ef4444;color:#7f1d1d;',
                        ];

                        $label = e($labels[$stage] ?? $stage);
                        $style = $styles[$stage] ?? 'background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;';

                        return new \Illuminate\Support\HtmlString(
                            '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:14px;">'
                            . '<div style="background:#fef3c7;border:1px solid #f59e0b;color:#78350f;border-radius:14px;padding:12px 14px;">'
                            . '<div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Folio</div>'
                            . '<div style="font-size:18px;font-weight:800;margin-top:2px;">' . $folio . '</div>'
                            . '</div>'
                            . '<div style="' . $style . 'border-radius:14px;padding:12px 14px;">'
                            . '<div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Estado operativo</div>'
                            . '<div style="font-size:18px;font-weight:800;margin-top:2px;">' . $label . '</div>'
                            . '</div>'
                            . '</div>'
                        );
                    })
                    ->columnSpanFull(),


                \Filament\Forms\Components\Section::make('Entrega de reparación')
                    ->description('Información final de entrega al cliente.')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('ready_for_delivery_at_display')
                            ->label('Listo para entrega')
                            ->content(fn ($record): string => filled($record?->ready_for_delivery_at) ? (string) $record->ready_for_delivery_at : 'Pendiente'),

                        \Filament\Forms\Components\Placeholder::make('delivered_at_display')
                            ->label('Entregado el')
                            ->content(fn ($record): string => filled($record?->delivered_at) ? (string) $record->delivered_at : 'Pendiente'),

                        \Filament\Forms\Components\Placeholder::make('delivered_to_display')
                            ->label('Recibió')
                            ->content(fn ($record): string => filled($record?->delivered_to) ? (string) $record->delivered_to : 'Pendiente'),

                        \Filament\Forms\Components\Placeholder::make('delivery_notes_display')
                            ->label('Observaciones de entrega')
                            ->content(fn ($record): string => filled($record?->delivery_notes) ? (string) $record->delivery_notes : 'Sin observaciones')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->visible(fn ($record): bool => (bool) $record && in_array((string) ($record->workflow_stage ?? ''), ['ready_for_delivery', 'delivered'], true)),


                \Filament\Forms\Components\Section::make('Tiempo real de reparación')
                    ->description('Se calcula con horario hábil: lunes a viernes 09:00-17:00, sábado 09:00-14:00, domingo 0 horas.')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('repair_started_at_display')
                            ->label('Inicio real')
                            ->content(fn ($record): string => filled($record?->repair_started_at) ? (string) $record->repair_started_at : 'Sin iniciar'),

                        \Filament\Forms\Components\Placeholder::make('repair_finished_at_display')
                            ->label('Fin real')
                            ->content(fn ($record): string => filled($record?->repair_finished_at) ? (string) $record->repair_finished_at : 'Sin finalizar'),

                        \Filament\Forms\Components\Placeholder::make('actual_labor_hours_display')
                            ->label('Horas hábiles reales')
                            ->content(fn ($record): string => filled($record?->actual_labor_hours) ? number_format((float) $record->actual_labor_hours, 2) . ' h' : 'Pendiente'),

                        \Filament\Forms\Components\Placeholder::make('actual_labor_cost_display')
                            ->label('Costo real mano de obra')
                            ->content(fn ($record): string => filled($record?->actual_labor_cost) ? '$' . number_format((float) $record->actual_labor_cost, 2) : 'Pendiente'),
                    ])
                    ->columns(4)
                    ->collapsible()
                    ->visible(fn ($record): bool => (bool) $record),

                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => ServiceAccess::currentCompanyId()),

                Forms\Components\Section::make('Datos generales')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('folio')
                        ->extraAttributes([
                            'style' => 'background:#fef3c7;border:1px solid #f59e0b;border-radius:12px;padding:8px;',
                        ])
                            ->label('Folio')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Select::make('service_case_id')
                        ->disabled(fn ($record): bool => \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                        ->dehydrated(fn ($record): bool => ! \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                            ->label('Ticket origen')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => ServiceAccess::serviceCaseOptions())
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::serviceCaseOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::serviceCaseLabel((int) $value))
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $details = ServiceAccess::serviceCaseDetails((int) $state);

                                foreach ([
                                    'customer_id',
                                    'assigned_employee_id',
                                    'product_id',
                                    'product_name',
                                    'serial_number',
                                    'lot_number',
                                    'sale_id',
                                    'invoice_id',
                                    'initial_diagnosis',
                                ] as $field) {
                                    if (array_key_exists($field, $details)) {
                                        $set($field, $details[$field]);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('workflow_stage')

                                                    ->label('Etapa operativa')

                                                    ->options([

                                                        'quote_draft' => 'Crear / Guardar cotizacion',

                                                        'pending_approval' => 'Enviada a aprobacion',

                                                        'quote_approved' => 'Aprobada / pendiente reparacion',

                                                        'in_repair' => 'En reparacion',

                                                        'repaired' => 'Reparado',

                                                        'supervisor_review' => 'Pendiente revision supervisor',

                                                        'ready_for_delivery' => 'Listo para entrega',

                                                        'delivered' => 'Entregado',

                                                        'cancelled' => 'Cancelado',

                                                    ])

                                                    ->default('quote_draft')

                                                    ->native(false)

                                                    ->live()

                                                    ->helperText('Esta es la etapa real del flujo. El estado tecnico se sincroniza automaticamente.')

                                                    ->columnSpan(1),
Forms\Components\Hidden::make('status')
                            ->default('received')
                            ->dehydrated(),


                        Forms\Components\Select::make('warranty_status')
                            ->label('Garantia')
                            ->options(RepairOrder::WARRANTY_STATUSES)
                            ->required()
                            ->default('pendiente'),

                        Forms\Components\Select::make('customer_id')
                        ->disabled(fn ($record): bool => \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                        ->dehydrated(fn ($record): bool => ! \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                            ->label('Cliente')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(fn (): array => ServiceAccess::contactOptions())
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::contactOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::contactLabel((int) $value))
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $details = ServiceAccess::contactDetails((int) $state);

                                // La orden de reparación aún no tiene campos propios para contacto,
                                // por ahora se mantiene disponible para futuras fases.
                            }),

                        Forms\Components\Select::make('assigned_employee_id')
                            ->label('Tecnico asignado')
                            ->helperText('Solo empleados del mismo grupo de empresas marcados como tecnico de servicio.')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => ServiceAccess::technicianEmployeeOptions())
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::technicianEmployeeOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::employeeLabel((int) $value)),

                        Forms\Components\DateTimePicker::make('received_at')
                        ->disabled(fn ($record): bool => \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                        ->dehydrated(fn ($record): bool => ! \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                            ->label('Fecha recepcion'),

                        Forms\Components\DateTimePicker::make('promised_at')
                            ->label('Fecha prometida'),

                        Forms\Components\DateTimePicker::make('warranty_expires_at')
                            ->label('Vence garantia'),
                    ]),

                Forms\Components\Section::make('Producto / documento relacionado')
                    ->description('Opcional. Usa catálogo si existe; si no, captura libremente producto, serie, lote, venta o factura.')
                    ->columns(12)
                    ->schema([
                        Forms\Components\Select::make('product_id')
                        ->disabled(fn ($record): bool => \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                        ->dehydrated(fn ($record): bool => ! \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                            ->label('Producto catálogo')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(fn (): array => ServiceAccess::productOptions())
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::productOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::productLabel((int) $value))
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $set('product_name', ServiceAccess::productLabel((int) $state));
                            })
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('product_name')
                        ->disabled(fn ($record): bool => \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                        ->dehydrated(fn ($record): bool => ! \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                            ->label('Producto / modelo libre')
                            ->helperText('Captura manual cuando el producto aún no exista en catálogo.')
                            ->maxLength(255)
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('serial_number')
                        ->disabled(fn ($record): bool => \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                        ->dehydrated(fn ($record): bool => ! \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                            ->label('Número de serie libre')
                            ->helperText('Captura libre mientras se cargan las series reales.')
                            ->maxLength(255)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('lot_number')
                        ->disabled(fn ($record): bool => \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                        ->dehydrated(fn ($record): bool => ! \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                            ->label('Lote libre')
                            ->helperText('Captura libre mientras se cargan lotes reales.')
                            ->maxLength(255)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('sale_reference')
                            ->label('Venta / documento libre')
                            ->helperText('Folio, pedido, nota o referencia manual.')
                            ->maxLength(255)
                            ->columnSpan(4),

                        Forms\Components\Select::make('sale_id')
                        ->disabled(fn ($record): bool => \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                        ->dehydrated(fn ($record): bool => ! \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                            ->label('Venta relacionada')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => ServiceAccess::saleOrderOptions())
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::saleOrderOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::saleOrderLabel((int) $value))
                            ->columnSpan(6),

                        Forms\Components\Select::make('invoice_id')
                        ->disabled(fn ($record): bool => \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                        ->dehydrated(fn ($record): bool => ! \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                            ->label('Factura relacionada')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => ServiceAccess::invoiceOptions())
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::invoiceOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::invoiceLabel((int) $value))
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('invoice_reference')
                        ->disabled(fn ($record): bool => \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                        ->dehydrated(fn ($record): bool => ! \App\Support\Service\ServiceAccess::repairOrderCoreFieldsLocked($record))
                            ->label('Factura / folio libre')
                            ->helperText('UUID, folio fiscal, serie-folio o referencia manual.')
                            ->maxLength(255)
                            ->columnSpan(3),
                    ]),

                
                Forms\Components\Section::make('Diagnostico, presupuesto y resolucion')
                    ->description('Captura diagnostico, refacciones/materiales, presupuesto y resolucion en un solo bloque.')
                    ->columns(12)
                    ->schema([
                        Forms\Components\Textarea::make('received_condition')
                            ->label('Condicion de recepcion')
                            ->columnSpan(4)
                            ->rows(3),

                        Forms\Components\Textarea::make('initial_diagnosis')
                            ->label('Diagnostico inicial')
                            ->columnSpan(4)
                            ->rows(4),

                        Forms\Components\Textarea::make('technical_diagnosis')
                            ->label('Diagnostico tecnico')
                            ->columnSpan(4)
                            ->rows(4),

                        Forms\Components\Repeater::make('parts')
                            ->label('Refacciones / materiales')
                            ->relationship('parts')
                            ->columns(12)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar refaccion o material')
                            ->collapsible()
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                self::recalculateBudgetFields($get, $set);
                            })
                            ->schema([
                                Forms\Components\Select::make('source_type')
                                    ->label('Origen')
                                    ->options([
                                        'catalog' => 'Catalogo / almacen',
                                        'manual' => 'Manual',
                                    ])
                                    ->default('manual')
                                    ->native(false)
                                    ->columnSpan(2),

                                Forms\Components\Select::make('product_id')
                                    ->label('Producto catalogo')
                                    ->searchable()
                                    ->preload()
                                    ->options(fn (): array => ServiceAccess::productOptions())
                                    ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::productOptions($search))
                                    ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::productLabel((int) $value))
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                        $details = ServiceAccess::productPricingDetails((int) $state);

                                        if (($details['product_name'] ?? null) !== null) {
                                            $set('product_name', $details['product_name']);
                                        }

                                        if (($details['unit_cost'] ?? null) !== null) {
                                            $set('unit_cost', $details['unit_cost']);
                                        }

                                        if (($details['unit_price'] ?? null) !== null) {
                                            $set('unit_price', $details['unit_price']);
                                        }

                                        $qty = (float) ($get('quantity') ?: 0);
                                        $unitCost = (float) ($get('unit_cost') ?: 0);
                                        $unitPrice = (float) ($get('unit_price') ?: 0);

                                        $set('total_cost', round($qty * $unitCost, 2));
                                        $set('total_price', round($qty * $unitPrice, 2));

                                        self::recalculateBudgetFields($get, $set, '../../');
                                    })
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('product_name')
                                    ->label('Descripcion / producto manual')
                                    ->maxLength(255)
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cant.')
                                    ->numeric()
                                    ->default(1)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                        $qty = (float) ($state ?: 0);
                                        $unitCost = (float) ($get('unit_cost') ?: 0);
                                        $unitPrice = (float) ($get('unit_price') ?: 0);

                                        $set('total_cost', round($qty * $unitCost, 2));
                                        $set('total_price', round($qty * $unitPrice, 2));

                                        self::recalculateBudgetFields($get, $set, '../../');
                                    })
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('Costo')
                                    ->numeric()
                                    ->prefix('$')
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                        $qty = (float) ($get('quantity') ?: 0);
                                        $set('total_cost', round($qty * (float) ($state ?: 0), 2));

                                        self::recalculateBudgetFields($get, $set, '../../');
                                    })
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Precio venta')
                                    ->numeric()
                                    ->prefix('$')
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                        $qty = (float) ($get('quantity') ?: 0);
                                        $set('total_price', round($qty * (float) ($state ?: 0), 2));

                                        self::recalculateBudgetFields($get, $set, '../../');
                                    })
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('total_price')
                                    ->label('Total venta')
                                    ->numeric()
                                    ->prefix('$')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('total_cost')
                                    ->label('Total costo')
                                    ->numeric()
                                    ->prefix('$')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->columnSpan(2),
                            ])
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('parts_required')
                            ->label('Observaciones de refacciones / materiales')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Describe piezas, consumibles o materiales necesarios para la reparacion.'),



                        Forms\Components\TextInput::make('parts_cost_estimate')
                            ->label('Costo estimado refacciones')
                            ->numeric()
                            ->prefix('$')
                            ->readOnly()
                            ->dehydrated()
                            ->helperText('Suma automatica de Total costo de refacciones/materiales.')
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('labor_hours_estimate')

                                                    ->label('Horas estimadas')

                                                    ->numeric()

                                                    ->suffix('hrs')

                                                    ->default(0)

                                                    ->live(debounce: 500)

                                                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {

                                                        self::recalculateBudgetFields($get, $set);

                                                    })

                                                    ->columnSpan(2),


                        Forms\Components\TextInput::make('labor_hour_rate')


                                                    ->label('Costo hora tecnico')


                                                    ->numeric()


                                                    ->prefix('$')


                                                    ->helperText('Se toma del tecnico si existe; si no, capturalo manualmente.')


                                                    ->live(debounce: 500)


                                                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {


                                                        $set('labor_rate_source', 'manual');


                                                        self::recalculateBudgetFields($get, $set);


                                                    })


                                                    ->columnSpan(2),


                        Forms\Components\Hidden::make('labor_rate_source'),

                        Forms\Components\TextInput::make('labor_cost_estimate')
                            ->label('Mano de obra estimada')
                            ->numeric()
                            ->prefix('$')
                            ->readOnly()
                            ->dehydrated()
                            ->helperText('Horas estimadas x costo hora tecnico.')
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('other_cost_estimate')

                                                    ->label('Otros costos')

                                                    ->numeric()

                                                    ->prefix('$')

                                                    ->default(0)

                                                    ->live(debounce: 500)

                                                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {

                                                        self::recalculateBudgetFields($get, $set);

                                                    })

                                                    ->columnSpan(3),


                        Forms\Components\TextInput::make('quote_total')
                            ->label('Total presupuesto')
                            ->numeric()
                            ->prefix('$')
                            ->readOnly()
                            ->dehydrated()
                            ->helperText('Total venta de refacciones/materiales + mano de obra + otros costos.')
                            ->columnSpan(4),

                        Forms\Components\Select::make('quote_status')
                            ->label('Estatus presupuesto')
                            ->options([
                                'not_required' => 'No requerido / sin presupuesto',
                                'draft' => 'Borrador / cotización',
                                'pending_internal' => 'Pendiente aprobacion interna',
                                'pending_customer' => 'Pendiente aprobacion cliente',
                                'customer_approved' => 'Aprobado por cliente',
                                'customer_rejected' => 'Rechazado por cliente',
                                'cancelled' => 'Cancelado',
                            ])
                            ->default('draft')
                            ->native(false)
                            ->columnSpan(4),

                        Forms\Components\Toggle::make('requires_customer_approval')
                            ->label('Requiere Vo.Bo. cliente')
                            ->helperText('Usar cuando el presupuesto debe ser aceptado por el cliente.')
                            ->columnSpan(4),

                        Forms\Components\Textarea::make('quote_notes')
                            ->label('Notas de presupuesto / autorizacion')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('resolution_locked_notice')
                            ->label('Resolución final')
                            ->content('La resolución final se captura después de aprobar la cotización e iniciar la reparación.')
                            ->visible(fn (Get $get): bool => ! in_array((string) ($get('workflow_stage') ?: 'quote_draft'), ['in_repair', 'repaired', 'supervisor_review', 'ready_for_delivery', 'delivered', 'finished'], true))
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('resolution')
                            ->label('Resolución final')
                            ->helperText('Describe la reparación real realizada, pruebas y resultado final.')
                            ->visible(fn (Get $get): bool => in_array((string) ($get('workflow_stage') ?: 'quote_draft'), ['in_repair', 'repaired', 'supervisor_review', 'ready_for_delivery', 'delivered', 'finished'], true))
                            ->label('Resolucion')
                            ->columnSpanFull()
                            ->rows(4),

                        Forms\Components\FileUpload::make('uploaded_attachments')
                            ->label('Fotos y archivos')
                            ->helperText('Agrega fotos de recepcion, diagnostico, pruebas, entrega o documentos relacionados.')
                            ->multiple()
                            ->disk('public')
                            ->directory('service-attachments/repairs')
                            ->downloadable()
                            ->openable()
                            ->previewable()
                            ->maxSize(20480)
                            ->acceptedFileTypes([
                                'image/*',
                                'application/pdf',
                                'text/plain',
                                'application/zip',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Costos finales / reales')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('estimated_cost')
                            ->label('Costo estimado')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('actual_cost')
                            ->label('Costo real')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->columnSpan(1),
                    ]),

                
            ]);
    }

    protected static function recalculateBudgetFields(Get $get, Set $set, string $prefix = ''): void
    {
        $parts = $get($prefix . 'parts') ?? [];

        $partsCostTotal = 0.0;
        $partsSaleTotal = 0.0;

        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (! is_array($part)) {
                    continue;
                }

                $quantity = (float) ($part['quantity'] ?? 0);
                $unitCost = (float) ($part['unit_cost'] ?? 0);
                $unitPrice = (float) ($part['unit_price'] ?? 0);

                $lineCost = (float) ($part['total_cost'] ?? 0);
                $lineSale = (float) ($part['total_price'] ?? 0);

                if ($lineCost <= 0 && $quantity > 0 && $unitCost > 0) {
                    $lineCost = $quantity * $unitCost;
                }

                if ($lineSale <= 0 && $quantity > 0 && $unitPrice > 0) {
                    $lineSale = $quantity * $unitPrice;
                }

                $partsCostTotal += $lineCost;
                $partsSaleTotal += $lineSale;
            }
        }

        $laborHours = (float) ($get($prefix . 'labor_hours_estimate') ?: 0);
        $laborRate = (float) ($get($prefix . 'labor_hour_rate') ?: 0);
        $labor = round($laborHours * $laborRate, 2);

        $other = (float) ($get($prefix . 'other_cost_estimate') ?: 0);

        $set($prefix . 'parts_cost_estimate', round($partsCostTotal, 2));
        $set($prefix . 'labor_cost_estimate', $labor);
        $set($prefix . 'quote_total', round($partsSaleTotal + $labor + $other, 2));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([

                Tables\Columns\TextColumn::make('folio')
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_name')
                    ->label('Producto')
                    ->searchable()
                    ->limit(35),

                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serie')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quote_status')
                    ->label('Presupuesto')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'not_required' => 'No requerido / sin presupuesto',
                        'draft' => 'Borrador / cotización',
                        'pending_internal' => 'Pendiente interno',
                        'pending_customer' => 'Pendiente cliente',
                        'customer_approved' => 'Aprobado cliente',
                        'customer_rejected' => 'Rechazado cliente',
                        'cancelled' => 'Cancelado',
                        default => $state ? ucfirst(str_replace('_', ' ', $state)) : '-',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quote_total')
                    ->label('Total')
                    ->money('MXN')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('workflow_stage')

                                    ->label('Etapa')

                                    ->badge()

                                    ->formatStateUsing(fn (?string $state): string => match ($state) {

                                        'quote_draft' => 'Cotizacion',

                                        'pending_approval' => 'En aprobacion',

                                        'quote_approved' => 'Pendiente reparacion',

                                        'in_repair' => 'En reparacion',

                                        'repaired' => 'Reparado',

                                        'supervisor_review' => 'Revision supervisor',

                                        'ready_for_delivery' => 'Listo entrega',

                                        'delivered' => 'Entregado',

                                        'cancelled' => 'Cancelado',

                                        'finished' => 'Reparado',

                                        default => $state ? ucfirst(str_replace('_', ' ', $state)) : 'Cotizacion',

                                    })

                                    ->sortable()

                                    ->toggleable(),

                Tables\Columns\TextColumn::make('warranty_status')
                    ->label('Garantia')

                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobada',
                        'rejected' => 'Rechazada',
                        'no_aplica' => 'No aplica',
                        'not_applicable' => 'No aplica',
                        default => $state ? ucfirst(str_replace('_', ' ', $state)) : 'No aplica',
                    })
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('assigned_employee_id')
                    ->label('Tecnico')
                    ->formatStateUsing(fn ($state): ?string => filled($state) ? ServiceAccess::employeeLabel((int) $state) : null)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('company_id')
                    ->label('Empresa')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('estimated_cost')
                    ->label('Estimado')
                    ->money('MXN')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('actual_cost')
                    ->label('Real')
                    ->money('MXN')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('promised_at')
                    ->label('Prometida')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(RepairOrder::STATUSES),

                Tables\Filters\SelectFilter::make('warranty_status')
                    ->label('Garantia')
                    ->options(RepairOrder::WARRANTY_STATUSES),
            ])
            ->actions([



                Tables\Actions\Action::make('reabrir')
                    ->label('Reabrir')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (RepairOrder $record): bool => in_array((string) $record->status, ['entregado', 'cerrado', 'rechazado', 'cancelado'], true) && static::canReopen())
                    ->action(function (RepairOrder $record): void {
                        $oldStatus = $record->status;

                        $record->update([
                            'status' => 'en_diagnostico',
                            'closed_at' => null,
                            'delivered_at' => null,
                        ]);

                        static::logEvent(
                            $record,
                            'reparacion_reabierta',
                            $oldStatus,
                            $record->status,
                            'Reparacion reabierta desde Filament.'
                        );

                        Notification::make()
                            ->title('Reparacion reabierta')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => static::canDeleteAny()),
                ]),
            ]);
    }

    public static function logEvent(RepairOrder $record, string $eventType, ?string $fromStatus = null, ?string $toStatus = null, ?string $notes = null): void
    {
        ServiceCaseEvent::create([
            'company_id' => $record->company_id,
            'service_case_id' => $record->service_case_id,
            'repair_order_id' => $record->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'performed_by' => auth()->id(),
            'performed_at' => now(),
            'notes' => $notes,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRepairOrders::route('/'),
            'create' => Pages\CreateRepairOrder::route('/create'),
            'edit' => Pages\EditRepairOrder::route('/{record}/edit'),
        ];
    }
}
