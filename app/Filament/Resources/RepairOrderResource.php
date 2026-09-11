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
    // BEXIA_ATC_CXC_BOUNDARY_TEXTS_V5_83_4C5E4
    // Los textos tecnicos no deben afirmar que sigue pendiente
    // revision/costeo cuando el flujo ya avanzo a cobro/entrega.

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
        return ServiceAccess::canViewRepairOrder($record);
    }

    public static function canCreate(): bool
    {
        return ServiceAccess::can('service.repairs.create');
    }

    public static function canEdit(Model $record): bool
    {
        $workflowStage = (string) (
            $record->workflow_stage ?? ''
        );

        $status = (string) (
            $record->status ?? ''
        );

        $isFinalState =
            in_array(
                $workflowStage,
                [
                    'delivered',
                    'cancelled',
                ],
                true
            )
            || in_array(
                $status,
                [
                    'delivered',
                    'entregado',
                    'cerrado',
                    'rechazado',
                    'cancelled',
                    'cancelado',
                ],
                true
            );

        if ($isFinalState) {
            return static::canReopen();
        }

        return ServiceAccess::canEditRepairOrder(
            $record
        );
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

        ServiceAccess::scopeRepairOrdersForCurrentUser($query);

        return $query;
    }

    public static function form(Form $form): Form
    {
        /*
         * BEXIA_ATC_TECHNICIAN_REPAIR_VIEW_V5_83_4C5B
         *
         * El técnico asignado NO usa el formulario administrativo
         * de la reparación.
         *
         * Para él:
         * - todos los datos maestros son solo lectura;
         * - no existen campos económicos;
         * - no existe cotización;
         * - no existe garantía editable;
         * - no existe etapa editable;
         * - no existe precio/costo de refacciones;
         * - el trabajo se captura únicamente desde la acción
         *   "Finalizar trabajo técnico".
         *
         * Encargado de Técnicos / Supervisor conservan el
         * formulario administrativo completo para la siguiente
         * fase de costeo y cobro.
         */
        if (ServiceAccess::isRestrictedServiceTechnician()) {
            return $form
                ->schema(
                    static::technicianFormSchema()
                );
        }

        /*
         * BEXIA_ATC_MANAGER_REVIEW_VIEW_V5_83_4C5C2A1
         *
         * Vista exclusiva del Encargado de Tecnicos
         * durante la etapa posterior al trabajo tecnico.
         *
         * Supervisor conserva temporalmente el form legacy.
         */
        if (
            ServiceAccess::hasServiceRole(
                'Servicio - Encargado de Técnicos'
            )
            && ! ServiceAccess::hasServiceRole(
                'Servicio - Supervisor'
            )
        ) {
            return $form
                ->schema(
                    static::managerReviewCostingFormSchema()
                );
        }



        /*
         * BEXIA_ATC_RECEPTION_REPAIR_READONLY_VIEW_V5_83_4C5E5
         *
         * Servicio - Recepcion consulta la reparación,
         * pero no modifica:
         * - diagnostico;
         * - trabajo tecnico;
         * - presupuesto;
         * - costos;
         * - garantia;
         * - etapa;
         * - informacion economica.
         *
         * Encargado y Supervisor conservan sus vistas propias.
         */
        if (
            ServiceAccess::hasServiceRole(
                'Servicio - Recepción'
            )
            && ! ServiceAccess::hasServiceRole([
                'Servicio - Encargado de Técnicos',
                'Servicio - Supervisor',
            ])
        ) {
            return $form
                ->schema(
                    static::
                        receptionRepairReadOnlyFormSchema()
                );
        }


        return $form
            ->schema([

                Forms\Components\Section::make('Cierre económico y utilidad')
                    ->extraAttributes(['class' => 'bexia-repair-order-economic-section'])
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
                    ->extraAttributes(['class' => 'bexia-repair-order-status-header-field'])
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

                        $stageClasses = [
                            'quote_draft' => 'bexia-repair-order-status-card--quote-draft',
                            'pending_approval' => 'bexia-repair-order-status-card--pending-approval',
                            'quote_approved' => 'bexia-repair-order-status-card--quote-approved',
                            'in_repair' => 'bexia-repair-order-status-card--in-repair',
                            'repaired' => 'bexia-repair-order-status-card--repaired',
                            'supervisor_review' => 'bexia-repair-order-status-card--supervisor-review',
                            'ready_for_delivery' => 'bexia-repair-order-status-card--ready-for-delivery',
                            'delivered' => 'bexia-repair-order-status-card--delivered',
                            'cancelled' => 'bexia-repair-order-status-card--cancelled',
                        ];

                        $label = e($labels[$stage] ?? $stage);
                        $stageClass = $stageClasses[$stage] ?? 'bexia-repair-order-status-card--default';

                        return new \Illuminate\Support\HtmlString(
                            '<div class="bexia-repair-order-status-header">'
                            . '<div class="bexia-repair-order-status-card bexia-repair-order-status-card--folio">'
                            . '<div class="bexia-repair-order-status-label">Folio</div>'
                            . '<div class="bexia-repair-order-status-value">' . $folio . '</div>'
                            . '</div>'
                            . '<div class="bexia-repair-order-status-card ' . $stageClass . '">'
                            . '<div class="bexia-repair-order-status-label">Estado operativo</div>'
                            . '<div class="bexia-repair-order-status-value">' . $label . '</div>'
                            . '</div>'
                            . '</div>'
                        );
                    })
                    ->columnSpanFull(),


                \Filament\Forms\Components\Section::make('Entrega de reparación')
                    ->extraAttributes(['class' => 'bexia-repair-order-delivery-section'])
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
                    ->extraAttributes(['class' => 'bexia-repair-order-time-section'])
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
                    ->extraAttributes(['class' => 'bexia-repair-order-general-section'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('folio')
                            ->extraAttributes(['class' => 'bexia-repair-order-folio-input'])
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

                        Forms\Components\Placeholder::make('sla_status_display')
                            ->label('Semáforo SLA')
                            ->content(fn ($record): string => $record
                                ? \App\Support\Service\ServiceRepairSla::summary($record)
                                : 'Se calculará al guardar')
                            ->visible(fn ($record): bool => (bool) $record),

                        Forms\Components\DateTimePicker::make('warranty_expires_at')
                            ->label('Vence garantia'),
                    ]),

                Forms\Components\Section::make('Producto / documento relacionado')
                    ->extraAttributes(['class' => 'bexia-repair-order-product-section'])
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
                    ->extraAttributes(['class' => 'bexia-repair-order-diagnosis-section'])
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
                            ->extraAttributes(['class' => 'bexia-repair-order-parts-repeater'])
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
                            ->extraAttributes(['class' => 'bexia-repair-order-resolution-locked-notice'])
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
                            ->extraAttributes(['class' => 'bexia-repair-order-attachments-upload'])
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
                    ->extraAttributes(['class' => 'bexia-repair-order-costs-section'])
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


    /*
     * BEXIA_ATC_MANAGER_REVIEW_SCHEMA_V5_83_4C5C2A1
     *
     * C5C2A1:
     * vista visual / solo lectura.
     *
     * C5C2B agregara la accion real de costeo.
     */
    protected static function managerReviewCostingFormSchema(): array
    {
        /*
         * BEXIA_ATC_MANAGER_REVIEW_SCHEMA_V5_83_4C5C2B
         *
         * Esta vista sigue siendo solo lectura.
         * La captura se realiza exclusivamente desde
         * la accion "Revisar y costear".
         */
        $schema =
            static::technicianFormSchema();

        $schema[] =
            Forms\Components\Section::make(
                'Revisión y costeo'
            )
                ->description(
                    'El trabajo técnico ya terminó. '
                    . 'El Encargado revisa el resultado '
                    . 'y registra la decisión económica.'
                )
                ->columns(2)
                ->schema([
                    Forms\Components\Placeholder::make(
                        'manager_review_status'
                    )
                        ->label('Estado')
                        ->content(
                            function (
                                $record
                            ): string {
                                if (! $record) {
                                    return 'Sin orden';
                                }

                                $metadata =
                                    $record->metadata
                                    ?? [];

                                if (
                                    is_string(
                                        $metadata
                                    )
                                ) {
                                    $metadata =
                                        json_decode(
                                            $metadata,
                                            true
                                        )
                                        ?: [];
                                }

                                if (
                                    ! is_array(
                                        $metadata
                                    )
                                ) {
                                    $metadata = [];
                                }

                                $manager =
                                    $metadata[
                                        'manager_review'
                                    ]
                                    ?? [];

                                if (
                                    is_array(
                                        $manager
                                    )
                                    && (
                                        $manager[
                                            'status'
                                        ]
                                        ?? null
                                    ) === 'completed'
                                ) {
                                    return
                                        'Revisión y costeo registrados';
                                }

                                return
                                    'Pendiente de revisión y costeo';
                            }
                        ),

                    Forms\Components\Placeholder::make(
                        'manager_review_completed_at'
                    )
                        ->label(
                            'Trabajo técnico finalizado'
                        )
                        ->content(
                            function (
                                $record
                            ): string {
                                if (! $record) {
                                    return '—';
                                }

                                $metadata =
                                    $record->metadata
                                    ?? [];

                                if (
                                    is_string(
                                        $metadata
                                    )
                                ) {
                                    $metadata =
                                        json_decode(
                                            $metadata,
                                            true
                                        )
                                        ?: [];
                                }

                                if (
                                    ! is_array(
                                        $metadata
                                    )
                                ) {
                                    $metadata = [];
                                }

                                return (string) (
                                    $metadata[
                                        'technical_work'
                                    ][
                                        'completed_at'
                                    ]
                                    ?? '—'
                                );
                            }
                        ),

                    Forms\Components\Placeholder::make(
                        'manager_review_decision'
                    )
                        ->label(
                            'Decisión'
                        )
                        ->content(
                            function (
                                $record
                            ): string {
                                if (! $record) {
                                    return 'Pendiente';
                                }

                                $metadata =
                                    $record->metadata
                                    ?? [];

                                if (
                                    is_string(
                                        $metadata
                                    )
                                ) {
                                    $metadata =
                                        json_decode(
                                            $metadata,
                                            true
                                        )
                                        ?: [];
                                }

                                if (
                                    ! is_array(
                                        $metadata
                                    )
                                ) {
                                    $metadata = [];
                                }

                                $decision =
                                    $metadata[
                                        'manager_review'
                                    ][
                                        'decision'
                                    ]
                                    ?? null;

                                return match (
                                    $decision
                                ) {
                                    'cobrable' =>
                                        'Servicio cobrable',

                                    'garantia' =>
                                        'Garantía aceptada / sin cargo',

                                    'garantia_rechazada' =>
                                        'Garantía rechazada / cobrable',

                                    'cortesia' =>
                                        'Cortesía / sin cargo',

                                    default =>
                                        'Pendiente',
                                };
                            }
                        ),

                    Forms\Components\Placeholder::make(
                        'manager_review_total'
                    )
                        ->label(
                            'Total al cliente'
                        )
                        ->content(
                            function (
                                $record
                            ): string {
                                if (! $record) {
                                    return '$0.00';
                                }

                                $metadata =
                                    $record->metadata
                                    ?? [];

                                if (
                                    is_string(
                                        $metadata
                                    )
                                ) {
                                    $metadata =
                                        json_decode(
                                            $metadata,
                                            true
                                        )
                                        ?: [];
                                }

                                if (
                                    ! is_array(
                                        $metadata
                                    )
                                ) {
                                    $metadata = [];
                                }

                                $total =
                                    (float) (
                                        $metadata[
                                            'manager_review'
                                        ][
                                            'quote_total'
                                        ]
                                        ?? 0
                                    );

                                return
                                    '$'
                                    . number_format(
                                        $total,
                                        2
                                    );
                            }
                        ),

                    Forms\Components\Placeholder::make(
                        'manager_review_next_step'
                    )
                        ->label(
                            'Siguiente paso'
                        )
                        ->content(
                            function (
                                $record
                            ): string {
                                if (! $record) {
                                    return 'Pendiente';
                                }

                                $metadata =
                                    $record->metadata
                                    ?? [];

                                if (
                                    is_string(
                                        $metadata
                                    )
                                ) {
                                    $metadata =
                                        json_decode(
                                            $metadata,
                                            true
                                        )
                                        ?: [];
                                }

                                if (
                                    ! is_array(
                                        $metadata
                                    )
                                ) {
                                    $metadata = [];
                                }

                                $review =
                                    $metadata[
                                        'manager_review'
                                    ]
                                    ?? [];

                                if (
                                    ! is_array(
                                        $review
                                    )
                                    || (
                                        $review[
                                            'status'
                                        ]
                                        ?? null
                                    ) !== 'completed'
                                ) {
                                    return
                                        'Usa el botón '
                                        . '"Revisar y costear".';
                                }

                                if (
                                    (bool) (
                                        $review[
                                            'requires_customer_approval'
                                        ]
                                        ?? false
                                    )
                                ) {
                                    return
                                        'Pendiente de Vo.Bo. '
                                        . 'del cliente.';
                                }

                                if (
                                    (float) (
                                        $review[
                                            'quote_total'
                                        ]
                                        ?? 0
                                    ) > 0
                                ) {
                                    return
                                        'Costeo listo. '
                                        . 'Siguiente: cobro '
                                        . 'y preparación de entrega.';
                                }

                                return
                                    'Sin cargo al cliente. '
                                    . 'Siguiente: preparar entrega.';
                            }
                        )
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make(
                        'manager_review_mode'
                    )
                        ->label('Captura')
                        ->content(
                            'La información técnica permanece '
                            . 'bloqueada. Costos, precios y '
                            . 'decisión se registran solamente '
                            . 'desde la acción '
                            . '"Revisar y costear".'
                        )
                        ->columnSpanFull(),
                ]);

        return $schema;
    }



    /*
     * BEXIA_ATC_RECEPTION_REPAIR_READONLY_SCHEMA_V5_83_4C5E5
     *
     * Vista informativa de Recepcion.
     * No contiene TextInput, Textarea, Select, Repeater
     * ni ningun componente editable.
     */
    protected static function receptionRepairReadOnlyFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Section::make(
                'Orden técnica'
            )
                ->description(
                    'Información de referencia de la reparación. '
                    . 'Recepción puede consultarla, pero no modificarla.'
                )
                ->schema([
                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_folio'
                        )
                        ->label('Folio')
                        ->content(
                            fn ($record): string =>
                                (string) (
                                    $record?->folio
                                    ?? '—'
                                )
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_ticket'
                        )
                        ->label('Ticket origen')
                        ->content(
                            function ($record): string {
                                if (
                                    ! $record
                                    || empty(
                                        $record->
                                            service_case_id
                                    )
                                ) {
                                    return '—';
                                }

                                $case =
                                    \Illuminate\Support\Facades\DB::
                                        table(
                                            'service_cases'
                                        )
                                        ->where(
                                            'id',
                                            (int) $record->
                                                service_case_id
                                        )
                                        ->first();

                                if (! $case) {
                                    return '—';
                                }

                                return
                                    (string) (
                                        $case->folio
                                        ?? (
                                            '#'
                                            . $case->id
                                        )
                                    )
                                    . (
                                        filled(
                                            $case->status
                                            ?? null
                                        )
                                            ? ' · '
                                                . (string)
                                                    $case->
                                                        status
                                            : ''
                                    );
                            }
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_product'
                        )
                        ->label(
                            'Producto / modelo'
                        )
                        ->content(
                            fn ($record): string =>
                                filled(
                                    $record?->product_name
                                )
                                    ? (string)
                                        $record->
                                            product_name
                                    : '—'
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_serial'
                        )
                        ->label('Número de serie')
                        ->content(
                            fn ($record): string =>
                                filled(
                                    $record?->serial_number
                                )
                                    ? (string)
                                        $record->
                                            serial_number
                                    : '—'
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_received'
                        )
                        ->label(
                            'Fecha de recepción'
                        )
                        ->content(
                            fn ($record): string =>
                                filled(
                                    $record?->received_at
                                )
                                    ? (string)
                                        $record->
                                            received_at
                                    : '—'
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_condition'
                        )
                        ->label(
                            'Condición de recepción'
                        )
                        ->content(
                            fn ($record): string =>
                                filled(
                                    $record->
                                        received_condition
                                    ?? null
                                )
                                    ? (string)
                                        $record->
                                            received_condition
                                    : 'Sin observaciones'
                        )
                        ->columnSpanFull(),
                ])
                ->columns(3),

            \Filament\Forms\Components\Section::make(
                'Diagnóstico y trabajo técnico'
            )
                ->description(
                    'Información capturada por el técnico. '
                    . 'Esta sección es únicamente informativa.'
                )
                ->schema([
                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_technical_status'
                        )
                        ->label('Estado')
                        ->content(
                            function ($record): string {
                                $metadata =
                                    static::
                                        receptionRepairMetadata(
                                            $record?->metadata
                                            ?? null
                                        );

                                return
                                    data_get(
                                        $metadata,
                                        'technical_work.status'
                                    ) === 'completed'
                                        ? 'Trabajo técnico finalizado'
                                        : 'Pendiente';
                            }
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_technical_completed'
                        )
                        ->label('Finalizado')
                        ->content(
                            function ($record): string {
                                $metadata =
                                    static::
                                        receptionRepairMetadata(
                                            $record?->metadata
                                            ?? null
                                        );

                                return
                                    (string) (
                                        data_get(
                                            $metadata,
                                            'technical_work.completed_at'
                                        )
                                        ?: 'Pendiente'
                                    );
                            }
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_diagnosis'
                        )
                        ->label(
                            'Diagnóstico técnico'
                        )
                        ->content(
                            fn ($record): string =>
                                filled(
                                    $record->
                                        technical_diagnosis
                                    ?? null
                                )
                                    ? (string)
                                        $record->
                                            technical_diagnosis
                                    : 'Pendiente'
                        )
                        ->columnSpanFull(),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_work'
                        )
                        ->label(
                            'Trabajo realizado'
                        )
                        ->content(
                            fn ($record): string =>
                                filled(
                                    $record->resolution
                                    ?? null
                                )
                                    ? (string)
                                        $record->
                                            resolution
                                    : 'Pendiente'
                        )
                        ->columnSpanFull(),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_tests'
                        )
                        ->label(
                            'Pruebas / observaciones finales'
                        )
                        ->content(
                            function ($record): string {
                                $metadata =
                                    static::
                                        receptionRepairMetadata(
                                            $record?->metadata
                                            ?? null
                                        );

                                return
                                    (string) (
                                        data_get(
                                            $metadata,
                                            'technical_work.tests_notes'
                                        )
                                        ?: 'Sin observaciones'
                                    );
                            }
                        )
                        ->columnSpanFull(),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_parts'
                        )
                        ->label(
                            'Refacciones utilizadas'
                        )
                        ->content(
                            function ($record):
                                \Illuminate\Support\HtmlString {
                                if (! $record) {
                                    return new
                                        \Illuminate\Support\HtmlString(
                                            'Sin refacciones'
                                        );
                                }

                                $rows =
                                    \Illuminate\Support\Facades\DB::
                                        table(
                                            'repair_order_parts'
                                        )
                                        ->where(
                                            'repair_order_id',
                                            (int) $record->
                                                getKey()
                                        )
                                        ->orderBy('id')
                                        ->get();

                                if ($rows->isEmpty()) {
                                    return new
                                        \Illuminate\Support\HtmlString(
                                            'Sin refacciones'
                                        );
                                }

                                $items = [];

                                foreach ($rows as $row) {
                                    $name =
                                        trim(
                                            (string) (
                                                $row->
                                                    product_name
                                                ?? $row->
                                                    description
                                                ?? 'Refacción'
                                            )
                                        );

                                    $qty =
                                        number_format(
                                            (float) (
                                                $row->
                                                    quantity
                                                ?? 0
                                            ),
                                            2
                                        );

                                    $items[] =
                                        '<div>'
                                        . e($name)
                                        . ' · Cantidad: '
                                        . e($qty)
                                        . '</div>';
                                }

                                return new
                                    \Illuminate\Support\HtmlString(
                                        implode(
                                            '',
                                            $items
                                        )
                                    );
                            }
                        )
                        ->columnSpanFull(),
                ])
                ->columns(2),

            \Filament\Forms\Components\Section::make(
                'Presupuesto y autorización'
            )
                ->description(
                    'Valores definidos por el Encargado de Técnicos '
                    . 'y autorizaciones posteriores. '
                    . 'Recepción no puede modificarlos.'
                )
                ->schema([
                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_decision'
                        )
                        ->label(
                            'Decisión comercial'
                        )
                        ->content(
                            function ($record): string {
                                $review =
                                    static::
                                        receptionManagerReview(
                                            $record
                                        );

                                return match (
                                    (string) (
                                        $review[
                                            'decision'
                                        ]
                                        ?? ''
                                    )
                                ) {
                                    'cobrable' =>
                                        'Servicio cobrable',

                                    'garantia' =>
                                        'Garantía / sin cargo',

                                    'garantia_rechazada' =>
                                        'Garantía rechazada / cobrable',

                                    'cortesia' =>
                                        'Cortesía / sin cargo',

                                    default =>
                                        'Pendiente',
                                };
                            }
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_warranty'
                        )
                        ->label('Garantía')
                        ->content(
                            fn ($record): string =>
                                match (
                                    (string) (
                                        $record->
                                            warranty_status
                                        ?? ''
                                    )
                                ) {
                                    'no_aplica' =>
                                        'No aplica',

                                    'approved',
                                    'aprobada' =>
                                        'Aprobada',

                                    'rejected',
                                    'rechazada' =>
                                        'Rechazada',

                                    'pendiente',
                                    'pending' =>
                                        'Pendiente',

                                    default =>
                                        (string) (
                                            $record->
                                                warranty_status
                                            ?? '—'
                                        ),
                                }
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_parts_sale'
                        )
                        ->label(
                            'Refacciones'
                        )
                        ->content(
                            function ($record): string {
                                $review =
                                    static::
                                        receptionManagerReview(
                                            $record
                                        );

                                return static::
                                    receptionMoney(
                                        $review[
                                            'parts_sale_total'
                                        ]
                                        ?? $record->
                                            parts_sale_total
                                        ?? 0
                                    );
                            }
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_labor'
                        )
                        ->label('Mano de obra')
                        ->content(
                            function ($record): string {
                                $review =
                                    static::
                                        receptionManagerReview(
                                            $record
                                        );

                                return static::
                                    receptionMoney(
                                        $review[
                                            'labor_amount'
                                        ]
                                        ?? $record->
                                            labor_sale_total
                                        ?? 0
                                    );
                            }
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_other'
                        )
                        ->label('Otros cargos')
                        ->content(
                            function ($record): string {
                                $review =
                                    static::
                                        receptionManagerReview(
                                            $record
                                        );

                                return static::
                                    receptionMoney(
                                        $review[
                                            'other_amount'
                                        ]
                                        ?? $record->
                                            other_cost_estimate
                                        ?? 0
                                    );
                            }
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_quote_total'
                        )
                        ->label(
                            'Total presupuesto'
                        )
                        ->content(
                            fn ($record): string =>
                                static::
                                    receptionMoney(
                                        $record->
                                            quote_total
                                        ?? 0
                                    )
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_quote_status'
                        )
                        ->label(
                            'Estado presupuesto'
                        )
                        ->content(
                            fn ($record): string =>
                                match (
                                    (string) (
                                        $record->
                                            quote_status
                                        ?? ''
                                    )
                                ) {
                                    'not_required' =>
                                        'No requerido',

                                    'draft' =>
                                        'Borrador',

                                    'pending_internal' =>
                                        'Pendiente aprobación interna',

                                    'pending_customer' =>
                                        'Pendiente Vo.Bo. cliente',

                                    'customer_approved' =>
                                        'Autorizado por cliente',

                                    'customer_rejected' =>
                                        'Rechazado por cliente',

                                    'cancelled' =>
                                        'Cancelado',

                                    default =>
                                        (string) (
                                            $record->
                                                quote_status
                                            ?? '—'
                                        ),
                                }
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_authorized'
                        )
                        ->label(
                            'Total autorizado'
                        )
                        ->content(
                            fn ($record): string =>
                                static::
                                    receptionMoney(
                                        $record->
                                            approved_total_snapshot
                                        ?? $record->
                                            quote_total
                                        ?? 0
                                    )
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_customer_approval'
                        )
                        ->label(
                            'Vo.Bo. cliente'
                        )
                        ->content(
                            function ($record): string {
                                $metadata =
                                    static::
                                        receptionRepairMetadata(
                                            $record?->metadata
                                            ?? null
                                        );

                                return match (
                                    (string) data_get(
                                        $metadata,
                                        'customer_approval.status',
                                        ''
                                    )
                                ) {
                                    'approved' =>
                                        'Aprobado',

                                    'rejected' =>
                                        'Rechazado',

                                    default =>
                                        'No requerido / pendiente',
                                };
                            }
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_internal_hour_cost'
                        )
                        ->label(
                            'Costo interno por hora'
                        )
                        ->content(
                            fn ($record): string =>
                                filled(
                                    $record->
                                        labor_internal_hour_cost
                                    ?? null
                                )
                                    ? static::
                                        receptionMoney(
                                            $record->
                                                labor_internal_hour_cost
                                        )
                                    : 'No capturado en el flujo nuevo'
                        )
                        ->helperText(
                            'Dato interno informativo. '
                            . 'No puede modificarse desde Recepción.'
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_quote_notes'
                        )
                        ->label(
                            'Notas de presupuesto'
                        )
                        ->content(
                            fn ($record): string =>
                                filled(
                                    $record->
                                        quote_notes
                                    ?? null
                                )
                                    ? (string)
                                        $record->
                                            quote_notes
                                    : 'Sin observaciones'
                        )
                        ->columnSpanFull(),
                ])
                ->columns(3),

            \Filament\Forms\Components\Section::make(
                'Cobro y entrega'
            )
                ->description(
                    'Seguimiento informativo. '
                    . 'Los cobros se registran desde Cuentas por cobrar.'
                )
                ->schema([
                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_cxc'
                        )
                        ->label('CxC')
                        ->content(
                            fn ($record): string =>
                                static::
                                    receptionReceivableSnapshot(
                                        $record
                                    )['number']
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_cxc_status'
                        )
                        ->label(
                            'Estado de cobro'
                        )
                        ->content(
                            fn ($record): string =>
                                static::
                                    receptionReceivableSnapshot(
                                        $record
                                    )['status']
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_cxc_total'
                        )
                        ->label('Total')
                        ->content(
                            fn ($record): string =>
                                static::
                                    receptionMoney(
                                        static::
                                            receptionReceivableSnapshot(
                                                $record
                                            )['total']
                                    )
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_cxc_collected'
                        )
                        ->label('Cobrado')
                        ->content(
                            fn ($record): string =>
                                static::
                                    receptionMoney(
                                        static::
                                            receptionReceivableSnapshot(
                                                $record
                                            )['collected']
                                    )
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_cxc_balance'
                        )
                        ->label('Saldo')
                        ->content(
                            fn ($record): string =>
                                static::
                                    receptionMoney(
                                        static::
                                            receptionReceivableSnapshot(
                                                $record
                                            )['balance']
                                    )
                        ),

                    \Filament\Forms\Components\Placeholder::
                        make(
                            'reception_ro_delivery'
                        )
                        ->label(
                            'Estado de entrega'
                        )
                        ->content(
                            fn ($record): string =>
                                filled(
                                    $record->
                                        delivered_at
                                    ?? null
                                )
                                    ? 'Entregado'
                                    : (
                                        filled(
                                            $record->
                                                ready_for_delivery_at
                                            ?? null
                                        )
                                            ? 'Listo para entrega'
                                            : 'Pendiente'
                                    )
                        ),
                ])
                ->columns(3),
        ];
    }

    protected static function receptionRepairMetadata(
        mixed $raw
    ): array {
        if (is_array($raw)) {
            return $raw;
        }

        if (
            $raw instanceof
            \Illuminate\Contracts\Support\Arrayable
        ) {
            return $raw->toArray();
        }

        if (is_object($raw)) {
            return (array) $raw;
        }

        if (
            is_string($raw)
            && trim($raw) !== ''
        ) {
            $decoded =
                json_decode(
                    $raw,
                    true
                );

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected static function receptionManagerReview(
        mixed $record
    ): array {
        if (! $record) {
            return [];
        }

        $metadata =
            static::
                receptionRepairMetadata(
                    $record->metadata
                    ?? null
                );

        $review =
            $metadata['manager_review']
            ?? [];

        return
            is_array($review)
                ? $review
                : [];
    }

    protected static function receptionMoney(
        mixed $value
    ): string {
        return '$'
            . number_format(
                (float) ($value ?? 0),
                2
            )
            . ' MXN';
    }

    protected static function receptionReceivableSnapshot(
        mixed $record
    ): array {
        $empty = [
            'number' =>
                'Sin CxC',

            'status' =>
                'Sin cuenta por cobrar',

            'total' =>
                0.0,

            'collected' =>
                0.0,

            'balance' =>
                0.0,
        ];

        if (
            ! $record
            || empty(
                $record->
                    account_receivable_id
            )
            || ! \Illuminate\Support\Facades\Schema::
                hasTable(
                    'account_receivables'
                )
        ) {
            return $empty;
        }

        $query =
            \Illuminate\Support\Facades\DB::
                table(
                    'account_receivables'
                )
                ->where(
                    'id',
                    (int) $record->
                        account_receivable_id
                );

        if (
            \Illuminate\Support\Facades\Schema::
                hasColumn(
                    'account_receivables',
                    'company_id'
                )
            && (int) (
                $record->company_id
                ?? 0
            ) > 0
        ) {
            $query->where(
                'company_id',
                (int) $record->
                    company_id
            );
        }

        $row =
            $query->first();

        if (! $row) {
            return $empty;
        }

        $total =
            (float) (
                $row->total
                ?? 0
            );

        $collected =
            (float) (
                $row->
                    collected_total
                ?? 0
            );

        $balance =
            (float) (
                $row->
                    balance_total
                ?? max(
                    0,
                    $total
                    - $collected
                )
            );

        $status =
            match (
                (string) (
                    $row->status
                    ?? ''
                )
            ) {
                'paid' =>
                    'Cobrada',

                'partial' =>
                    'Cobro parcial',

                'open' =>
                    $balance <= 0.0001
                        ? 'Cobrada'
                        : (
                            $collected > 0.0001
                                ? 'Cobro parcial'
                                : 'Pendiente de cobro'
                        ),

                'cancelled' =>
                    'Cancelada',

                default =>
                    (string) (
                        $row->status
                        ?? 'Pendiente'
                    ),
            };

        return [
            'number' =>
                (string) (
                    $row->number
                    ?? (
                        'CxC #'
                        . $row->id
                    )
                ),

            'status' =>
                $status,

            'total' =>
                $total,

            'collected' =>
                $collected,

            'balance' =>
                $balance,
        ];
    }



    /*
     * BEXIA_ATC_TECHNICIAN_REPAIR_SCHEMA_V5_83_4C5B
     *
     * Pantalla operativa mínima del técnico.
     * Todos los componentes son Placeholder.
     */
    protected static function technicianFormSchema(): array
    {
        return [
            Forms\Components\Section::make(
                'Orden técnica'
            )
                ->description(
                    'Datos de referencia. El técnico puede consultarlos, pero no modificarlos.'
                )
                ->columns(3)
                ->schema([
                    Forms\Components\Placeholder::make(
                        'technician_order_folio'
                    )
                        ->label('Folio')
                        ->content(
                            fn ($record): string =>
                                filled($record?->folio)
                                    ? (string) $record->folio
                                    : '—'
                        ),

                    Forms\Components\Placeholder::make(
                        'technician_service_case'
                    )
                        ->label('Ticket origen')
                        ->content(
                            fn ($record): string =>
                                $record && filled(
                                    $record->service_case_id
                                )
                                    ? (
                                        ServiceAccess::serviceCaseLabel(
                                            (int) $record->service_case_id
                                        )
                                        ?? (
                                            '#'
                                            . $record->service_case_id
                                        )
                                    )
                                    : '—'
                        ),

                    Forms\Components\Placeholder::make(
                        'technician_customer'
                    )
                        ->label('Cliente')
                        ->content(
                            fn ($record): string =>
                                $record && filled(
                                    $record->customer_id
                                )
                                    ? (
                                        ServiceAccess::contactLabel(
                                            (int) $record->customer_id
                                        )
                                        ?? (
                                            '#'
                                            . $record->customer_id
                                        )
                                    )
                                    : '—'
                        ),

                    Forms\Components\Placeholder::make(
                        'technician_product'
                    )
                        ->label('Producto / modelo')
                        ->content(
                            function ($record): string {
                                if (! $record) {
                                    return '—';
                                }

                                if (
                                    filled(
                                        $record->product_name
                                    )
                                ) {
                                    return (string)
                                        $record->product_name;
                                }

                                if (
                                    filled(
                                        $record->product_id
                                    )
                                ) {
                                    return ServiceAccess::productLabel(
                                        (int) $record->product_id
                                    ) ?? '—';
                                }

                                return '—';
                            }
                        ),

                    Forms\Components\Placeholder::make(
                        'technician_serial'
                    )
                        ->label('Número de serie')
                        ->content(
                            fn ($record): string =>
                                filled(
                                    $record?->serial_number
                                )
                                    ? (string)
                                        $record->serial_number
                                    : '—'
                        ),

                    Forms\Components\Placeholder::make(
                        'technician_received_at'
                    )
                        ->label('Fecha de recepción')
                        ->content(
                            fn ($record): string =>
                                filled(
                                    $record?->received_at
                                )
                                    ? (string)
                                        $record->received_at
                                    : '—'
                        ),

                    Forms\Components\Placeholder::make(
                        'technician_assigned_employee'
                    )
                        ->label('Técnico asignado')
                        ->content(
                            fn ($record): string =>
                                $record && filled(
                                    $record->assigned_employee_id
                                )
                                    ? (
                                        ServiceAccess::employeeLabel(
                                            (int)
                                            $record->assigned_employee_id
                                        )
                                        ?? (
                                            '#'
                                            . $record->assigned_employee_id
                                        )
                                    )
                                    : '—'
                        ),

                    Forms\Components\Placeholder::make(
                        'technician_received_condition'
                    )
                        ->label('Condición de recepción')
                        ->content(
                            fn ($record): string =>
                                filled(
                                    $record?->received_condition
                                )
                                    ? (string)
                                        $record->received_condition
                                    : 'Sin observaciones registradas'
                        )
                        ->columnSpan(2),
                ]),

            Forms\Components\Section::make(
                'Trabajo técnico'
            )
                ->description(
                    'Aquí sólo se muestra el trabajo técnico. Para registrarlo usa el botón "Finalizar trabajo técnico".'
                )
                ->columns(2)
                ->schema([
                    Forms\Components\Placeholder::make(
                        'technician_work_status'
                    )
                        ->label('Estado')
                        ->content(
                            function ($record): string {
                                $work =
                                    static::technicianWorkMetadata(
                                        $record
                                    );

                                if (
                                    (string) (
                                        $work['status']
                                        ?? ''
                                    ) === 'completed'
                                ) {
                                    return
                                        'Trabajo técnico finalizado';
                                }

                                return
                                    'Pendiente de trabajo técnico';
                            }
                        ),

                    Forms\Components\Placeholder::make(
                        'technician_work_completed_at'
                    )
                        ->label('Finalizado')
                        ->content(
                            function ($record): string {
                                $work =
                                    static::technicianWorkMetadata(
                                        $record
                                    );

                                $completedAt =
                                    trim(
                                        (string) (
                                            $work[
                                                'completed_at'
                                            ]
                                            ?? ''
                                        )
                                    );

                                return
                                    $completedAt !== ''
                                        ? $completedAt
                                        : 'Pendiente';
                            }
                        ),

                    Forms\Components\Placeholder::make(
                        'technician_diagnosis_display'
                    )
                        ->label('Diagnóstico técnico')
                        ->content(
                            fn ($record): string =>
                                filled(
                                    $record?->technical_diagnosis
                                )
                                    ? (string)
                                        $record->technical_diagnosis
                                    : 'Pendiente'
                        )
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make(
                        'technician_work_performed_display'
                    )
                        ->label('Trabajo realizado')
                        ->content(
                            fn ($record): string =>
                                filled(
                                    $record?->resolution
                                )
                                    ? (string)
                                        $record->resolution
                                    : 'Pendiente'
                        )
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make(
                        'technician_tests_display'
                    )
                        ->label(
                            'Pruebas / observaciones finales'
                        )
                        ->content(
                            function ($record): string {
                                $work =
                                    static::technicianWorkMetadata(
                                        $record
                                    );

                                $tests =
                                    trim(
                                        (string) (
                                            $work[
                                                'tests_notes'
                                            ]
                                            ?? ''
                                        )
                                    );

                                return
                                    $tests !== ''
                                        ? $tests
                                        : 'Sin observaciones registradas';
                            }
                        )
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make(
                        'technician_parts_display'
                    )
                        ->label('Refacciones utilizadas')
                        ->content(
                            function (
                                $record
                            ): \Illuminate\Support\HtmlString {
                                if (
                                    ! $record
                                    || ! $record->getKey()
                                ) {
                                    return new
                                        \Illuminate\Support\HtmlString(
                                            'Sin refacciones registradas.'
                                        );
                                }

                                $rows =
                                    \Illuminate\Support\Facades\DB::
                                        table(
                                            'repair_order_parts'
                                        )
                                        ->where(
                                            'repair_order_id',
                                            $record->getKey()
                                        )
                                        ->orderBy('id')
                                        ->get([
                                            'product_id',
                                            'product_name',
                                            'description',
                                            'quantity',
                                            'notes',
                                        ]);

                                if ($rows->isEmpty()) {
                                    return new
                                        \Illuminate\Support\HtmlString(
                                            'Sin refacciones registradas.'
                                        );
                                }

                                $items = [];

                                foreach ($rows as $row) {
                                    $name =
                                        trim(
                                            (string) (
                                                $row->product_name
                                                ?? ''
                                            )
                                        );

                                    if (
                                        $name === ''
                                        && ! empty(
                                            $row->product_id
                                        )
                                    ) {
                                        $name =
                                            ServiceAccess::productLabel(
                                                (int)
                                                $row->product_id
                                            ) ?? '';
                                    }

                                    if ($name === '') {
                                        $name =
                                            trim(
                                                (string) (
                                                    $row->description
                                                    ?? ''
                                                )
                                            );
                                    }

                                    if ($name === '') {
                                        $name = 'Refacción';
                                    }

                                    $quantity =
                                        (float) (
                                            $row->quantity
                                            ?? 0
                                        );

                                    $qty =
                                        rtrim(
                                            rtrim(
                                                number_format(
                                                    $quantity,
                                                    2,
                                                    '.',
                                                    ''
                                                ),
                                                '0'
                                            ),
                                            '.'
                                        );

                                    if ($qty === '') {
                                        $qty = '0';
                                    }

                                    $notes =
                                        trim(
                                            (string) (
                                                $row->notes
                                                ?? ''
                                            )
                                        );

                                    $label =
                                        e($name)
                                        . ' · Cantidad: '
                                        . e($qty);

                                    if ($notes !== '') {
                                        $label .=
                                            ' · '
                                            . e($notes);
                                    }

                                    $items[] =
                                        '<li>'
                                        . $label
                                        . '</li>';
                                }

                                return new
                                    \Illuminate\Support\HtmlString(
                                        '<ul>'
                                        . implode(
                                            '',
                                            $items
                                        )
                                        . '</ul>'
                                    );
                            }
                        )
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make(
                        'technician_action_hint'
                    )
                        ->label('Siguiente paso')
                        ->content(
                            function ($record): string {
                                $work =
                                    static::technicianWorkMetadata(
                                        $record
                                    );

                                if (
                                    (string) (
                                        $work['status']
                                        ?? ''
                                    ) === 'completed'
                                ) {
                                    return
                                        'El trabajo técnico está finalizado y bloqueado. Las etapas posteriores corresponden al Encargado de Técnicos y a los roles autorizados.';
                                }

                                return
                                    'Usa "Finalizar trabajo técnico" para registrar diagnóstico, trabajo, refacciones y evidencia.';
                            }
                        )
                        ->columnSpanFull(),
                ]),
        ];
    }

    /*
     * Extrae metadata técnica sin asumir que Eloquent ya la
     * haya convertido a array.
     */
    protected static function technicianWorkMetadata(
        mixed $record
    ): array {
        if (! $record) {
            return [];
        }

        $metadata =
            $record->metadata
            ?? [];

        if (is_string($metadata)) {
            $decoded =
                json_decode(
                    $metadata,
                    true
                );

            $metadata =
                is_array($decoded)
                    ? $decoded
                    : [];
        }

        if (! is_array($metadata)) {
            return [];
        }

        $work =
            $metadata['technical_work']
            ?? [];

        return
            is_array($work)
                ? $work
                : [];
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

                Tables\Columns\TextColumn::make('sla_status_virtual')
                    ->label('SLA')
                    ->getStateUsing(fn ($record): string => \App\Support\Service\ServiceRepairSla::label($record))
                    ->badge()
                    ->color(fn ($record): string => \App\Support\Service\ServiceRepairSla::color($record))
                    ->description(fn ($record): string => \App\Support\Service\ServiceRepairSla::description($record))
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
                    ->label('Reabrir reparación')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->modalHeading(
                        'Reabrir reparación entregada'
                    )
                    ->modalDescription(
                        'La reparación regresará a En reparación. El ticket ATC se reabrirá, pero la CxC y los pagos existentes no serán modificados.'
                    )
                    ->modalSubmitActionLabel(
                        'Confirmar reapertura'
                    )
                    ->form([
                        Forms\Components\Textarea::make(
                            'reason'
                        )
                            ->label(
                                'Motivo de reapertura'
                            )
                            ->helperText(
                                'Describe por qué el cliente regresa el producto o por qué es necesario continuar la reparación.'
                            )
                            ->rows(4)
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->visible(
                        fn (
                            RepairOrder $record
                        ): bool =>
                            (
                                (string) (
                                    $record->workflow_stage
                                    ?? ''
                                ) === 'delivered'
                                || in_array(
                                    (string) (
                                        $record->status
                                        ?? ''
                                    ),
                                    [
                                        'delivered',
                                        'entregado',
                                        'cerrado',
                                    ],
                                    true
                                )
                            )
                            && static::canReopen()
                    )
                    ->action(
                        function (
                            RepairOrder $record,
                            array $data
                        ): void {
                            $result = app(
                                \App\Support\Service\ServiceRepairCaseLifecycleService::class
                            )->reopenAfterDelivery(
                                $record,
                                (string) (
                                    $data['reason']
                                    ?? ''
                                )
                            );

                            $record->refresh();

                            Notification::make()
                                ->title(
                                    'Reparación reabierta'
                                )
                                ->body(
                                    'La reparación regresó a En reparación y el ticket ATC quedó activo. La CxC existente se conservó sin cambios.'
                                )
                                ->success()
                                ->send();
                        }
                    ),
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
