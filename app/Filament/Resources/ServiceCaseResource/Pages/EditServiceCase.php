<?php

namespace App\Filament\Resources\ServiceCaseResource\Pages;

use App\Filament\Resources\RepairOrderResource;
use App\Filament\Resources\ServiceCaseResource;
use App\Models\RepairOrder;
use App\Models\ServiceCase;
use App\Support\Service\ServiceAccess;
use App\Support\Service\ServiceCaseClassificationService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditServiceCase extends EditRecord
{
    use \App\Support\Service\Concerns\HasServiceCaseDirectAttentionHeaderActions;

    protected static string $resource = ServiceCaseResource::class;

    protected ?string $oldStatus = null;

    protected mixed $oldAssignedEmployeeId = null;

    protected mixed $uploadedAttachments = [];

    /*
     * BEXIA_ATC_RECEPTION_THREE_LAYERS_V5_83_4C4C5B2
     *
     * Contexto para mostrar:
     * 1. dato originalmente reportado
     * 2. dato observado en pickup
     * 3. dato a confirmar fisicamente
     */
    protected function pickupReceptionContext(): array
    {
        $metadata =
            is_array(
                $this->record->metadata
            )
                ? $this->record->metadata
                : [];

        $pre =
            (array) (
                $metadata[
                    'repair_pre_reception'
                ]
                ?? []
            );

        $pickupOrder =
            (array) (
                $metadata[
                    'pickup_order'
                ]
                ?? []
            );

        $snapshot =
            (array) (
                $pickupOrder[
                    'reported_snapshot'
                ]
                ?? []
            );

        $pickup =
            (array) (
                $pickupOrder[
                    'pickup'
                ]
                ?? []
            );

        $token =
            trim(
                (string) (
                    $pickupOrder[
                        'token'
                    ]
                    ?? ''
                )
            );

        $signatureId =
            (int) (
                $pickup[
                    'signature_attachment_id'
                ]
                ?? 0
            );

        $signature = null;
        $signatureOnDisk = false;

        if ($signatureId > 0) {
            $signature =
                \Illuminate\Support\Facades\DB::
                    table(
                        'service_attachments'
                    )
                    ->where(
                        'id',
                        $signatureId
                    )
                    ->where(
                        'service_case_id',
                        $this->record
                            ->getKey()
                    )
                    ->where(
                        'stage',
                        'pickup'
                    )
                    ->whereNull(
                        'repair_order_id'
                    )
                    ->first();

            if (
                $signature
                && filled(
                    $signature->file_path
                    ?? null
                )
            ) {
                $signatureOnDisk =
                    \Illuminate\Support\Facades\Storage::
                        disk('public')
                        ->exists(
                            (string)
                                $signature
                                    ->file_path
                        );
            }
        }

        $pickupCompleted =
            (string) (
                $pickupOrder[
                    'status'
                ]
                ?? ''
            ) === 'completed';

        $reuseSignature =
            $pickupCompleted
            && (
                (string) (
                    $pre[
                        'arrival_method'
                    ]
                    ?? ''
                ) === 'recoleccion_chofer'
            )
            && $signature
            && $signatureOnDisk
            && filled(
                $pickup[
                    'customer_signed_at'
                ]
                ?? null
            );

        return [
            'has_completed_pickup' =>
                $pickupCompleted,

            'reuse_pickup_signature' =>
                (bool) $reuseSignature,

            'arrival_method' =>
                $pre[
                    'arrival_method'
                ]
                ?? null,

            'reported_product_name' =>
                $snapshot[
                    'product_name'
                ]
                ?? $this->record
                    ->product_name,

            'reported_serial_number' =>
                $snapshot[
                    'serial_number'
                ]
                ?? $this->record
                    ->serial_number,

            'reported_lot_number' =>
                $this->record
                    ->lot_number,

            'driver_name' =>
                $pickup[
                    'driver_name'
                ]
                ?? null,

            'delivered_by' =>
                $pickup[
                    'delivered_by'
                ]
                ?? null,

            'pickup_location' =>
                $pickup[
                    'pickup_location'
                ]
                ?? null,

            'observed_product_name' =>
                $pickup[
                    'observed_product_name'
                ]
                ?? null,

            'observed_serial_number' =>
                $pickup[
                    'observed_serial_number'
                ]
                ?? null,

            'product_differs' =>
                (bool) (
                    $pickup[
                        'product_differs'
                    ]
                    ?? false
                ),

            'serial_differs' =>
                (bool) (
                    $pickup[
                        'serial_differs'
                    ]
                    ?? false
                ),

            'physical_condition' =>
                $pickup[
                    'physical_condition'
                ]
                ?? null,

            'physical_condition_label' =>
                $pickup[
                    'physical_condition_label'
                ]
                ?? null,

            'accessories' =>
                (array) (
                    $pickup[
                        'accessories'
                    ]
                    ?? []
                ),

            'accessories_other' =>
                $pickup[
                    'accessories_other'
                ]
                ?? null,

            'pickup_notes' =>
                $pickup[
                    'notes'
                ]
                ?? null,

            'evidence_attachment_ids' =>
                array_values(
                    array_map(
                        'intval',
                        (array) (
                            $pickup[
                                'evidence_attachment_ids'
                            ]
                            ?? []
                        )
                    )
                ),

            'signature_attachment_id' =>
                $signatureId > 0
                    ? $signatureId
                    : null,

            'customer_signed_at' =>
                $pickup[
                    'customer_signed_at'
                ]
                ?? null,

            'pickup_completed_at' =>
                $pickupOrder[
                    'completed_at'
                ]
                ?? null,

            'token' =>
                $token,
        ];
    }

    protected function hasReusablePickupCustomerSignature(): bool
    {
        return (bool) (
            $this
                ->pickupReceptionContext()[
                    'reuse_pickup_signature'
                ]
            ?? false
        );
    }

    protected function receptionReportedSummaryHtml():
        \Illuminate\Support\HtmlString
    {
        $context =
            $this
                ->pickupReceptionContext();

        $product =
            e(
                (string) (
                    $context[
                        'reported_product_name'
                    ]
                    ?? 'Sin producto'
                )
            );

        $serial =
            filled(
                $context[
                    'reported_serial_number'
                ]
                ?? null
            )
                ? e(
                    (string) $context[
                        'reported_serial_number'
                    ]
                )
                : 'Sin serie';

        $lot =
            filled(
                $context[
                    'reported_lot_number'
                ]
                ?? null
            )
                ? e(
                    (string) $context[
                        'reported_lot_number'
                    ]
                )
                : 'Sin lote';

        return new
            \Illuminate\Support\HtmlString(
                '<div style="line-height:1.65">'
                . '<strong>Producto / modelo:</strong> '
                . $product
                . '<br>'
                . '<strong>Serie:</strong> '
                . $serial
                . '<br>'
                . '<strong>Lote:</strong> '
                . $lot
                . '<br>'
                . '<span style="color:#6b7280;font-size:12px">'
                . 'Estos datos se conservarán para trazabilidad.'
                . '</span>'
                . '</div>'
            );
    }

    protected function receptionPickupSummaryHtml():
        \Illuminate\Support\HtmlString
    {
        $context =
            $this
                ->pickupReceptionContext();

        if (
            ! (
                $context[
                    'has_completed_pickup'
                ]
                ?? false
            )
        ) {
            return new
                \Illuminate\Support\HtmlString(
                    '<span style="color:#6b7280">'
                    . 'No existe una recolección pública completada.'
                    . '</span>'
                );
        }

        $accessoryOptions =
            \App\Support\Service\ServiceRepairReceptionChecklistService::
                ACCESSORIES;

        $accessories = [];

        foreach (
            (array) (
                $context[
                    'accessories'
                ]
                ?? []
            )
            as $value
        ) {
            $accessories[] =
                $accessoryOptions[
                    $value
                ]
                ?? $value;
        }

        if (
            in_array(
                'otro',
                (array) (
                    $context[
                        'accessories'
                    ]
                    ?? []
                ),
                true
            )
            && filled(
                $context[
                    'accessories_other'
                ]
                ?? null
            )
        ) {
            $accessories[] =
                'Otro: '
                . $context[
                    'accessories_other'
                ];
        }

        $productBadge =
            ($context['product_differs'] ?? false)
                ? '<span style="color:#b45309;font-weight:700">Diferente al ticket</span>'
                : '<span style="color:#15803d;font-weight:700">Coincide</span>';

        $serialBadge =
            ($context['serial_differs'] ?? false)
                ? '<span style="color:#b45309;font-weight:700">Diferente al ticket</span>'
                : '<span style="color:#15803d;font-weight:700">Coincide</span>';

        $links = [];

        $token =
            trim(
                (string) (
                    $context[
                        'token'
                    ]
                    ?? ''
                )
            );

        if ($token !== '') {
            foreach (
                (array) (
                    $context[
                        'evidence_attachment_ids'
                    ]
                    ?? []
                )
                as $index => $attachmentId
            ) {
                $url =
                    route(
                        'public.service.pickup.evidence',
                        [
                            'token' =>
                                $token,

                            'attachment' =>
                                $attachmentId,
                        ]
                    );

                $links[] =
                    '<a href="'
                    . e($url)
                    . '" target="_blank" rel="noopener">'
                    . 'Ver foto '
                    . (
                        $index + 1
                    )
                    . '</a>';
            }

            $signatureId =
                (int) (
                    $context[
                        'signature_attachment_id'
                    ]
                    ?? 0
                );

            if ($signatureId > 0) {
                $url =
                    route(
                        'public.service.pickup.evidence',
                        [
                            'token' =>
                                $token,

                            'attachment' =>
                                $signatureId,
                        ]
                    );

                $links[] =
                    '<a href="'
                    . e($url)
                    . '" target="_blank" rel="noopener">'
                    . 'Ver firma del cliente'
                    . '</a>';
            }
        }

        $html =
            '<div style="line-height:1.7">'
            . '<strong>Chofer:</strong> '
            . e(
                (string) (
                    $context[
                        'driver_name'
                    ]
                    ?? '-'
                )
            )
            . '<br>'
            . '<strong>Persona que entregó al chofer:</strong> '
            . e(
                (string) (
                    $context[
                        'delivered_by'
                    ]
                    ?? '-'
                )
            )
            . '<br>'
            . '<strong>Lugar:</strong> '
            . e(
                (string) (
                    $context[
                        'pickup_location'
                    ]
                    ?? '-'
                )
            )
            . '<hr style="margin:8px 0;border:0;border-top:1px solid #e5e7eb">'
            . '<strong>Producto / modelo observado:</strong> '
            . e(
                (string) (
                    $context[
                        'observed_product_name'
                    ]
                    ?? '-'
                )
            )
            . ' · '
            . $productBadge
            . '<br>'
            . '<strong>Serie observada:</strong> '
            . e(
                (string) (
                    $context[
                        'observed_serial_number'
                    ]
                    ?? 'Sin serie'
                )
            )
            . ' · '
            . $serialBadge
            . '<br>'
            . '<strong>Condición:</strong> '
            . e(
                (string) (
                    $context[
                        'physical_condition_label'
                    ]
                    ?? '-'
                )
            )
            . '<br>'
            . '<strong>Accesorios:</strong> '
            . e(
                implode(
                    ', ',
                    $accessories
                )
                ?: 'Sin dato'
            )
            . '<br>'
            . '<strong>Observaciones:</strong> '
            . e(
                (string) (
                    $context[
                        'pickup_notes'
                    ]
                    ?? 'Sin observaciones'
                )
            );

        if ($links !== []) {
            $html .=
                '<br><strong>Evidencia:</strong> '
                . implode(
                    ' · ',
                    $links
                );
        }

        if (
            $context[
                'reuse_pickup_signature'
            ]
            ?? false
        ) {
            $html .=
                '<div style="margin-top:10px;padding:9px 11px;'
                . 'background:#ecfdf5;border:1px solid #bbf7d0;'
                . 'border-radius:8px;color:#166534">'
                . '<strong>Firma del cliente ya capturada.</strong> '
                . 'No se solicitará una segunda firma.'
                . '</div>';
        }

        $html .= '</div>';

        return new
            \Illuminate\Support\HtmlString(
                $html
            );
    }

    /*
     * BEXIA_ATC_DELIVERY_FROM_TICKET_V5_83_4C5G12
     *
     * Después de que Reparaciones prepara:
     * - la CxC;
     * - la política de cobro;
     * - ready_for_delivery;
     *
     * el ticket ATC se convierte en el centro operativo
     * de la entrega física.
     *
     * IMPORTANTE:
     * ATC NO registra pagos desde estas acciones.
     */
    protected function linkedRepairForFinalDelivery():
        ?\App\Models\RepairOrder
    {
        if (! $this->record) {
            return null;
        }

        return $this->record
            ->repairOrders()
            ->orderByDesc('id')
            ->first();
    }

    protected function ticketPrepareRepairExitDocumentAction():
        \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make(
            'ticket_prepare_repair_exit_document'
        )
            ->label(
                'Generar salida de equipo'
            )
            ->icon(
                'heroicon-o-document-check'
            )
            ->color('warning')
            ->modalHeading(
                'Generar documento de salida'
            )
            ->modalDescription(
                'Autoriza la salida física del equipo bajo custodia. No genera movimiento de inventario.'
            )
            ->modalSubmitActionLabel(
                'Autorizar salida'
            )
            ->visible(
                function (): bool {
                    $repair =
                        $this->
                            linkedRepairForFinalDelivery();

                    if (! $repair) {
                        return false;
                    }

                    return
                        \App\Support\Service\ServiceAccess::
                            canPrepareRepairExitDocument(
                                $repair
                            )
                        && ! \App\Support\Service\ServiceAccess::
                            hasAuthorizedRepairExitDocument(
                                $repair
                            );
                }
            )
            ->form([
                \Filament\Forms\Components\Select::
                    make(
                        'origin_exit_warehouse_id'
                    )
                    ->label(
                        'Ubicación de salida'
                    )
                    ->options(
                        function (): array {
                            $repair =
                                $this->
                                    linkedRepairForFinalDelivery();

                            if (! $repair) {
                                return [];
                            }

                            return
                                \App\Models\ExitWarehouse::
                                    query()
                                    ->where(
                                        'company_id',
                                        $repair->
                                            company_id
                                    )
                                    ->where(
                                        'is_active',
                                        true
                                    )
                                    ->whereIn(
                                        'usage_type',
                                        [
                                            'envio',
                                            'ambos',
                                        ]
                                    )
                                    ->orderBy(
                                        'sort_order'
                                    )
                                    ->orderBy(
                                        'name'
                                    )
                                    ->pluck(
                                        'name',
                                        'id'
                                    )
                                    ->all();
                        }
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->helperText(
                        'Selecciona la ubicación configurada para la empresa. Si no aparece, usa la ubicación manual.'
                    ),

                \Filament\Forms\Components\TextInput::
                    make(
                        'origin_location_label'
                    )
                    ->label(
                        'Ubicación manual'
                    )
                    ->placeholder(
                        'Sólo si no aparece en el catálogo'
                    )
                    ->maxLength(255),

                \Filament\Forms\Components\Textarea::
                    make(
                        'exit_document_notes'
                    )
                    ->label(
                        'Observaciones de salida'
                    )
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(
                function (array $data): void {
                    $repair =
                        $this->
                            linkedRepairForFinalDelivery();

                    if (! $repair) {
                        throw
                            \Illuminate\Validation\ValidationException::
                                withMessages([
                                    'repair' =>
                                        'No se encontró la reparación vinculada.',
                                ]);
                    }

                    $repair =
                        app(
                            \App\Support\Service\ServiceRepairExitDocumentService::
                                class
                        )->authorize(
                            $repair,
                            $data
                        );

                    $document =
                        \App\Support\Service\ServiceAccess::
                            repairExitDocument(
                                $repair
                            );

                    $this->record->refresh();

                    \Filament\Notifications\Notification::
                        make()
                        ->title(
                            'Salida autorizada'
                        )
                        ->body(
                            'Se generó '
                            . (
                                $document[
                                    'folio'
                                ]
                                ?? 'el documento SAL-ATC'
                            )
                            . '. Puede imprimirse desde este ticket.'
                        )
                        ->success()
                        ->send();
                }
            );
    }

    protected function ticketPrintRepairExitDocumentAction():
        \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make(
            'ticket_print_repair_exit_document'
        )
            ->label(
                'Imprimir salida'
            )
            ->icon(
                'heroicon-o-printer'
            )
            ->color('gray')
            ->visible(
                function (): bool {
                    $repair =
                        $this->
                            linkedRepairForFinalDelivery();

                    return
                        $repair
                        && \App\Support\Service\ServiceAccess::
                            hasAuthorizedRepairExitDocument(
                                $repair
                            );
                }
            )
            ->url(
                function (): string {
                    $repair =
                        $this->
                            linkedRepairForFinalDelivery();

                    if (! $repair) {
                        return
                            \App\Filament\Resources\ServiceCaseResource::
                                getUrl(
                                    'edit',
                                    [
                                        'record' =>
                                            $this->record,
                                    ]
                                );
                    }

                    return route(
                        'service.repair-orders.exit-document',
                        [
                            'tenant' =>
                                $repair->company_id,

                            'record' =>
                                $repair->getKey(),
                        ]
                    );
                }
            )
            ->openUrlInNewTab();
    }

    /*
     * BEXIA_ATC_PUBLIC_REPAIR_DELIVERY_ACTION_V5_83_4C5G13A
     *
     * La captura final ya NO vive en un modal Filament.
     *
     * El Encargado/Supervisor:
     * 1. pulsa Entregar al cliente;
     * 2. abre nueva pestaña;
     * 3. launcher interno valida auth/signed/tenant/actor;
     * 4. genera/reutiliza liga publica;
     * 5. chofer captura evidencia/firma sin usuario Bexia.
     */
    protected function ticketDeliverToCustomerAction():
        \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make(
            'ticket_deliver_to_customer'
        )
            ->label(
                'Entregar al cliente'
            )
            ->icon(
                'heroicon-o-truck'
            )
            ->color('success')
            ->visible(
                function (): bool {
                    $repair =
                        $this->
                            linkedRepairForFinalDelivery();

                    if (! $repair) {
                        return false;
                    }

                    $alreadyDelivered =
                        filled(
                            $repair->
                                delivered_at
                        )
                        || in_array(
                            (string) (
                                $repair->
                                    workflow_stage
                                ?? ''
                            ),
                            [
                                'delivered',
                                'entregado',
                            ],
                            true
                        );

                    if ($alreadyDelivered) {
                        return false;
                    }

                    return
                        \App\Support\Service\ServiceAccess::
                            hasAuthorizedRepairExitDocument(
                                $repair
                            )
                        && \App\Support\Service\ServiceAccess::
                            can(
                                'service.repairs.delivery'
                            )
                        && \App\Support\Service\ServiceAccess::
                            hasServiceRole([
                                'Servicio - Encargado de Técnicos',
                                'Servicio - Supervisor',
                            ]);
                }
            )
            ->disabled(
                function (): bool {
                    $repair =
                        $this->
                            linkedRepairForFinalDelivery();

                    return
                        ! $repair
                        || ! \App\Support\Service\ServiceAccess::
                            canDeliverRepair(
                                $repair
                            );
                }
            )
            ->tooltip(
                function (): string {
                    $repair =
                        $this->
                            linkedRepairForFinalDelivery();

                    if (
                        $repair
                        && \App\Support\Service\ServiceAccess::
                            canDeliverRepair(
                                $repair
                            )
                    ) {
                        return
                            'Abrir página de entrega para el chofer.';
                    }

                    return
                        'La entrega todavía no cumple las condiciones requeridas.';
                }
            )
            ->url(
                function () {
                    $repair =
                        $this->
                            linkedRepairForFinalDelivery();

                    if (
                        ! $repair
                        || ! \App\Support\Service\ServiceAccess::
                            canDeliverRepair(
                                $repair
                            )
                    ) {
                        return null;
                    }

                    return
                        \Illuminate\Support\Facades\URL::
                            temporarySignedRoute(
                                'service.repair-delivery.launch',
                                now()->addMinutes(10),
                                [
                                    'tenant' =>
                                        (int)
                                            $repair->
                                                company_id,

                                    'repairOrder' =>
                                        (int)
                                            $repair->
                                                getKey(),

                                    'actor' =>
                                        (int)
                                            auth()->id(),
                                ]
                            );
                }
            )
            ->openUrlInNewTab();
    }


    protected function getHeaderActions(): array
    {
        return [
            ...$this->serviceCaseDirectAttentionHeaderActions(),

            /*
             * BEXIA_ATC_CONTINUE_LINKED_REPAIR_V5_83_4C5G8
             *
             * Una vez creada la orden tecnica, el trabajo
             * operativo continua dentro de Reparaciones.
             *
             * Este boton evita dejar al usuario detenido
             * dentro del ticket ATC.
             */
            Action::make(
                'continue_linked_repair'
            )
                ->label(
                    'Continuar reparación'
                )
                ->icon(
                    'heroicon-o-wrench-screwdriver'
                )
                ->color('primary')
                ->visible(
                    function (): bool {
                        /*
                         * BEXIA_ATC_HIDE_CONTINUE_AFTER_TECH_COMPLETED_V5_83_4C5G9D
                         *
                         * "Continuar reparación" sólo pertenece
                         * a la etapa de trabajo técnico.
                         *
                         * Cuando technical_work.status=completed
                         * la reparación ya fue realizada y este
                         * acceso deja de mostrarse en el ticket.
                         *
                         * Las etapas posteriores continúan dentro
                         * de RepairOrder con sus acciones propias.
                         */
                        if (
                            (string) (
                                $this->record->
                                    attention_route
                                ?? ''
                            ) !== 'repair'
                        ) {
                            return false;
                        }

                        $repair =
                            $this->record
                                ->repairOrders()
                                ->orderByDesc('id')
                                ->first();

                        if (! $repair) {
                            return false;
                        }

                        if (
                            ! RepairOrderResource::
                                canView(
                                    $repair
                                )
                        ) {
                            return false;
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
                            $metadata instanceof
                                \Illuminate\Contracts\Support\Arrayable
                        ) {
                            $metadata =
                                $metadata->toArray();
                        }

                        if (
                            is_object(
                                $metadata
                            )
                        ) {
                            $metadata =
                                (array) $metadata;
                        }

                        if (
                            ! is_array(
                                $metadata
                            )
                        ) {
                            $metadata = [];
                        }

                        if (
                            data_get(
                                $metadata,
                                'technical_work.status'
                            ) === 'completed'
                        ) {
                            return false;
                        }

                        return true;
                    }
                )
                ->url(
                    function (): ?string {
                        $repair =
                            $this->record
                                ->repairOrders()
                                ->orderByDesc('id')
                                ->first();

                        if (
                            ! $repair
                            || ! RepairOrderResource::
                                canView(
                                    $repair
                                )
                        ) {
                            return null;
                        }

                        return RepairOrderResource::
                            getUrl(
                                'edit',
                                [
                                    'record' =>
                                        $repair,
                                ]
                            );
                    }
                ),

            /*
             * BEXIA_ATC_PICKUP_ORDER_ACTION_V5_83_4C4B1
             *
             * Orden operativa de recoleccion.
             *
             * No depende del nombre interno de la accion
             * Recibir equipo.
             */
            Action::make(
                'pickup_order'
            )
                ->label(
                    'Orden de recolección'
                )
                ->icon(
                    'heroicon-o-qr-code'
                )
                ->color('info')
                ->visible(
                    function (): bool {
                        if (
                            ! \App\Support\Service\ServiceAccess::
                                canEditServiceCaseMasterData()
                        ) {
                            return false;
                        }

                        if (
                            (string) (
                                $this->record
                                    ->attention_route
                                ?? ''
                            ) !== 'repair'
                        ) {
                            return false;
                        }

                        if (
                            (string) (
                                $this->record
                                    ->status
                                ?? ''
                            ) !== 'esperando_producto'
                        ) {
                            return false;
                        }

                        $metadata =
                            is_array(
                                $this->record
                                    ->metadata
                            )
                                ? $this->record
                                    ->metadata
                                : [];

                        /*
                         * BEXIA_ATC_HIDE_COMPLETED_PICKUP_ORDER_V5_83_4C4C5C
                         *
                         * Una vez que el chofer confirma la
                         * recoleccion, esta etapa ya termino.
                         *
                         * Se conserva la orden, token, PDF,
                         * QR y evidencias, pero la accion deja
                         * de mostrarse en el ticket.
                         */
                        $pickupOrder =
                            (array) (
                                $metadata[
                                    'pickup_order'
                                ]
                                ?? []
                            );

                        if (
                            (string) (
                                $pickupOrder[
                                    'status'
                                ]
                                ?? ''
                            ) === 'completed'
                        ) {
                            return false;
                        }

                        $pre =
                            (array) (
                                $metadata[
                                    'repair_pre_reception'
                                ]
                                ?? []
                            );

                        return (
                            (string) (
                                $pre[
                                    'arrival_method'
                                ]
                                ?? ''
                            )
                            === 'recoleccion_chofer'
                        );
                    }
                )
                /*
                 * BEXIA_ATC_PICKUP_NEW_TAB_V5_83_4C4B5
                 *
                 * La URL interna no genera la Orden
                 * durante el render.
                 *
                 * Al hacer clic se abre otra pestaña;
                 * esa ruta genera/reutiliza el token
                 * y redirige a la Orden publica.
                 */
                ->url(
                    function (): string {
                        return
                            \Illuminate\Support\Facades\URL::
                                temporarySignedRoute(
                                    'service.pickup.launch',
                                    now()->addMinutes(
                                        10
                                    ),
                                    [
                                        'tenant' =>
                                            (int)
                                                $this
                                                    ->record
                                                    ->company_id,

                                        'serviceCase' =>
                                            (int)
                                                $this
                                                    ->record
                                                    ->getKey(),

                                        'actor' =>
                                            (int)
                                                auth()
                                                    ->id(),
                                    ]
                                );
                    }
                )
                ->openUrlInNewTab(),

            /*
             * BEXIA_ATC_RECEIVE_EQUIPMENT_ACTION_V5_83_4C3
             *
             * Se muestra únicamente cuando el equipo está
             * pendiente de recepción física y todavía no
             * existe RepairOrder.
             */
            Action::make(
                'receive_repair_equipment'
            )
                ->label('Recibir equipo')
                ->icon(
                    'heroicon-o-arrow-down-tray'
                )
                ->color('success')
                ->modalHeading(
                    'Registrar recepción física'
                )
                ->modalDescription(
                    'Confirma el equipo recibido, su condición y los accesorios entregados. Al confirmar se creará la orden técnica.'
                )
                ->modalSubmitActionLabel(
                    'Recibir equipo y crear orden'
                )
                ->form([
                    Forms\Components\Section::make(
                        '1. Reportado en ticket'
                    )
                        ->description(
                            'Referencia original. Se conservará aunque la recepción confirme datos distintos.'
                        )
                        ->schema([
                            Forms\Components\Placeholder::make(
                                'reception_reported_summary'
                            )
                                ->hiddenLabel()
                                ->content(
                                    fn ():
                                        \Illuminate\Support\HtmlString =>
                                            $this
                                                ->receptionReportedSummaryHtml()
                                ),
                        ])
                        ->columnSpanFull(),

                    Forms\Components\Section::make(
                        '2. Observado en recolección'
                    )
                        ->description(
                            'Datos capturados por el chofer al recoger físicamente el equipo.'
                        )
                        ->schema([
                            Forms\Components\Placeholder::make(
                                'reception_pickup_summary'
                            )
                                ->hiddenLabel()
                                ->content(
                                    fn ():
                                        \Illuminate\Support\HtmlString =>
                                            $this
                                                ->receptionPickupSummaryHtml()
                                ),
                        ])
                        ->visible(
                            fn (): bool =>
                                (bool) (
                                    $this
                                        ->pickupReceptionContext()[
                                            'has_completed_pickup'
                                        ]
                                    ?? false
                                )
                        )
                        ->columnSpanFull(),

                    Forms\Components\Section::make(
                        '3. Confirmado físicamente en recepción'
                    )
                        ->description(
                            'Estos serán los datos operativos definitivos después de revisar físicamente el equipo.'
                        )
                        ->schema([
                            Forms\Components\TextInput::make(
                                'reception_product_name'
                            )
                                ->label(
                                    'Producto / modelo confirmado'
                                )
                                ->default(
                                    function (): mixed {
                                        $context =
                                            $this
                                                ->pickupReceptionContext();

                                        return filled(
                                            $context[
                                                'observed_product_name'
                                            ]
                                            ?? null
                                        )
                                            ? $context[
                                                'observed_product_name'
                                            ]
                                            : $this
                                                ->record
                                                ->product_name;
                                    }
                                )
                                ->required()
                                ->maxLength(255),

                            Forms\Components\TextInput::make(
                                'reception_serial_number'
                            )
                                ->label(
                                    'Número de serie confirmado'
                                )
                                ->default(
                                    function (): mixed {
                                        $context =
                                            $this
                                                ->pickupReceptionContext();

                                        return filled(
                                            $context[
                                                'observed_serial_number'
                                            ]
                                            ?? null
                                        )
                                            ? $context[
                                                'observed_serial_number'
                                            ]
                                            : $this
                                                ->record
                                                ->serial_number;
                                    }
                                )
                                ->maxLength(255),

                            Forms\Components\TextInput::make(
                                'reception_lot_number'
                            )
                                ->label(
                                    'Lote confirmado'
                                )
                                ->default(
                                    fn (): mixed =>
                                        $this
                                            ->record
                                            ->lot_number
                                )
                                ->maxLength(255),

                            Forms\Components\TextInput::make(
                                'reception_received_from'
                            )
                                ->label(
                                    'Persona que entrega físicamente'
                                )
                                ->default(
                                    function (): mixed {
                                        $context =
                                            $this
                                                ->pickupReceptionContext();

                                        return (
                                            $context[
                                                'has_completed_pickup'
                                            ]
                                            ?? false
                                        )
                                            ? (
                                                $context[
                                                    'driver_name'
                                                ]
                                                ?? null
                                            )
                                            : null;
                                    }
                                )
                                ->required()
                                ->maxLength(255),

                            Forms\Components\TextInput::make(
                                'reception_received_location'
                            )
                                ->label(
                                    'Lugar donde se recibe'
                                )
                                ->placeholder(
                                    'Ej. CEDIS, taller, sucursal...'
                                )
                                ->maxLength(255),

                            Forms\Components\TextInput::make(
                                'reception_received_by_name'
                            )
                                ->label(
                                    'Nombre de quien recibe'
                                )
                                ->helperText(
                                    'Opcional. El usuario Bexia queda registrado automáticamente.'
                                )
                                ->maxLength(255),

                            Forms\Components\Placeholder::make(
                                'reception_reused_signature_notice'
                            )
                                ->label(
                                    'Firma del cliente'
                                )
                                ->content(
                                    function (): string {
                                        $context =
                                            $this
                                                ->pickupReceptionContext();

                                        return
                                            'Firma capturada durante la recolección'
                                            . (
                                                filled(
                                                    $context[
                                                        'customer_signed_at'
                                                    ]
                                                    ?? null
                                                )
                                                    ? ' el '
                                                        . $context[
                                                            'customer_signed_at'
                                                        ]
                                                    : ''
                                            )
                                            . '. Se reutilizará como evidencia; no se solicita una segunda firma.';
                                    }
                                )
                                ->visible(
                                    fn (): bool =>
                                        $this
                                            ->hasReusablePickupCustomerSignature()
                                )
                                ->columnSpanFull(),

                            Forms\Components\ViewField::make(
                                'reception_customer_signature'
                            )
                                ->label(
                                    'Firma del cliente'
                                )
                                ->view(
                                    'filament.forms.components.service-reception-signature'
                                )
                                ->visible(
                                    fn (): bool =>
                                        ! $this
                                            ->hasReusablePickupCustomerSignature()
                                )
                                ->required(
                                    fn (): bool =>
                                        ! $this
                                            ->hasReusablePickupCustomerSignature()
                                )
                                ->dehydrated()
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),

                    Forms\Components\Section::make(
                        '4. Checklist de recepción'
                    )
                        ->description(
                            'Verifica el estado del equipo exactamente como entra físicamente a Bexia.'
                        )
                        ->schema([
                            Forms\Components\Select::make(
                                'reception_physical_condition'
                            )
                                ->label(
                                    'Condición física'
                                )
                                ->options(
                                    \App\Support\Service\ServiceRepairReceptionChecklistService::
                                        PHYSICAL_CONDITIONS
                                )
                                ->default(
                                    function (): mixed {
                                        $value =
                                            $this
                                                ->pickupReceptionContext()[
                                                    'physical_condition'
                                                ]
                                            ?? null;

                                        return array_key_exists(
                                            (string) $value,
                                            \App\Support\Service\ServiceRepairReceptionChecklistService::
                                                PHYSICAL_CONDITIONS
                                        )
                                            ? $value
                                            : null;
                                    }
                                )
                                ->native(false)
                                ->required(),

                            Forms\Components\Select::make(
                                'reception_power_status'
                            )
                                ->label(
                                    'Prueba de encendido'
                                )
                                ->options(
                                    \App\Support\Service\ServiceRepairReceptionChecklistService::
                                        POWER_STATUSES
                                )
                                ->native(false)
                                ->required(),

                            Forms\Components\CheckboxList::make(
                                'reception_accessories'
                            )
                                ->label(
                                    'Accesorios recibidos'
                                )
                                ->options(
                                    \App\Support\Service\ServiceRepairReceptionChecklistService::
                                        ACCESSORIES
                                )
                                ->default(
                                    function (): array {
                                        $allowed =
                                            array_keys(
                                                \App\Support\Service\ServiceRepairReceptionChecklistService::
                                                    ACCESSORIES
                                            );

                                        return array_values(
                                            array_intersect(
                                                (array) (
                                                    $this
                                                        ->pickupReceptionContext()[
                                                            'accessories'
                                                        ]
                                                    ?? []
                                                ),
                                                $allowed
                                            )
                                        );
                                    }
                                )
                                ->columns(2)
                                ->live()
                                ->required(),

                            Forms\Components\TextInput::make(
                                'reception_accessories_other'
                            )
                                ->label(
                                    'Otro accesorio'
                                )
                                ->default(
                                    fn (): mixed =>
                                        $this
                                            ->pickupReceptionContext()[
                                                'accessories_other'
                                            ]
                                        ?? null
                                )
                                ->visible(
                                    fn (Get $get): bool =>
                                        in_array(
                                            'otro',
                                            (array) $get(
                                                'reception_accessories'
                                            ),
                                            true
                                        )
                                )
                                ->required(
                                    fn (Get $get): bool =>
                                        in_array(
                                            'otro',
                                            (array) $get(
                                                'reception_accessories'
                                            ),
                                            true
                                        )
                                )
                                ->maxLength(500),

                            Forms\Components\Textarea::make(
                                'reception_notes'
                            )
                                ->label(
                                    'Observaciones de recepción'
                                )
                                ->default(
                                    fn (): mixed =>
                                        $this
                                            ->pickupReceptionContext()[
                                                'pickup_notes'
                                            ]
                                        ?? null
                                )
                                ->rows(4)
                                ->required(),

                            Forms\Components\CheckboxList::make(
                                'reception_confirmations'
                            )
                                ->label(
                                    'Confirmaciones de recepción'
                                )
                                ->options(
                                    \App\Support\Service\ServiceRepairReceptionChecklistService::
                                        CONFIRMATIONS
                                )
                                ->helperText(
                                    'Confirma todos los puntos antes de crear la orden técnica.'
                                )
                                ->columns(1)
                                ->required(),

                            Forms\Components\FileUpload::make(
                                'reception_files'
                            )
                                ->label(
                                    'Imágenes / evidencias de recepción'
                                )
                                ->helperText(
                                    'Adjunta por lo menos una fotografía nueva al momento de la recepción física.'
                                )
                                ->multiple()
                                ->required()
                                ->acceptedFileTypes([
                                    'image/*',
                                    'application/pdf',
                                ])
                                ->disk('public')
                                ->directory(
                                    'service-attachments/reception'
                                )
                                ->downloadable()
                                ->openable()
                                ->previewable()
                                ->imagePreviewHeight('160')
                                ->maxSize(20480)
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->visible(
                    fn (): bool =>
                        ServiceAccess::
                            canEditServiceCaseMasterData()
                        && (
                            (string) (
                                $this->record
                                    ->attention_route
                                ?? ''
                            ) === 'repair'
                        )
                        && (
                            (string) (
                                $this->record
                                    ->status
                                ?? ''
                            ) ===
                                'esperando_producto'
                        )
                        && ! $this->record
                            ->repairOrders()
                            ->exists()
                )
                ->action(
                    function (
                        array $data
                    ): void {
                        $repair = app(
                            ServiceCaseClassificationService::class
                        )->receiveRepairEquipment(
                            $this->record,
                            $data
                        );

                        $this->record
                            ->refresh();

                        Notification::make()
                            ->title(
                                'Equipo recibido'
                            )
                            ->body(
                                'Se creó la orden técnica '
                                . $repair->folio
                                . '. El caso quedó listo para diagnóstico.'
                            )
                            ->success()
                            ->send();

                        /*
                         * BEXIA_ATC_RECEPTION_CONTINUE_TO_REPAIR_V5_83_4C5G8
                         *
                         * Al terminar la recepción física ya existe
                         * la RepairOrder. El siguiente paso del flujo
                         * sucede dentro de Reparaciones.
                         */
                        $this->redirect(
                            RepairOrderResource::getUrl(
                                'edit',
                                [
                                    'record' =>
                                        $repair,
                                ]
                            )
                        );
                    }
                ),

            /*
             * BEXIA_ATC_PREPARE_RECEPTION_MODAL_V5_83_4C1
             *
             * Reparación:
             * este modal sólo prepara la recepción.
             * La orden técnica se crea después,
             * cuando el equipo se recibe físicamente.
             */
            Action::make('classify_attention')
                ->label('Atender ticket')
                ->icon('heroicon-o-arrows-right-left')
                ->color('warning')
                ->modalHeading(
                    '¿Cómo se atenderá este ticket?'
                )
                ->modalDescription(
                    'Selecciona Respuesta / gestión o Revisión / reparación de equipo y asigna al responsable.'
                )
                ->modalSubmitActionLabel(
                    'Confirmar atención'
                )
                ->form([
                    Forms\Components\Radio::make(
                        'attention_route'
                    )
                        ->label('Forma de atención')
                        ->options(
                            ServiceCase::ATTENTION_ROUTES
                        )
                        ->descriptions([
                            'repair' =>
                                'Prepara la recepción del equipo. La orden técnica se creará únicamente cuando se registre la recepción física.',
                            'non_repair' =>
                                'La atención continúa y se resuelve dentro del ticket.',
                        ])
                        ->required()
                        ->live(),

                    Forms\Components\Select::make(
                        'assigned_employee_id'
                    )
                        ->label('Responsable')
                        ->helperText(
                            fn (Get $get): string =>
                                $get('attention_route')
                                    === 'repair'
                                    ? 'Selecciona quién dará seguimiento hasta la recepción del equipo.'
                                    : 'Selecciona quién dará seguimiento al ticket.'
                        )
                        ->options(
                            fn (Get $get): array =>
                                (string) $get(
                                    'attention_route'
                                ) === 'non_repair'
                                    ? ServiceAccess::
                                        managementResponsibleEmployeeOptions()
                                    : ServiceAccess::
                                        technicianEmployeeOptions()
                        )
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->default(
                            fn (): mixed =>
                                $this->record
                                    ->assigned_employee_id
                        )
                        ->visible(
                            fn (Get $get): bool =>
                                filled(
                                    $get('attention_route')
                                )
                        )
                        ->required(
                            fn (Get $get): bool =>
                                in_array(
                                    $get('attention_route'),
                                    [
                                        'repair',
                                        'non_repair',
                                    ],
                                    true
                                )
                        ),

                    /*
                     * PRE-RECEPCION DE EQUIPO
                     */
                    Forms\Components\Select::make(
                        'repair_arrival_method'
                    )
                        ->label(
                            '¿Cómo llegará el equipo?'
                        )
                        ->options(
                            ServiceCase::
                                REPAIR_ARRIVAL_METHODS
                        )
                        ->native(false)
                        ->required(
                            fn (Get $get): bool =>
                                $get('attention_route')
                                    === 'repair'
                        )
                        ->visible(
                            fn (Get $get): bool =>
                                $get('attention_route')
                                    === 'repair'
                        ),

                    Forms\Components\TextInput::make(
                        'repair_reception_place'
                    )
                        ->label('Lugar de recepción')
                        ->placeholder(
                            'Ej. CEDIS, taller, sucursal...'
                        )
                        ->required(
                            fn (Get $get): bool =>
                                $get('attention_route')
                                    === 'repair'
                        )
                        ->visible(
                            fn (Get $get): bool =>
                                $get('attention_route')
                                    === 'repair'
                        ),

                    Forms\Components\DateTimePicker::make(
                        'planned_reception_at'
                    )
                        ->label(
                            'Fecha prevista de recepción'
                        )
                        ->helperText(
                            'Opcional. No es todavía una fecha compromiso de reparación.'
                        )
                        ->visible(
                            fn (Get $get): bool =>
                                $get('attention_route')
                                    === 'repair'
                        ),

                    Forms\Components\Placeholder::make(
                        'repair_pre_reception_notice'
                    )
                        ->label('')
                        ->content(
                            'En este paso NO se crea orden de reparación, NO se marca el equipo como recibido y NO se inicia diagnóstico.'
                        )
                        ->visible(
                            fn (Get $get): bool =>
                                $get('attention_route')
                                    === 'repair'
                        ),

                    /*
                     * RESPUESTA / GESTION
                     */
                    Forms\Components\Select::make(
                        'non_repair_type'
                    )
                        ->label('Tipo de gestión')
                        ->options(
                            ServiceCase::NON_REPAIR_TYPES
                        )
                        ->native(false)
                        ->visible(
                            fn (Get $get): bool =>
                                $get('attention_route')
                                    === 'non_repair'
                        ),

                    Forms\Components\DateTimePicker::make(
                        'non_repair_due_at'
                    )
                        ->label('Fecha compromiso')
                        ->default(
                            fn (): mixed =>
                                $this->record->due_at
                        )
                        ->visible(
                            fn (Get $get): bool =>
                                $get('attention_route')
                                    === 'non_repair'
                        ),

                    /*
                     * COMUN
                     */
                    Forms\Components\Textarea::make(
                        'classification_notes'
                    )
                        ->label(
                            'Indicaciones de atención'
                        )
                        ->helperText(
                            fn (Get $get): string =>
                                $get('attention_route')
                                    === 'repair'
                                    ? 'Agrega instrucciones para coordinar la recepción del equipo.'
                                    : 'Agrega las indicaciones iniciales del caso.'
                        )
                        ->rows(4),

                    Forms\Components\FileUpload::make(
                        'attention_files'
                    )
                        ->label(
                            fn (Get $get): string =>
                                $get('attention_route')
                                    === 'repair'
                                    ? 'Imágenes / evidencias previas a recepción'
                                    : 'Imágenes / evidencias de atención'
                        )
                        ->helperText(
                            fn (Get $get): string =>
                                $get('attention_route')
                                    === 'repair'
                                    ? 'Puedes adjuntar fotografías o documentos disponibles antes de recibir físicamente el equipo.'
                                    : 'Puedes adjuntar fotografías o documentos relacionados con la atención.'
                        )
                        ->multiple()
                        ->acceptedFileTypes([
                            'image/*',
                            'application/pdf',
                        ])
                        ->disk('public')
                        ->directory(
                            'service-attachments/attention'
                        )
                        ->downloadable()
                        ->openable()
                        ->previewable()
                        ->imagePreviewHeight('160')
                        ->maxSize(20480)
                        ->columnSpanFull()
                        ->visible(
                            fn (Get $get): bool =>
                                filled(
                                    $get('attention_route')
                                )
                        ),
                ])
                ->visible(fn (): bool =>
                    ServiceAccess::can(
                        'service.cases.classify'
                    )
                    && blank(
                        $this->record
                            ->attention_route
                    )
                    && ! in_array(
                        (string)
                            $this->record->status,
                        [
                            'entregado',
                            'cerrado',
                            'rechazado',
                            'cancelado',
                        ],
                        true
                    )
                    && ! $this->record
                        ->repairOrders()
                        ->exists()
                )
                ->action(
                    function (array $data): void {
                        $route = (string) (
                            $data[
                                'attention_route'
                            ] ?? ''
                        );

                        app(
                            ServiceCaseClassificationService::class
                        )->classify(
                            $this->record,
                            $data
                        );

                        ServiceAccess::
                            saveUploadedAttachments(
                                companyId:
                                    $this->record
                                        ->company_id,
                                serviceCaseId:
                                    $this->record->id,
                                repairOrderId: null,
                                files:
                                    $data[
                                        'attention_files'
                                    ] ?? null,
                                stage:
                                    $route === 'repair'
                                        ? 'pre_reception'
                                        : 'attention',
                                isCustomerVisible:
                                    false
                            );

                        $this->record->refresh();

                        if ($route === 'repair') {
                            Notification::make()
                                ->title(
                                    'Recepción preparada'
                                )
                                ->body(
                                    'El ticket quedó en espera de recepción. La orden técnica se creará al registrar la recepción física.'
                                )
                                ->success()
                                ->send();

                            $this->redirect(
                                ServiceCaseResource::
                                    getUrl(
                                        'edit',
                                        [
                                            'record' =>
                                                $this
                                                    ->record,
                                        ]
                                    )
                            );

                            return;
                        }

                        Notification::make()
                            ->title(
                                'Atención iniciada'
                            )
                            ->body(
                                'El ticket quedó asignado para respuesta / gestión.'
                            )
                            ->success()
                            ->send();

                        $this->redirect(
                            ServiceCaseResource::getUrl(
                                'edit',
                                [
                                    'record' =>
                                        $this->record,
                                ]
                            )
                        );
                    }
                ),

            /*
             * BEXIA_ATC_TICKET_FINAL_DELIVERY_ACTIONS_V5_83_4C5G12
             */
            $this->ticketPrepareRepairExitDocumentAction(),
            $this->ticketPrintRepairExitDocumentAction(),
            $this->ticketDeliverToCustomerAction(),

        ];
    }

    /*
     * BEXIA_ATC_TECH_TICKET_READONLY_V5_82_P7H32A4B2
     *
     * El técnico entra por la página Edit porque ServiceCase
     * actualmente no tiene una página View independiente.
     *
     * Para un Técnico restringido se eliminan las acciones de
     * guardado del formulario general.
     *
     * Las acciones operativas expresamente autorizadas, como
     * Registrar respuesta, permanecen disponibles.
     */
    protected function getFormActions(): array
    {
        if (
            ! ServiceAccess::canEditServiceCaseMasterData()
        ) {
            return [];
        }

        return parent::getFormActions();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->oldStatus = $this->record->status;
        $this->oldAssignedEmployeeId = $this->record->assigned_employee_id;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (
            ! ServiceAccess::canEditServiceCaseMasterData()
        ) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Sólo Recepción, Encargado de Técnicos o Supervisor pueden modificar los datos generales del ticket.'
            );
        }

        $this->uploadedAttachments = $data['uploaded_attachments'] ?? [];
        unset($data['uploaded_attachments']);

        if (
            array_key_exists('assigned_employee_id', $data)
            && (string) ($data['assigned_employee_id'] ?? '') !== (string) ($this->oldAssignedEmployeeId ?? '')
            && auth()->check()
        ) {
            $data['assigned_by'] = auth()->id();
            $data['assigned_at'] = now();
        }

        if (empty($data['product_name']) && ! empty($data['product_id'])) {
            $data['product_name'] = ServiceAccess::productLabel((int) $data['product_id']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        ServiceAccess::saveUploadedAttachments(
            companyId: $this->record->company_id,
            serviceCaseId: $this->record->id,
            repairOrderId: null,
            files: $this->uploadedAttachments,
            stage: 'attention'
        );

        if ($this->oldStatus !== $this->record->status) {
            ServiceCaseResource::logEvent(
                $this->record,
                'cambio_estado_ticket',
                $this->oldStatus,
                $this->record->status,
                'Cambio de estado desde Filament.'
            );

            return;
        }

        if ((string) ($this->oldAssignedEmployeeId ?? '') !== (string) ($this->record->assigned_employee_id ?? '')) {
            ServiceCaseResource::logEvent(
                $this->record,
                'reasignacion_ticket',
                $this->record->status,
                $this->record->status,
                'Cambio de responsable desde Filament.'
            );

            return;
        }

        ServiceCaseResource::logEvent(
            $this->record,
            'ticket_actualizado',
            $this->record->status,
            $this->record->status,
            'Ticket actualizado desde Filament.'
        );
    }
}
