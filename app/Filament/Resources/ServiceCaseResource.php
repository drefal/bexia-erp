<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceCaseResource\Pages;
use App\Models\ServiceCase;
use App\Models\ServiceCaseEvent;
use App\Support\Service\ServiceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/* BEXIA_ATC_LABEL_CLEANUP_V5_83_4C5F2 */
class ServiceCaseResource extends Resource
{
    protected static ?string $model = ServiceCase::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Atencion y Servicio';

    protected static ?string $navigationLabel = 'Tickets de servicio';

    protected static ?string $modelLabel = 'ticket de servicio';

    protected static ?string $pluralModelLabel = 'tickets de servicio';

    protected static ?int $navigationSort = 10;

    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function shouldRegisterNavigation(): bool
    {
        /*
         * BEXIA_ATC_TECH_ONLY_REPAIRS_NAV_V5_83_4C5F6A
         *
         * Para un usuario exclusivamente con rol
         * Servicio - Técnico, esta opción se oculta
         * del menú. El acceso backend existente se
         * conserva sin cambios.
         */
        if (
            \App\Support\Service\ServiceAccess::
                isRestrictedServiceTechnician()
        ) {
            return false;
        }

        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        /*
         * BEXIA_ATC_TECH_TICKET_ACCESS_V5_82_P7H32A4
         *
         * El Técnico puede abrir el módulo aun cuando no posea
         * permisos CRUD generales. La consulta queda limitada
         * posteriormente a sus tickets asignados.
         */
        if (
            ServiceAccess::technicianCanEnterServiceCases()
        ) {
            return true;
        }

        return ServiceAccess::can([
            'service.menu.view',
            'service.cases.view',
            'service.cases.create',
            'service.cases.update',
        ]);
    }

    public static function canView(Model $record): bool
    {
        if (
            ServiceAccess::isRestrictedServiceTechnician()
        ) {
            return ServiceAccess::isAssignedServiceCaseTechnician(
                $record
            );
        }

        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return ServiceAccess::can('service.cases.create');
    }

    public static function canEdit(Model $record): bool
    {
        if (
            ServiceAccess::isRestrictedServiceTechnician()
        ) {
            return ServiceAccess::isAssignedServiceCaseTechnician(
                $record
            );
        }

        if (in_array((string) ($record->status ?? ''), ['entregado', 'cerrado', 'rechazado', 'cancelado'], true)) {
            if ((string) ($record->attention_route ?? '') === 'non_repair') {
                return ServiceAccess::can('service.cases.update');
            }

            return ServiceAccess::can('service.repairs.reopen');
        }

        return ServiceAccess::can('service.cases.update');
    }

    public static function canDelete(Model $record): bool
    {
        return ServiceAccess::can('service.cases.delete');
    }

    public static function canDeleteAny(): bool
    {
        return ServiceAccess::can('service.cases.delete');
    }

    public static function getEloquentQuery(): Builder
    {
        $model = static::getModel();

        $query = $model::query();

        $companyId = ServiceAccess::currentCompanyId();

        if ($companyId && ServiceAccess::tableHasCompany('service_cases')) {
            $query->where('company_id', $companyId);
        }

        ServiceAccess::scopeServiceCasesForCurrentUser(
            $query
        );

        return $query;
    }


    /*
     * BEXIA_SVC_RESOURCE_RESPONSIVE_V5_79_48C
     * Visual-only responsive marker.
     */

    /*
     * BEXIA_ATC_TECH_MASTER_READONLY_V5_83_4B6
     *
     * El técnico y cualquier rol que no sea Recepción,
     * Encargado de Técnicos o Supervisor ve los datos
     * maestros del ticket en modo sólo lectura.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => ServiceAccess::currentCompanyId()),

                Forms\Components\Section::make('Atención asignada')
                    ->extraAttributes(['class' => 'bexia-svc-section bexia-svc-section-classification'])
                    ->description('El Encargado de Técnicos o el Supervisor define cómo se atenderá el ticket y asigna al responsable correspondiente.')
                    ->columns(4)
                    ->schema([
                        Forms\Components\Placeholder::make('attention_route_display')
                            ->label('Ruta')
                            ->content(fn ($record): string => $record
                                ? (ServiceCase::ATTENTION_ROUTES[(string) ($record->attention_route ?? '')] ?? 'Pendiente')
                                : 'Pendiente'),

                        Forms\Components\Placeholder::make('non_repair_type_display')
                            ->label('Tipo de gestión')
                            ->content(fn ($record): string => $record
                                ? (ServiceCase::NON_REPAIR_TYPES[(string) ($record->non_repair_type ?? '')] ?? 'No aplica')
                                : 'No aplica'),

                        Forms\Components\Placeholder::make('classified_at_display')
                            ->label('Atendido / asignado')
                            ->content(fn ($record): string => filled($record?->classified_at)
                                ? (string) $record->classified_at
                                : 'Pendiente'),

                        /*
                         * BEXIA_ATC_TICKET_REPAIR_OPERATIONAL_SUMMARY_V5_83_4C5G9C
                         *
                         * El ticket conserva su status técnico interno,
                         * pero muestra al usuario la etapa operativa real
                         * de la reparación vinculada.
                         */
                        Forms\Components\Placeholder::make(
                            'repair_operational_summary'
                        )
                            ->label(
                                'Estado actual de la reparación'
                            )
                            ->content(
                                function ($record): \Illuminate\Support\HtmlString {
                                    $repair =
                                        $record
                                            ?->repairOrders()
                                            ->orderByDesc('id')
                                            ->first();

                                    if (! $repair) {
                                        return new \Illuminate\Support\HtmlString(
                                            '<div style="padding:12px;border:1px solid #e5e7eb;border-radius:10px;">'
                                            . 'Aún no existe una orden de reparación.'
                                            . '</div>'
                                        );
                                    }

                                    $metadata =
                                        $repair->metadata
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

                                    $technical =
                                        data_get(
                                            $metadata,
                                            'technical_work',
                                            []
                                        );

                                    $manager =
                                        data_get(
                                            $metadata,
                                            'manager_review',
                                            []
                                        );

                                    $customer =
                                        data_get(
                                            $metadata,
                                            'customer_approval',
                                            []
                                        );

                                    $bridge =
                                        data_get(
                                            $metadata,
                                            'post_repair_collection_delivery'
                                        );

                                    $stage =
                                        (string) (
                                            $repair->
                                                workflow_stage
                                            ?? ''
                                        );

                                    $decision =
                                        (string) (
                                            $manager[
                                                'decision'
                                            ]
                                            ?? ''
                                        );

                                    $state =
                                        'Reparación en proceso';

                                    $next =
                                        'Continuar reparación';

                                    if (
                                        $stage
                                        === 'delivered'
                                    ) {
                                        $state =
                                            'Equipo entregado';

                                        $next =
                                            'Expediente de reparación terminado';
                                    } elseif (
                                        is_array(
                                            $bridge
                                        )
                                        && (
                                            $bridge[
                                                'status'
                                            ]
                                            ?? null
                                        ) === 'prepared'
                                    ) {
                                        $state =
                                            'Cobro y entrega preparados';

                                        $next =
                                            'Continuar con cobro / entrega';
                                    } elseif (
                                        (
                                            $manager[
                                                'status'
                                            ]
                                            ?? null
                                        ) === 'completed'
                                    ) {
                                        $state =
                                            'Reparación terminada';

                                        if (
                                            in_array(
                                                $decision,
                                                [
                                                    'garantia',
                                                    'cortesia',
                                                ],
                                                true
                                            )
                                        ) {
                                            $next =
                                                'Preparar entrega sin cobro';
                                        } elseif (
                                            in_array(
                                                $decision,
                                                [
                                                    'cobrable',
                                                    'garantia_rechazada',
                                                ],
                                                true
                                            )
                                        ) {
                                            $requiresCustomer =
                                                (bool) (
                                                    $manager[
                                                        'requires_customer_approval'
                                                    ]
                                                    ?? (
                                                        $repair->
                                                            requires_customer_approval
                                                        ?? false
                                                    )
                                                );

                                            $customerApproved =
                                                (
                                                    $customer[
                                                        'status'
                                                    ]
                                                    ?? null
                                                ) === 'approved';

                                            $next =
                                                $requiresCustomer
                                                && ! $customerApproved
                                                    ? 'Registrar Vo.Bo. del cliente'
                                                    : 'Preparar cobro y entrega';
                                        }
                                    } elseif (
                                        (
                                            $technical[
                                                'status'
                                            ]
                                            ?? null
                                        ) === 'completed'
                                    ) {
                                        $state =
                                            'Trabajo técnico terminado';

                                        $next =
                                            'Revisar y costear';
                                    }

                                    $diagnosis =
                                        trim(
                                            (string) (
                                                $technical[
                                                    'technical_diagnosis'
                                                ]
                                                ?? ''
                                            )
                                        );

                                    if ($diagnosis === '') {
                                        $diagnosis =
                                            'Pendiente';
                                    }

                                    $parts = [];

                                    foreach (
                                        (
                                            $technical[
                                                'parts'
                                            ]
                                            ?? []
                                        )
                                        as $part
                                    ) {
                                        if (
                                            ! is_array(
                                                $part
                                            )
                                        ) {
                                            continue;
                                        }

                                        $name =
                                            trim(
                                                (string) (
                                                    $part[
                                                        'product_name'
                                                    ]
                                                    ?? ''
                                                )
                                            );

                                        if ($name === '') {
                                            continue;
                                        }

                                        $qty =
                                            (float) (
                                                $part[
                                                    'quantity'
                                                ]
                                                ?? 0
                                            );

                                        $qtyLabel =
                                            abs(
                                                $qty
                                                - round($qty)
                                            ) < 0.0001
                                                ? (string) (
                                                    (int) round(
                                                        $qty
                                                    )
                                                )
                                                : number_format(
                                                    $qty,
                                                    2
                                                );

                                        $parts[] =
                                            $name
                                            . ' ×'
                                            . $qtyLabel;
                                    }

                                    $partsLabel =
                                        $parts !== []
                                            ? implode(
                                                ', ',
                                                $parts
                                            )
                                            : 'Sin refacciones registradas';

                                    $total =
                                        number_format(
                                            (float) (
                                                $repair->
                                                    quote_total
                                                ?? 0
                                            ),
                                            2
                                        );

                                    return new \Illuminate\Support\HtmlString(
                                        '<div style="padding:14px 16px;border:1px solid #dbe3ef;border-radius:12px;background:#f8fafc;">'
                                        . '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;">'

                                        . '<div>'
                                        . '<div style="font-size:12px;color:#64748b;">Estado del servicio</div>'
                                        . '<div style="font-weight:700;">'
                                        . e($state)
                                        . '</div>'
                                        . '</div>'

                                        . '<div>'
                                        . '<div style="font-size:12px;color:#64748b;">Resultado técnico</div>'
                                        . '<div style="font-weight:700;">'
                                        . e($diagnosis)
                                        . '</div>'
                                        . '</div>'

                                        . '<div>'
                                        . '<div style="font-size:12px;color:#64748b;">Total final</div>'
                                        . '<div style="font-weight:700;">$'
                                        . e($total)
                                        . '</div>'
                                        . '</div>'

                                        . '<div>'
                                        . '<div style="font-size:12px;color:#64748b;">Siguiente paso</div>'
                                        . '<div style="font-weight:700;">'
                                        . e($next)
                                        . '</div>'
                                        . '</div>'

                                        . '</div>'

                                        . '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #e2e8f0;">'
                                        . '<span style="font-size:12px;color:#64748b;">Refacciones: </span>'
                                        . '<span style="font-weight:600;">'
                                        . e($partsLabel)
                                        . '</span>'
                                        . '</div>'

                                        . '</div>'
                                    );
                                }
                            )
                            ->visible(
                                fn ($record): bool =>
                                    (bool) $record
                                    && (string) (
                                        $record->
                                            attention_route
                                        ?? ''
                                    ) === 'repair'
                                    && $record->
                                        repairOrders()
                                        ->exists()
                            )
                            ->columnSpanFull(),

                        /*
                         * BEXIA_ATC_DELIVERY_FROM_TICKET_V5_83_4C5G12
                         *
                         * Resumen de sólo lectura.
                         * ATC no registra pagos aquí.
                         */
                        Forms\Components\Placeholder::
                            make(
                                'repair_delivery_operational_status'
                            )
                            ->label(
                                'Cobro, salida y entrega'
                            )
                            ->content(
                                function ($record):
                                    \Illuminate\Support\HtmlString {
                                    if (! $record) {
                                        return new
                                            \Illuminate\Support\HtmlString(
                                                'Sin reparación vinculada.'
                                            );
                                    }

                                    $repair =
                                        $record->
                                            repairOrders()
                                            ->orderByDesc('id')
                                            ->first();

                                    if (! $repair) {
                                        return new
                                            \Illuminate\Support\HtmlString(
                                                'Sin reparación vinculada.'
                                            );
                                    }

                                    $rawMetadata =
                                        $repair->metadata
                                        ?? [];

                                    if (
                                        is_string(
                                            $rawMetadata
                                        )
                                    ) {
                                        $decoded =
                                            json_decode(
                                                $rawMetadata,
                                                true
                                            );

                                        $metadata =
                                            is_array(
                                                $decoded
                                            )
                                                ? $decoded
                                                : [];
                                    } elseif (
                                        is_array(
                                            $rawMetadata
                                        )
                                    ) {
                                        $metadata =
                                            $rawMetadata;
                                    } elseif (
                                        is_object(
                                            $rawMetadata
                                        )
                                    ) {
                                        $metadata =
                                            (array)
                                                $rawMetadata;
                                    } else {
                                        $metadata = [];
                                    }

                                    $bridge =
                                        data_get(
                                            $metadata,
                                            'post_repair_collection_delivery'
                                        );

                                    $noChargeBridge =
                                        data_get(
                                            $metadata,
                                            'post_repair_no_charge_delivery_bridge'
                                        );

                                    $policy =
                                        trim(
                                            (string) (
                                                data_get(
                                                    $metadata,
                                                    'post_repair_collection_delivery.collection_policy'
                                                )
                                                ?? ''
                                            )
                                        );

                                    if (
                                        $policy === ''
                                        && is_array(
                                            $noChargeBridge
                                        )
                                    ) {
                                        $policy =
                                            'no_charge';
                                    }

                                    $policyLabels = [
                                        'payment_required' =>
                                            'Cobro requerido antes de entrega',

                                        'payment_before_delivery' =>
                                            'Cobrar antes de salir a entrega',

                                        'payment_on_delivery' =>
                                            'Cobrar al momento de entregar',

                                        'credit_allowed' =>
                                            'Crédito autorizado',

                                        'no_charge' =>
                                            'Sin cobro',
                                    ];

                                    $policyLabel =
                                        $policyLabels[
                                            $policy
                                        ]
                                        ?? (
                                            $policy !== ''
                                                ? $policy
                                                : 'Pendiente de definir'
                                        );

                                    $receivable =
                                        null;

                                    $receivableId =
                                        (int) (
                                            $repair->
                                                account_receivable_id
                                            ?? 0
                                        );

                                    if (
                                        $receivableId > 0
                                        && \Illuminate\Support\Facades\Schema::
                                            hasTable(
                                                'account_receivables'
                                            )
                                    ) {
                                        $receivable =
                                            \Illuminate\Support\Facades\DB::
                                                table(
                                                    'account_receivables'
                                                )
                                                ->where(
                                                    'id',
                                                    $receivableId
                                                )
                                                ->first();
                                    }

                                    $total =
                                        (float) (
                                            $receivable->total
                                            ?? $repair->quote_total
                                            ?? 0
                                        );

                                    $collected =
                                        (float) (
                                            $receivable->
                                                collected_total
                                            ?? 0
                                        );

                                    $balance =
                                        (float) (
                                            $receivable->
                                                balance_total
                                            ?? $total
                                        );

                                    $repairPaymentStatus =
                                        (string) (
                                            $repair->
                                                economic_payment_status
                                            ?? ''
                                        );

                                    $receivableStatus =
                                        (string) (
                                            $receivable->status
                                            ?? ''
                                        );

                                    $paid =
                                        $policy === 'no_charge'
                                        || $repairPaymentStatus
                                            === 'paid'
                                        || $receivableStatus
                                            === 'paid'
                                        || (
                                            $receivable
                                            && $balance <= 0.009
                                        );

                                    if (
                                        $policy === 'no_charge'
                                    ) {
                                        $paymentLabel =
                                            'No aplica · $0.00';
                                    } elseif ($paid) {
                                        $paymentLabel =
                                            'Pagado · $'
                                            . number_format(
                                                $total,
                                                2
                                            );
                                    } elseif (
                                        $collected > 0.009
                                    ) {
                                        $paymentLabel =
                                            'Cobro parcial · $'
                                            . number_format(
                                                $collected,
                                                2
                                            )
                                            . ' de $'
                                            . number_format(
                                                $total,
                                                2
                                            );
                                    } elseif (
                                        $policy
                                        === 'credit_allowed'
                                    ) {
                                        $paymentLabel =
                                            'Saldo pendiente · crédito autorizado';
                                    } elseif (
                                        $receivableId > 0
                                    ) {
                                        $paymentLabel =
                                            'Pendiente de cobro · $'
                                            . number_format(
                                                max(
                                                    0,
                                                    $balance
                                                ),
                                                2
                                            );
                                    } else {
                                        $paymentLabel =
                                            'Pendiente de preparar';
                                    }

                                    $document =
                                        \App\Support\Service\ServiceAccess::
                                            repairExitDocument(
                                                $repair
                                            );

                                    $exitAuthorized =
                                        is_array(
                                            $document
                                        )
                                        && (
                                            $document[
                                                'status'
                                            ]
                                            ?? null
                                        ) === 'authorized';

                                    $exitLabel =
                                        $exitAuthorized
                                            ? (
                                                $document[
                                                    'folio'
                                                ]
                                                ?? 'SAL-ATC autorizada'
                                            )
                                            : 'Pendiente';

                                    $delivered =
                                        filled(
                                            $repair->
                                                delivered_at
                                        )
                                        || (
                                            (string) (
                                                $repair->
                                                    workflow_stage
                                                ?? ''
                                            )
                                            === 'delivered'
                                        );

                                    /*
                                     * BEXIA_ATC_TICKET_PAYMENT_STATUS_V5_83_4C5G12B
                                     *
                                     * Este estado es solamente operativo/visual.
                                     * NO modifica ServiceCase.status.
                                     */
                                    $repairStatusLabel =
                                        match (true) {
                                            $delivered =>
                                                'Reparación entregada',

                                            $policy === 'no_charge' =>
                                                'Reparación terminada · sin cobro',

                                            $policy === 'credit_allowed'
                                            && ! $paid =>
                                                'Reparación terminada · crédito autorizado',

                                            $collected > 0.009
                                            && ! $paid =>
                                                'Reparación terminada · cobro parcial',

                                            $receivableId > 0
                                            && ! $paid =>
                                                'Reparación terminada · pendiente de cobro',

                                            $paid =>
                                                'Reparación terminada · cobro registrado',

                                            default =>
                                                'Reparación terminada',
                                        };

                                    if ($delivered) {
                                        $deliveryLabel =
                                            'Entregado al cliente';

                                        $nextStep =
                                            'Servicio concluido';
                                    } elseif (
                                        ! $exitAuthorized
                                    ) {
                                        if (
                                            in_array(
                                                $policy,
                                                [
                                                    'payment_required',
                                                    'payment_before_delivery',
                                                ],
                                                true
                                            )
                                            && ! $paid
                                        ) {
                                            $deliveryLabel =
                                                'Pendiente de pago';

                                            $nextStep =
                                                'Esperar pago para generar salida';
                                        } else {
                                            $deliveryLabel =
                                                'Pendiente de salida';

                                            $nextStep =
                                                'Generar salida de equipo';
                                        }
                                     } elseif (
                                        in_array(
                                            $policy,
                                            [
                                                'payment_required',
                                                'payment_before_delivery',
                                            ],
                                            true
                                        )
                                        && ! $paid
                                    ) {
                                        $deliveryLabel =
                                            'Salida autorizada · pendiente de pago';

                                        $nextStep =
                                            'Esperar registro de pago';
                                    } elseif (
                                        $policy === 'payment_on_delivery'
                                        && ! $paid
                                    ) {
                                        /*
                                         * BEXIA_ATC_PAYMENT_ON_DELIVERY_READY_V5_83_4C5G12H
                                         *
                                         * La CxC permanece pendiente,
                                         * pero la entrega física está permitida
                                         * porque el cobro ocurre contra entrega.
                                         */
                                        $deliveryLabel =
                                            'Lista para entrega · cobro contra entrega';

                                        $nextStep =
                                            'Entregar al cliente';
                                    } else {
                                        $deliveryLabel =
                                            'Lista para entrega';

                                        $nextStep =
                                            'Entregar al cliente';
                                    }

                                    /*
                                     * BEXIA_ATC_TICKET_CXC_LINK_V5_83_4C5G12D
                                     *
                                     * ATC puede consultar la CxC enlazada.
                                     * El permiso sigue perteneciendo al
                                     * AccountReceivableResource.
                                     */
                                    /*
                                     * BEXIA_ATC_TICKET_CXC_MODAL_V5_83_4C5G12F
                                     *
                                     * Consulta de CxC EN EL MISMO TICKET.
                                     *
                                     * BEXIA_ATC_TICKET_CXC_MODAL_FIX_V5_83_4C5G12G
                                     *
                                     * El cierre NO usa un formulario dialog anidado
                                     * porque esta vista vive dentro del formulario
                                     * principal de Filament.
                                     *
                                     * No hay navegación al módulo general.
                                     * No hay formulario.
                                     * No hay acciones de pago.
                                     *
                                     * La CxC sigue resolviéndose únicamente
                                     * desde RepairOrder.account_receivable_id.
                                     */
                                    $receivableNumber =
                                        trim(
                                            (string) (
                                                $receivable->number
                                                ?? ''
                                            )
                                        );

                                    if (
                                        $receivableNumber === ''
                                        && $receivableId > 0
                                    ) {
                                        $receivableNumber =
                                            'CxC #'
                                            . $receivableId;
                                    }

                                    $receivableStatus =
                                        (string) (
                                            $receivable->status
                                            ?? ''
                                        );

                                    $receivableStatusLabel =
                                        match (
                                            $receivableStatus
                                        ) {
                                            'draft' =>
                                                'Borrador',

                                            'open' =>
                                                'Pendiente',

                                            'partial',
                                            'partially_paid' =>
                                                'Cobro parcial',

                                            'paid' =>
                                                'Pagada',

                                            'closed' =>
                                                'Cerrada',

                                            'cancelled',
                                            'canceled' =>
                                                'Cancelada',

                                            default =>
                                                $receivableStatus !== ''
                                                    ? $receivableStatus
                                                    : 'Sin estado',
                                        };

                                    $modalId =
                                        'bexia-cxc-modal-'
                                        . (int) $record->getKey()
                                        . '-'
                                        . $receivableId;

                                    $receivableReferenceHtml =
                                        '';

                                    if (
                                        $receivableId > 0
                                        && $receivable
                                    ) {
                                        $customerName =
                                            trim(
                                                (string) (
                                                    $receivable->
                                                        customer_name
                                                    ?? $record->
                                                        contact_name
                                                    ?? ''
                                                )
                                            );

                                        $issueDate =
                                            (string) (
                                                $receivable->
                                                    issue_date
                                                ?? '-'
                                            );

                                        $dueDate =
                                            (string) (
                                                $receivable->
                                                    due_date
                                                ?? '-'
                                            );

                                        $currency =
                                            (string) (
                                                $receivable->
                                                    currency
                                                ?? 'MXN'
                                            );

                                        $ticketFolio =
                                            (string) (
                                                $record->folio
                                                ?? '-'
                                            );

                                        $repairFolio =
                                            (string) (
                                                $repair->folio
                                                ?? '-'
                                            );

                                        $receivableReferenceHtml =
                                            '<div style="margin-top:7px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">'

                                            . '<span style="font-size:11px;color:#64748b;">'
                                            . e(
                                                $receivableNumber
                                            )
                                            . '</span>'

                                            . '<button '
                                            . 'type="button" '
                                            . 'onclick="document.getElementById(\''
                                            . e($modalId)
                                            . '\').showModal()" '
                                            . 'style="'
                                            . 'display:inline-flex;'
                                            . 'align-items:center;'
                                            . 'gap:4px;'
                                            . 'padding:6px 11px;'
                                            . 'border:1px solid #1d4ed8;'
                                            . 'border-radius:7px;'
                                            . 'background:#2563eb;'
                                            . 'color:#ffffff;'
                                            . 'font-size:13px;'
                                            . 'font-weight:700;'
                                            . 'line-height:1.2;'
                                            . 'cursor:pointer;'
                                            . 'box-shadow:0 1px 2px rgba(15,23,42,.12);'
                                            . '"'
                                            . '>'
                                            . 'Ver cobro · '
                                            . e(
                                                $receivableStatusLabel
                                            )
                                            . '</button>'

                                            . '</div>'

                                            . '<style>'
                                            . '#'
                                            . e($modalId)
                                            . '::backdrop{'
                                            . 'background:rgba(15,23,42,.45);'
                                            . '}'
                                            . '</style>'

                                            . '<dialog '
                                            . 'id="'
                                            . e($modalId)
                                            . '" '
                                            . 'onclick="if(event.target===this){this.close();}" '
                                            . 'style="'
                                            . 'width:min(620px,calc(100vw - 32px));'
                                            . 'max-width:620px;'
                                            . 'padding:0;'
                                            . 'border:0;'
                                            . 'border-radius:14px;'
                                            . 'box-shadow:0 24px 60px rgba(15,23,42,.28);'
                                            . 'color:#0f172a;'
                                            . '"'
                                            . '>'

                                            . '<div style="padding:20px 22px 16px;border-bottom:1px solid #e2e8f0;">'
                                            . '<div style="font-size:18px;font-weight:800;">'
                                            . 'Detalle de cobro'
                                            . '</div>'
                                            . '<div style="margin-top:3px;font-size:12px;color:#64748b;">'
                                            . e(
                                                $receivableNumber
                                            )
                                            . ' · Consulta de sólo lectura'
                                            . '</div>'
                                            . '</div>'

                                            . '<div style="padding:18px 22px;">'

                                            . '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">'

                                            . '<div style="padding:10px 12px;border:1px solid #e2e8f0;border-radius:9px;">'
                                            . '<div style="font-size:10px;color:#64748b;">Estado</div>'
                                            . '<div style="font-weight:700;">'
                                            . e(
                                                $receivableStatusLabel
                                            )
                                            . '</div>'
                                            . '</div>'

                                            . '<div style="padding:10px 12px;border:1px solid #e2e8f0;border-radius:9px;">'
                                            . '<div style="font-size:10px;color:#64748b;">Cliente</div>'
                                            . '<div style="font-weight:700;">'
                                            . e(
                                                $customerName !== ''
                                                    ? $customerName
                                                    : '-'
                                            )
                                            . '</div>'
                                            . '</div>'

                                            . '<div style="padding:10px 12px;border:1px solid #e2e8f0;border-radius:9px;">'
                                            . '<div style="font-size:10px;color:#64748b;">Ticket ATC</div>'
                                            . '<div style="font-weight:700;">'
                                            . e(
                                                $ticketFolio
                                            )
                                            . '</div>'
                                            . '</div>'

                                            . '<div style="padding:10px 12px;border:1px solid #e2e8f0;border-radius:9px;">'
                                            . '<div style="font-size:10px;color:#64748b;">Orden de reparación</div>'
                                            . '<div style="font-weight:700;">'
                                            . e(
                                                $repairFolio
                                            )
                                            . '</div>'
                                            . '</div>'

                                            . '<div style="padding:10px 12px;border:1px solid #e2e8f0;border-radius:9px;">'
                                            . '<div style="font-size:10px;color:#64748b;">Fecha de emisión</div>'
                                            . '<div style="font-weight:600;">'
                                            . e(
                                                $issueDate
                                            )
                                            . '</div>'
                                            . '</div>'

                                            . '<div style="padding:10px 12px;border:1px solid #e2e8f0;border-radius:9px;">'
                                            . '<div style="font-size:10px;color:#64748b;">Fecha de vencimiento</div>'
                                            . '<div style="font-weight:600;">'
                                            . e(
                                                $dueDate
                                            )
                                            . '</div>'
                                            . '</div>'

                                            . '</div>'

                                            . '<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:12px;">'

                                            . '<div style="padding:12px;border:1px solid #e2e8f0;border-radius:9px;">'
                                            . '<div style="font-size:10px;color:#64748b;">Total</div>'
                                            . '<div style="font-size:17px;font-weight:800;">$'
                                            . e(
                                                number_format(
                                                    $total,
                                                    2
                                                )
                                            )
                                            . '</div>'
                                            . '</div>'

                                            . '<div style="padding:12px;border:1px solid #e2e8f0;border-radius:9px;">'
                                            . '<div style="font-size:10px;color:#64748b;">Cobrado</div>'
                                            . '<div style="font-size:17px;font-weight:800;">$'
                                            . e(
                                                number_format(
                                                    $collected,
                                                    2
                                                )
                                            )
                                            . '</div>'
                                            . '</div>'

                                            . '<div style="padding:12px;border:1px solid #e2e8f0;border-radius:9px;">'
                                            . '<div style="font-size:10px;color:#64748b;">Saldo pendiente</div>'
                                            . '<div style="font-size:17px;font-weight:800;">$'
                                            . e(
                                                number_format(
                                                    $balance,
                                                    2
                                                )
                                            )
                                            . '</div>'
                                            . '</div>'

                                            . '</div>'

                                            . '<div style="margin-top:12px;padding:10px 12px;border-radius:9px;background:#f8fafc;color:#475569;font-size:11px;line-height:1.45;">'
                                            . 'El pago se registra por el área autorizada de Cuentas por cobrar. '
                                            . 'ATC sólo consulta el estado desde este ticket.'
                                            . '</div>'

                                            . '<div style="margin-top:8px;font-size:11px;color:#64748b;">'
                                            . 'Moneda: '
                                            . e(
                                                $currency
                                            )
                                            . '</div>'

                                            . '</div>'

                                            . '<div style="display:flex;justify-content:flex-end;padding:13px 22px;border-top:1px solid #e2e8f0;background:#f8fafc;">'
                                            . '<button '
                                            . 'type="button" '
                                            . 'onclick="document.getElementById(\''
                                            . e($modalId)
                                            . '\').close()" '
                                            . 'style="'
                                            . 'padding:7px 14px;'
                                            . 'border:1px solid #cbd5e1;'
                                            . 'border-radius:8px;'
                                            . 'background:#ffffff;'
                                            . 'font-weight:700;'
                                            . 'cursor:pointer;'
                                            . '"'
                                            . '>'
                                            . 'Cerrar'
                                            . '</button>'
                                            . '</div>'

                                            . '</dialog>';
                                    }

                                    $html =
                                        '<div style="'
                                        . 'display:grid;'
                                        . 'grid-template-columns:repeat(2,minmax(0,1fr));'
                                        . 'gap:10px;'
                                        . 'width:100%;'
                                        . '">'

                                        . '<div style="grid-column:1/-1;padding:12px 14px;border:1px solid #e5e7eb;border-radius:10px;">'
                                        . '<div style="font-size:11px;color:#6b7280;">Estado de reparación</div>'
                                        . '<div style="font-size:15px;font-weight:700;">'
                                        . e($repairStatusLabel)
                                        . '</div></div>'

                                        . '<div style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;">'
                                        . '<div style="font-size:11px;color:#6b7280;">Cobro</div>'
                                        . '<div style="font-weight:600;">'
                                        . e($paymentLabel)
                                        . '</div>'
                                        . $receivableReferenceHtml
                                        . '</div>'

                                        . '<div style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;">'
                                        . '<div style="font-size:11px;color:#6b7280;">Condición</div>'
                                        . '<div style="font-weight:600;">'
                                        . e($policyLabel)
                                        . '</div></div>'

                                        . '<div style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;">'
                                        . '<div style="font-size:11px;color:#6b7280;">Salida</div>'
                                        . '<div style="font-weight:600;">'
                                        . e($exitLabel)
                                        . '</div></div>'

                                        . '<div style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;">'
                                        . '<div style="font-size:11px;color:#6b7280;">Entrega</div>'
                                        . '<div style="font-weight:600;">'
                                        . e($deliveryLabel)
                                        . '</div></div>'

                                        . '<div style="grid-column:1/-1;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;">'
                                        . '<div style="font-size:11px;color:#6b7280;">Siguiente paso</div>'
                                        . '<div style="font-weight:700;">'
                                        . e($nextStep)
                                        . '</div></div>'

                                        . '</div>';

                                    return new
                                        \Illuminate\Support\HtmlString(
                                            $html
                                        );
                                }
                            )
                            ->visible(
                                function ($record):
                                    bool {
                                    if (
                                        ! $record
                                        || (
                                            (string) (
                                                $record->
                                                    attention_route
                                                ?? ''
                                            )
                                            !== 'repair'
                                        )
                                    ) {
                                        return false;
                                    }

                                    $repair =
                                        $record->
                                            repairOrders()
                                            ->orderByDesc('id')
                                            ->first();

                                    if (! $repair) {
                                        return false;
                                    }

                                    $raw =
                                        $repair->metadata
                                        ?? [];

                                    if (
                                        is_string($raw)
                                    ) {
                                        $raw =
                                            json_decode(
                                                $raw,
                                                true
                                            )
                                            ?: [];
                                    }

                                    if (
                                        ! is_array($raw)
                                    ) {
                                        $raw = [];
                                    }

                                    return
                                        in_array(
                                            (string) (
                                                $repair->
                                                    workflow_stage
                                                ?? ''
                                            ),
                                            [
                                                'ready_for_delivery',
                                                'delivered',
                                                'finished',
                                            ],
                                            true
                                        )
                                        || (
                                            (int) (
                                                $repair->
                                                    account_receivable_id
                                                ?? 0
                                            ) > 0
                                        )
                                        || is_array(
                                            data_get(
                                                $raw,
                                                'post_repair_collection_delivery'
                                            )
                                        )
                                        || is_array(
                                            data_get(
                                                $raw,
                                                'post_repair_no_charge_delivery_bridge'
                                            )
                                        )
                                        || is_array(
                                            data_get(
                                                $raw,
                                                'atc_exit_document'
                                            )
                                        );
                                }
                            )
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('repair_order_display')
                            ->label('Orden vinculada')
                            ->content(function ($record): string {
                                if (! $record) {
                                    return 'No aplica';
                                }

                                $repair = $record->repairOrders()
                                    ->orderByDesc('id')
                                    ->first();

                                return $repair?->folio ?: 'No aplica';
                            }),

                        Forms\Components\Placeholder::make('assigned_employee_display')
                            ->label('Responsable')
                            ->content(fn ($record): string => filled($record?->assigned_employee_id)
                                ? (ServiceAccess::employeeLabel((int) $record->assigned_employee_id) ?? 'Sin asignar')
                                : 'Sin asignar'),

                        Forms\Components\Placeholder::make('due_at_display')
                            ->label('Fecha compromiso')
                            ->content(fn ($record): string => filled($record?->due_at)
                                ? (string) $record->due_at
                                : 'Sin fecha'),

                        Forms\Components\Placeholder::make('classification_notes_display')
                            ->label('Notas')
                            ->content(fn ($record): string => filled($record?->classification_notes)
                                ? (string) $record->classification_notes
                                : 'Sin notas')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record): bool => (bool) $record && filled($record->attention_route))
                    ->collapsible(),

                Forms\Components\Section::make('Resolución del ticket')
                    ->extraAttributes([
                        'class' =>
                            'bexia-svc-section bexia-svc-section-direct-attention',
                    ])
                    ->description(
                        'Seguimiento y resolución del ticket que no requiere reparación.'
                    )
                    ->columns(4)
                    ->schema([
                        Forms\Components\Placeholder::make(
                            'direct_attention_type_display'
                        )
                            ->label('Tipo de atención')
                            ->content(fn ($record): string =>
                                ServiceCase::NON_REPAIR_TYPES[
                                    (string) (
                                        $record?->non_repair_type
                                        ?? ''
                                    )
                                ]
                                ?? 'Sin definir'
                            ),

                        Forms\Components\Placeholder::make(
                            'first_response_display'
                        )
                            ->label('Primera respuesta')
                            ->content(fn ($record): string =>
                                filled(
                                    $record?->first_response_at
                                )
                                    ? (string)
                                        $record
                                            ->first_response_at
                                    : 'Pendiente'
                            ),

                        Forms\Components\Placeholder::make(
                            'resolution_type_display'
                        )
                            ->label('Resolución')
                            ->content(fn ($record): string =>
                                \App\Support\Service\ServiceCaseDirectAttentionService::resolutionTypeLabel(
                                    $record?->resolution_type
                                )
                            ),

                        Forms\Components\Placeholder::make(
                            'direct_closed_at_display'
                        )
                            ->label('Cerrado')
                            ->content(fn ($record): string =>
                                filled($record?->closed_at)
                                    ? (string)
                                        $record->closed_at
                                    : 'Abierto'
                            ),

                        Forms\Components\Placeholder::make(
                            'resolution_notes_display'
                        )
                            ->label('Solución proporcionada')
                            ->content(fn ($record): string =>
                                filled(
                                    $record?->resolution_notes
                                )
                                    ? (string)
                                        $record
                                            ->resolution_notes
                                    : 'Pendiente'
                            )
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record): bool =>
                        (bool) $record
                        && (string) (
                            $record->attention_route
                            ?? ''
                        ) === 'non_repair'
                    )
                    ->collapsible(),
                Forms\Components\Section::make('Solicitud')
                    ->disabled(
                        fn ($record): bool =>
                            (bool) $record
                            && ! ServiceAccess::canEditServiceCaseMasterData()
                    )
                    ->extraAttributes(['class' => 'bexia-svc-section bexia-svc-section-general'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('folio')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-folio'])
                            ->label('Folio')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Placeholder::make('status_display')
                            ->label('Estado')
                            ->content(fn ($record): string => $record
                                ? $record->visibleStatusLabel()
                                : 'Nuevo'),

                        Forms\Components\Hidden::make('status')
                            ->default('nuevo'),

                        Forms\Components\Select::make('priority')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-priority'])
                            ->label('Prioridad')
                            ->options(ServiceCase::OPERATIONAL_PRIORITIES)
                            ->required()
                            ->default('media'),

                        Forms\Components\Select::make('channel')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-channel'])
                            ->label('Medio de contacto')
                            ->options(ServiceCase::OPERATIONAL_CHANNELS)
                            ->required()
                            ->default('manual'),

                        Forms\Components\Hidden::make('case_type')
                            ->default('general'),

                        Forms\Components\Hidden::make('assigned_team')
                            ->dehydrated(false),

                    ]),

                Forms\Components\Section::make('Cliente / contacto')
                    ->disabled(
                        fn ($record): bool =>
                            (bool) $record
                            && ! ServiceAccess::canEditServiceCaseMasterData()
                    )
                    ->extraAttributes(['class' => 'bexia-svc-section bexia-svc-section-contact'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-customer'])
                            ->extraFieldWrapperAttributes([
                                'class' => 'bexia-svc-dropdown-overlay-host bexia-svc-customer-overlay-host',
                                'style' => 'position: relative; overflow: visible; z-index: 40;',
                            ])
                            ->helperText(new \Illuminate\Support\HtmlString(<<<'HTML'
<style id="bexia-svc-dropdown-overlay-style">
.bexia-svc-dropdown-overlay-host,
.bexia-svc-dropdown-overlay-host .fi-fo-field-wrp,
.bexia-svc-dropdown-overlay-host .fi-input-wrp,
.fi-section:has(.bexia-svc-dropdown-overlay-host),
.fi-section:has(.bexia-svc-dropdown-overlay-host) .fi-section-content-ctn,
.fi-section:has(.bexia-svc-dropdown-overlay-host) .fi-section-content {
    overflow: visible !important;
}
.bexia-svc-dropdown-overlay-host:focus-within {
    z-index: 9999 !important;
}
.bexia-svc-dropdown-overlay-host .choices {
    position: relative !important;
    overflow: visible !important;
}
.bexia-svc-dropdown-overlay-host .choices__list--dropdown {
    position: absolute !important;
    inset-inline: 0 !important;
    top: calc(100% + 0.25rem) !important;
    width: 100% !important;
    min-width: 100% !important;
    height: auto !important;
    max-height: none !important;
    overflow: visible !important;
    z-index: 9999 !important;
}
.bexia-svc-dropdown-overlay-host .choices__list--dropdown .choices__input {
    position: static !important;
}
.bexia-svc-dropdown-overlay-host .choices__list--dropdown .choices__list[role="listbox"] {
    position: static !important;
    inset: auto !important;
    top: auto !important;
    width: auto !important;
    min-width: 0 !important;
    max-height: 18rem !important;
    overflow-y: auto !important;
    z-index: auto !important;
}
</style>
HTML))
                            ->label('Cliente')
                            ->options(ServiceAccess::contactOptions())
                            ->required(
                                fn ($record): bool =>
                                    $record === null
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $details = ServiceAccess::contactDetails((int) $state);

                                $set('contact_name', $details['contact_name'] ?? null);
                                $set('contact_email', $details['contact_email'] ?? null);
                                $set('contact_phone', $details['contact_phone'] ?? null);
                            }),

                        Forms\Components\TextInput::make('contact_name')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-contact-name'])
                            ->label('Contacto')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_phone')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-phone'])
                            ->label('Telefono')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_email')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-email'])
                            ->label('Correo')
                            ->email()
                            ->maxLength(255),
                    ]),

                Forms\Components\Section::make('Caso')
                    ->disabled(
                        fn ($record): bool =>
                            (bool) $record
                            && ! ServiceAccess::canEditServiceCaseMasterData()
                    )
                    ->extraAttributes(['class' => 'bexia-svc-section bexia-svc-section-case'])
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-subject'])
                            ->label('Asunto')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-description'])
                            ->label('Descripción')
                            ->required(
                                fn ($record): bool =>
                                    $record === null
                            )
                            ->rows(5)
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('uploaded_attachments')
                            ->label('Imágenes / evidencias')
                            ->helperText('Agrega fotografías, documentos o evidencia correspondiente a este paso del ticket.')
                            ->multiple()
                            ->disk('public')
                            ->directory('service-attachments/tickets')
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

                Forms\Components\Section::make('Producto / documento relacionado')
                    ->disabled(
                        fn ($record): bool =>
                            (bool) $record
                            && ! ServiceAccess::canEditServiceCaseMasterData()
                    )
                    ->extraAttributes(['class' => 'bexia-svc-section bexia-svc-section-product'])
                    ->description('Opcional. Usa catálogo si existe; si no, captura libremente producto, serie, lote, venta o factura.')
                    ->columns(12)
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-product'])
                            ->extraFieldWrapperAttributes([
                                'class' => 'bexia-svc-dropdown-overlay-host bexia-svc-product-overlay-host',
                                'style' => 'position: relative; overflow: visible; z-index: 40;',
                            ])
                            ->label('Producto catálogo')
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->searchPrompt('Busca por SKU, nombre o descripción')
                            ->searchingMessage('Buscando productos...')
                            ->noSearchResultsMessage('No se encontraron productos')
                            ->searchDebounce(400)
                            ->optionsLimit(50)
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::productOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::productLabel((int) $value))
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $set('product_name', ServiceAccess::productLabel((int) $state));
                            })
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('product_name')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-product-name'])
                            ->label('Producto / modelo libre')
                            ->helperText('Captura manual cuando el producto aún no exista en catálogo.')
                            ->maxLength(255)
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('serial_number')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-serial'])
                            ->label('Número de serie libre')
                            ->helperText('Captura libre mientras se cargan las series reales.')
                            ->maxLength(255)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('lot_number')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-lot'])
                            ->label('Lote libre')
                            ->helperText('Captura libre mientras se cargan lotes reales.')
                            ->maxLength(255)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('sale_reference')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-sale-reference'])
                            ->label('Venta / documento libre')
                            ->helperText('Folio, pedido, nota o referencia manual.')
                            ->maxLength(255)
                            ->columnSpan(4),

                        Forms\Components\Select::make('sale_id')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-sale'])
                            ->label('Venta relacionada')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => ServiceAccess::saleOrderOptions())
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::saleOrderOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::saleOrderLabel((int) $value))
                            ->columnSpan(6),

                        Forms\Components\Select::make('invoice_id')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-invoice'])
                            ->label('Factura relacionada')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => ServiceAccess::invoiceOptions())
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::invoiceOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::invoiceLabel((int) $value))
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('invoice_reference')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-invoice-reference'])
                            ->label('Factura / folio libre')
                            ->helperText('UUID, folio fiscal, serie-folio o referencia manual.')
                            ->maxLength(255)
                            ->columnSpan(3),
                    ]),

                
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('folio')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-folio bexia-svc-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-folio bexia-svc-col-primary'])
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(35)
                    ->wrap(),

                Tables\Columns\TextColumn::make('subject')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-subject bexia-svc-col-wrap'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-subject bexia-svc-col-wrap'])
                    ->label('Asunto')
                    ->searchable()
                    ->limit(45),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-status'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-status'])
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state, ServiceCase $record): string =>
                            $record->visibleStatusLabel()
                    )
                    ->color(
                        fn (ServiceCase $record): string =>
                            match ($record->visibleStatusKey()) {
                                'nuevo' => 'gray',
                                'en_atencion' => 'warning',
                                'resuelto' => 'success',
                                'cerrado' => 'gray',
                                default => 'gray',
                            }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('operational_substatus')
                    ->label('Subestado')
                    ->getStateUsing(
                        fn (ServiceCase $record): string =>
                            $record->operationalSubstatusLabel()
                    )
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('attention_route')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-route'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-route'])
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'repair' => 'Equipo',
                        'non_repair' => 'Gestión',
                        default => 'Pendiente',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('priority')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-priority'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-priority'])
                    ->label('Prioridad')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            ServiceCase::PRIORITIES[$state]
                                ?? ($state ?: 'Normal')
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('case_type')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-case-type'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-case-type'])
                    ->label('Tipo interno')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('product_name')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-product bexia-svc-col-wrap'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-product bexia-svc-col-wrap'])
                    ->label('Producto')
                    ->searchable()
                    ->limit(35)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('serial_number')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-serial bexia-svc-col-wrap'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-serial bexia-svc-col-wrap'])
                    ->label('Serie')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('invoice_id')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-invoice'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-invoice'])
                    ->label('Factura')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('channel')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-channel'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-channel'])
                    ->label('Medio')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('assigned_team')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-team bexia-svc-col-wrap'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-team bexia-svc-col-wrap'])
                    ->label('Equipo')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('assigned_employee_id')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-technician'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-technician'])
                    ->label('Responsable')
                    ->formatStateUsing(fn ($state): ?string => filled($state) ? ServiceAccess::employeeLabel((int) $state) : null)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('company_id')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-company'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-company'])
                    ->label('Empresa')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('due_at')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-due-at'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-due-at'])
                    ->label('Compromiso')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última actividad')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-created-at'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-created-at'])
                    ->label('Creado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(ServiceCase::VISIBLE_STATUSES)
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'nuevo' =>
                                $query->where('status', 'nuevo'),

                            'en_atencion' =>
                                $query->whereNotIn('status', [
                                    'nuevo',
                                    'resuelto',
                                    'entregado',
                                    'cerrado',
                                    'rechazado',
                                    'cancelado',
                                ]),

                            'resuelto' =>
                                $query->whereIn('status', [
                                    'resuelto',
                                    'entregado',
                                ]),

                            'cerrado' =>
                                $query->whereIn('status', [
                                    'cerrado',
                                    'rechazado',
                                    'cancelado',
                                ]),

                            default => $query,
                        };
                    }),

                Tables\Filters\SelectFilter::make('attention_route')
                    ->label('Ruta de atención')
                    ->options(ServiceCase::ATTENTION_ROUTES),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(ServiceCase::PRIORITIES),

                Tables\Filters\SelectFilter::make('case_type')
                    ->label('Tipo')
                    ->options(ServiceCase::CASE_TYPES),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(
                        fn (ServiceCase $record): string =>
                            $record->nextActionLabel()
                    )
                    ->visible(fn (ServiceCase $record): bool => static::canEdit($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => static::canDeleteAny()),
                ]),
            ]);
    }

    public static function logEvent(ServiceCase $record, string $eventType, ?string $fromStatus = null, ?string $toStatus = null, ?string $notes = null): void
    {
        ServiceCaseEvent::create([
            'company_id' => $record->company_id,
            'service_case_id' => $record->id,
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
            'index' => Pages\ListServiceCases::route('/'),
            'create' => Pages\CreateServiceCase::route('/create'),
            'edit' => Pages\EditServiceCase::route('/{record}/edit'),
        ];
    }
}
