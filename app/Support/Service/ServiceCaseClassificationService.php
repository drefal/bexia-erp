<?php

namespace App\Support\Service;

use App\Models\RepairOrder;
use App\Models\ServiceCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ServiceCaseClassificationService
{
    public function classify(
        ServiceCase $serviceCase,
        array $data
    ): ?RepairOrder {
        if (! ServiceAccess::can('service.cases.classify')) {
            throw new AuthorizationException(
                'No tienes permiso para clasificar tickets.'
            );
        }

        $actorId = auth()->id();

        if (! $actorId) {
            throw new AuthorizationException(
                'Debes iniciar sesión para clasificar el ticket.'
            );
        }

        $route = (string) ($data['attention_route'] ?? '');

        if (! array_key_exists($route, ServiceCase::ATTENTION_ROUTES)) {
            throw ValidationException::withMessages([
                'attention_route' => 'Selecciona una ruta válida.',
            ]);
        }

        return DB::transaction(function () use (
            $serviceCase,
            $data,
            $route,
            $actorId
        ): ?RepairOrder {
            $case = ServiceCase::query()
                ->whereKey($serviceCase->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (filled($case->attention_route)) {
                throw ValidationException::withMessages([
                    'attention_route' =>
                        'Este ticket ya fue clasificado.',
                ]);
            }

            if (in_array(
                (string) $case->status,
                ['entregado', 'cerrado', 'rechazado', 'cancelado'],
                true
            )) {
                throw ValidationException::withMessages([
                    'attention_route' =>
                        'No se puede clasificar un ticket terminado.',
                ]);
            }

            if ($route === 'repair') {
                /*
                 * BEXIA_ATC_REPAIR_PRE_RECEPTION_V5_83_4C1
                 *
                 * Elegir la ruta de reparación NO significa que
                 * el equipo ya haya sido recibido.
                 *
                 * En esta etapa sólo se prepara la recepción.
                 * RepairOrder se creará en el paso físico
                 * Registrar recepción.
                 */
                $this->prepareRepairReception(
                    $case,
                    $data,
                    (int) $actorId
                );

                return null;
            }

            $this->classifyAsNonRepair(
                $case,
                $data,
                (int) $actorId
            );

            return null;
        });
    }

    /*
     * BEXIA_ATC_CONVERT_REPAIR_FULL_ROUTE_V5_83_4C5G3
     *
     * Una atención directa que posteriormente requiere
     * reparación NO puede saltarse la recepción física.
     *
     * La conversión reutiliza exactamente la primera etapa
     * del flujo normal:
     *
     * non_repair
     *      ->
     * repair / esperando_producto
     *
     * NO crea RepairOrder.
     * NO llena received_at.
     * NO inicia diagnóstico.
     *
     * La RepairOrder será creada únicamente por
     * receiveRepairEquipment().
     */
    public function convertNonRepairToRepair(
        ServiceCase $serviceCase,
        array $data
    ): void {
        if (! ServiceAccess::can('service.cases.classify')) {
            throw new AuthorizationException(
                'No tienes permiso para convertir tickets a reparación.'
            );
        }

        $actorId = (int) auth()->id();

        if ($actorId <= 0) {
            throw new AuthorizationException(
                'Debes iniciar sesión para convertir el ticket.'
            );
        }

        DB::transaction(function () use (
            $serviceCase,
            $data,
            $actorId
        ): void {
            $case = ServiceCase::query()
                ->whereKey(
                    $serviceCase->getKey()
                )
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (string) $case->attention_route
                    !== 'non_repair'
            ) {
                throw ValidationException::withMessages([
                    'conversion_notes' =>
                        'Sólo un ticket sin reparación puede convertirse.',
                ]);
            }

            if (
                in_array(
                    (string) $case->status,
                    [
                        'cerrado',
                        'rechazado',
                        'cancelado',
                        'entregado',
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'conversion_notes' =>
                        'Reabre el ticket antes de convertirlo a reparación.',
                ]);
            }

            $notes = trim((string) (
                $data['conversion_notes']
                ?? ''
            ));

            if ($notes === '') {
                throw ValidationException::withMessages([
                    'conversion_notes' =>
                        'Captura el motivo de conversión.',
                ]);
            }

            $oldStatus =
                (string) $case->status;

            $oldType =
                $case->non_repair_type;

            $payload = $data;

            $payload['classification_notes'] =
                $notes;

            /*
             * Punto clave C5G3:
             * reutiliza el mismo método que la clasificación
             * inicial como reparación.
             */
            $this->prepareRepairReception(
                $case,
                $payload,
                $actorId
            );

            $case->refresh();

            $this->logEvent(
                serviceCaseId:
                    (int) $case->id,

                repairOrderId:
                    null,

                companyId:
                    (int) $case->company_id,

                eventType:
                    'ticket_convertido_a_reparacion',

                fromStatus:
                    $oldStatus,

                toStatus:
                    'esperando_producto',

                actorId:
                    $actorId,

                notes:
                    $notes,

                oldValues: [
                    'attention_route' =>
                        'non_repair',

                    'non_repair_type' =>
                        $oldType,

                    'status' =>
                        $oldStatus,
                ],

                newValues: [
                    'attention_route' =>
                        'repair',

                    'repair_order_id' =>
                        null,

                    'status' =>
                        'esperando_producto',

                    'arrival_method' =>
                        data_get(
                            $case->metadata,
                            'repair_pre_reception.arrival_method'
                        ),

                    'reception_place' =>
                        data_get(
                            $case->metadata,
                            'repair_pre_reception.reception_place'
                        ),
                ]
            );
        });
    }

    /*
     * V5.83.4c1
     *
     * Primera etapa de una reparación física:
     * - define responsable
     * - define cómo llegará el equipo
     * - define lugar y fecha prevista
     * - deja el ticket en esperando_producto
     * - NO crea RepairOrder
     * - NO llena received_at
     * - NO inicia diagnóstico
     */
    protected function prepareRepairReception(
        ServiceCase $case,
        array $data,
        int $actorId
    ): void {
        if (
            RepairOrder::withTrashed()
                ->where(
                    'service_case_id',
                    $case->getKey()
                )
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'attention_route' =>
                    'Este ticket ya tiene una reparación vinculada.',
            ]);
        }

        if (
            blank($case->product_id)
            && blank($case->product_name)
        ) {
            throw ValidationException::withMessages([
                'attention_route' =>
                    'Identifica el equipo o producto antes de preparar la recepción.',
            ]);
        }

        if (
            blank($case->description)
            && blank($case->subject)
        ) {
            throw ValidationException::withMessages([
                'attention_route' =>
                    'Captura la descripción del problema.',
            ]);
        }

        $technicianId = (int) (
            $data['assigned_employee_id'] ?? 0
        );

        if ($technicianId <= 0) {
            throw ValidationException::withMessages([
                'assigned_employee_id' =>
                    'Selecciona al responsable de seguimiento.',
            ]);
        }

        $technicians =
            ServiceAccess::technicianEmployeeOptions();

        if (
            ! array_key_exists(
                $technicianId,
                $technicians
            )
        ) {
            throw ValidationException::withMessages([
                'assigned_employee_id' =>
                    'El responsable seleccionado no está disponible.',
            ]);
        }

        $arrivalMethod = (string) (
            $data['repair_arrival_method'] ?? ''
        );

        if (
            ! array_key_exists(
                $arrivalMethod,
                ServiceCase::REPAIR_ARRIVAL_METHODS
            )
        ) {
            throw ValidationException::withMessages([
                'repair_arrival_method' =>
                    'Selecciona cómo llegará el equipo.',
            ]);
        }

        $receptionPlace = trim((string) (
            $data['repair_reception_place'] ?? ''
        ));

        if ($receptionPlace === '') {
            throw ValidationException::withMessages([
                'repair_reception_place' =>
                    'Captura el lugar donde se recibirá el equipo.',
            ]);
        }

        $plannedReceptionAt =
            $data['planned_reception_at']
            ?? null;

        $notes = trim((string) (
            $data['classification_notes'] ?? ''
        ));

        if ($notes === '') {
            $notes =
                'Recepción de equipo preparada.';
        }

        $oldStatus = (string) $case->status;
        $oldAttentionRoute =
            $case->attention_route;

        $now = now();

        $metadata = is_array($case->metadata)
            ? $case->metadata
            : [];

        $metadata['repair_pre_reception'] = [
            'arrival_method' =>
                $arrivalMethod,
            'arrival_method_label' =>
                ServiceCase::REPAIR_ARRIVAL_METHODS[
                    $arrivalMethod
                ],
            'reception_place' =>
                $receptionPlace,
            'planned_reception_at' =>
                $plannedReceptionAt,
            'prepared_at' =>
                $now->toDateTimeString(),
            'prepared_by' =>
                $actorId,
        ];

        $case->update([
            'attention_route' => 'repair',
            'classified_at' => $now,
            'classified_by' => $actorId,
            'classification_notes' => $notes,

            'non_repair_type' => null,
            'resolution_type' => null,
            'resolution_notes' => null,

            'case_type' => 'reparacion',

            /*
             * Sigue pendiente de recepción física.
             * NO usar producto_recibido ni en_diagnostico.
             */
            'status' => 'esperando_producto',

            'assigned_employee_id' =>
                $technicianId,
            'assigned_at' => $now,
            'assigned_by' => $actorId,

            'metadata' => $metadata,
        ]);

        $this->logEvent(
            serviceCaseId: (int) $case->id,
            repairOrderId: null,
            companyId: (int) $case->company_id,
            eventType:
                'ticket_preparado_recepcion',
            fromStatus: $oldStatus,
            toStatus: 'esperando_producto',
            actorId: $actorId,
            notes: $notes,
            oldValues: [
                'attention_route' =>
                    $oldAttentionRoute,
                'status' =>
                    $oldStatus,
            ],
            newValues: [
                'attention_route' =>
                    'repair',
                'status' =>
                    'esperando_producto',
                'assigned_employee_id' =>
                    $technicianId,
                'arrival_method' =>
                    $arrivalMethod,
                'reception_place' =>
                    $receptionPlace,
                'planned_reception_at' =>
                    $plannedReceptionAt,
                'repair_order_id' =>
                    null,
            ]
        );
    }

    /*
     * BEXIA_ATC_PHYSICAL_RECEPTION_V5_83_4C3
     *
     * Este es el punto donde el equipo YA fue recibido
     * físicamente.
     *
     * Sólo aquí:
     * - se crea RepairOrder
     * - se registra received_at
     * - se registra checklist de recepción
     * - se vinculan NUEVAS evidencias stage=reception
     * - el ticket avanza a en_diagnostico
     *
     * No se relocalizan ni reclasifican evidencias
     * históricas de ticket_opening o pre_reception.
     */
    /*
     * BEXIA_ATC_RECEPTION_IDENTITY_BACKEND_V5_83_4C4C5B
     *
     * Recepcion fisica definitiva.
     *
     * Conserva tres capas:
     * - ticket original
     * - pickup observado
     * - recepcion confirmada
     *
     * Firma:
     * - pickup por chofer ya firmado:
     *   reutiliza attachment pickup por referencia.
     * - entrega directa:
     *   exige nueva firma reception.
     */
    public function receiveRepairEquipment(
        ServiceCase $serviceCase,
        array $data
    ): RepairOrder {
        if (
            ! ServiceAccess::
                canEditServiceCaseMasterData()
        ) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Sólo Recepción, Encargado de Técnicos o Supervisor pueden registrar la recepción física.'
            );
        }

        $actorId = (int) auth()->id();

        if ($actorId <= 0) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Debes iniciar sesión para registrar la recepción.'
            );
        }

        /*
         * Validar checklist.
         * No escribe DB.
         */
        $checklistService = app(
            ServiceRepairReceptionChecklistService::class
        );

        $checklistService->validateData(
            $data
        );

        $receivedFrom = trim((string) (
            $data[
                'reception_received_from'
            ]
            ?? ''
        ));

        if ($receivedFrom === '') {
            throw ValidationException::withMessages([
                'reception_received_from' =>
                    'Captura quién entrega físicamente el equipo.',
            ]);
        }

        $receivedLocation = trim((string) (
            $data[
                'reception_received_location'
            ]
            ?? ''
        ));

        $receivedByName = trim((string) (
            $data[
                'reception_received_by_name'
            ]
            ?? ''
        ));

        $receivedLocation =
            $receivedLocation !== ''
                ? $receivedLocation
                : null;

        $receivedByName =
            $receivedByName !== ''
                ? $receivedByName
                : null;

        /*
         * Puede venir vacía cuando se reutiliza
         * la firma de pickup.
         */
        $signatureData = trim((string) (
            $data[
                'reception_customer_signature'
            ]
            ?? ''
        ));

        $normalizeIdentity =
            static function (
                mixed $value
            ): string {
                $normalized = trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        (string) $value
                    )
                    ?? ''
                );

                return mb_strtolower(
                    $normalized,
                    'UTF-8'
                );
            };

        /*
         * Sólo se llena si este método crea
         * un PNG nuevo de recepción.
         */
        $newSignaturePath = null;

        try {
            return DB::transaction(
                function () use (
                    $serviceCase,
                    $data,
                    $actorId,
                    $checklistService,
                    $receivedFrom,
                    $receivedLocation,
                    $receivedByName,
                    $signatureData,
                    $normalizeIdentity,
                    &$newSignaturePath
                ): RepairOrder {
                    $case = ServiceCase::query()
                        ->whereKey(
                            $serviceCase->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (
                        (string) $case->attention_route
                            !== 'repair'
                    ) {
                        throw ValidationException::withMessages([
                            'reception_notes' =>
                                'Este ticket no está en la ruta de reparación.',
                        ]);
                    }

                    if (
                        (string) $case->status
                            !== 'esperando_producto'
                    ) {
                        throw ValidationException::withMessages([
                            'reception_notes' =>
                                'El ticket ya no está pendiente de recepción física.',
                        ]);
                    }

                    if (
                        RepairOrder::withTrashed()
                            ->where(
                                'service_case_id',
                                $case->getKey()
                            )
                            ->exists()
                    ) {
                        throw ValidationException::withMessages([
                            'reception_notes' =>
                                'Este ticket ya tiene una orden de reparación vinculada.',
                        ]);
                    }

                    $technicianId = (int) (
                        $case->assigned_employee_id
                        ?? 0
                    );

                    if ($technicianId <= 0) {
                        throw ValidationException::withMessages([
                            'reception_notes' =>
                                'El ticket no tiene responsable asignado.',
                        ]);
                    }

                    $technicians =
                        ServiceAccess::
                            technicianEmployeeOptions();

                    if (
                        ! array_key_exists(
                            $technicianId,
                            $technicians
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'reception_notes' =>
                                'El responsable asignado ya no está disponible.',
                        ]);
                    }

                    /*
                     * Metadata previa.
                     */
                    $caseMetadata =
                        is_array($case->metadata)
                            ? $case->metadata
                            : [];

                    $preReception =
                        (array) (
                            $caseMetadata[
                                'repair_pre_reception'
                            ]
                            ?? []
                        );

                    $pickupOrder =
                        (array) (
                            $caseMetadata[
                                'pickup_order'
                            ]
                            ?? []
                        );

                    $pickupSnapshot =
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

                    /*
                     * CAPA 1:
                     * identidad que existía antes
                     * de la recepción definitiva.
                     */
                    $ticketProductName = trim((string) (
                        $case->product_name
                        ?? ''
                    ));

                    $ticketSerialNumber = trim((string) (
                        $case->serial_number
                        ?? ''
                    ));

                    $ticketLotNumber = trim((string) (
                        $case->lot_number
                        ?? ''
                    ));

                    $ticketSerialNumber =
                        $ticketSerialNumber !== ''
                            ? $ticketSerialNumber
                            : null;

                    $ticketLotNumber =
                        $ticketLotNumber !== ''
                            ? $ticketLotNumber
                            : null;

                    /*
                     * CAPA 2:
                     * datos observados por chofer.
                     */
                    $pickupProductName = trim((string) (
                        $pickup[
                            'observed_product_name'
                        ]
                        ?? ''
                    ));

                    $pickupSerialNumber = trim((string) (
                        $pickup[
                            'observed_serial_number'
                        ]
                        ?? ''
                    ));

                    $pickupProductName =
                        $pickupProductName !== ''
                            ? $pickupProductName
                            : null;

                    $pickupSerialNumber =
                        $pickupSerialNumber !== ''
                            ? $pickupSerialNumber
                            : null;

                    /*
                     * CAPA 3:
                     * confirmación física definitiva.
                     */
                    $productName = trim((string) (
                        $data[
                            'reception_product_name'
                        ]
                        ?? $case->product_name
                        ?? ''
                    ));

                    if ($productName === '') {
                        throw ValidationException::withMessages([
                            'reception_product_name' =>
                                'Confirma el producto o modelo recibido.',
                        ]);
                    }

                    $serialNumber = trim((string) (
                        $data[
                            'reception_serial_number'
                        ]
                        ?? $case->serial_number
                        ?? ''
                    ));

                    $lotNumber = trim((string) (
                        $data[
                            'reception_lot_number'
                        ]
                        ?? $case->lot_number
                        ?? ''
                    ));

                    $serialNumber =
                        $serialNumber !== ''
                            ? $serialNumber
                            : null;

                    $lotNumber =
                        $lotNumber !== ''
                            ? $lotNumber
                            : null;

                    $now = now();

                    /*
                     * Firma reusable de pickup.
                     *
                     * NO se modifica ese attachment.
                     */
                    $pickupSignatureId = (int) (
                        $pickup[
                            'signature_attachment_id'
                        ]
                        ?? 0
                    );

                    $pickupSignatureAttachment = null;
                    $reusePickupSignature = false;

                    if (
                        (string) (
                            $preReception[
                                'arrival_method'
                            ]
                            ?? ''
                        ) === 'recoleccion_chofer'
                        && (
                            (string) (
                                $pickupOrder[
                                    'status'
                                ]
                                ?? ''
                            ) === 'completed'
                        )
                        && $pickupSignatureId > 0
                        && filled(
                            $pickup[
                                'customer_signed_at'
                            ]
                            ?? null
                        )
                    ) {
                        $pickupSignatureAttachment =
                            DB::table(
                                'service_attachments'
                            )
                            ->where(
                                'id',
                                $pickupSignatureId
                            )
                            ->where(
                                'service_case_id',
                                $case->id
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
                            $pickupSignatureAttachment
                            && filled(
                                $pickupSignatureAttachment
                                    ->file_path
                                ?? null
                            )
                            && \Illuminate\Support\Facades\Storage::
                                disk('public')
                                ->exists(
                                    (string)
                                        $pickupSignatureAttachment
                                            ->file_path
                                )
                        ) {
                            $reusePickupSignature = true;
                        }
                    }

                    /*
                     * Si no hay una firma pickup válida,
                     * entonces la firma de recepción es
                     * obligatoria.
                     */
                    $newSignatureBinary = null;

                    if (! $reusePickupSignature) {
                        $signaturePrefix =
                            'data:image/png;base64,';

                        if (
                            $signatureData === ''
                            || ! str_starts_with(
                                $signatureData,
                                $signaturePrefix
                            )
                        ) {
                            throw ValidationException::withMessages([
                                'reception_customer_signature' =>
                                    'Solicita y captura la firma del cliente.',
                            ]);
                        }

                        $newSignatureBinary =
                            base64_decode(
                                substr(
                                    $signatureData,
                                    strlen(
                                        $signaturePrefix
                                    )
                                ),
                                true
                            );

                        if (
                            $newSignatureBinary === false
                            || strlen(
                                $newSignatureBinary
                            ) < 500
                        ) {
                            throw ValidationException::withMessages([
                                'reception_customer_signature' =>
                                    'La firma capturada no es válida. Vuelve a firmar.',
                            ]);
                        }

                        if (
                            strlen(
                                $newSignatureBinary
                            ) > (
                                2
                                * 1024
                                * 1024
                            )
                        ) {
                            throw ValidationException::withMessages([
                                'reception_customer_signature' =>
                                    'La firma capturada excede el tamaño permitido.',
                            ]);
                        }

                        $signatureInfo =
                            @getimagesizefromstring(
                                $newSignatureBinary
                            );

                        if (
                            ! is_array(
                                $signatureInfo
                            )
                            || (
                                $signatureInfo[
                                    'mime'
                                ]
                                ?? ''
                            ) !== 'image/png'
                        ) {
                            throw ValidationException::withMessages([
                                'reception_customer_signature' =>
                                    'La firma capturada no es una imagen PNG válida.',
                            ]);
                        }
                    }

                    $productCorrected =
                        $normalizeIdentity(
                            $ticketProductName
                        )
                        !==
                        $normalizeIdentity(
                            $productName
                        );

                    $serialCorrected =
                        $normalizeIdentity(
                            $ticketSerialNumber
                        )
                        !==
                        $normalizeIdentity(
                            $serialNumber
                        );

                    $lotCorrected =
                        $normalizeIdentity(
                            $ticketLotNumber
                        )
                        !==
                        $normalizeIdentity(
                            $lotNumber
                        );

                    $receptionIdentity = [
                        'ticket_product_name' =>
                            $ticketProductName !== ''
                                ? $ticketProductName
                                : null,

                        'pickup_product_name' =>
                            $pickupProductName,

                        'received_product_name' =>
                            $productName,

                        'product_corrected' =>
                            $productCorrected,

                        'ticket_serial_number' =>
                            $ticketSerialNumber,

                        'pickup_serial_number' =>
                            $pickupSerialNumber,

                        'received_serial_number' =>
                            $serialNumber,

                        'serial_corrected' =>
                            $serialCorrected,

                        'ticket_lot_number' =>
                            $ticketLotNumber,

                        'received_lot_number' =>
                            $lotNumber,

                        'lot_corrected' =>
                            $lotCorrected,

                        'pickup_product_differs_from_ticket' =>
                            (bool) (
                                $pickup[
                                    'product_differs'
                                ]
                                ?? false
                            ),

                        'pickup_serial_differs_from_ticket' =>
                            (bool) (
                                $pickup[
                                    'serial_differs'
                                ]
                                ?? false
                            ),

                        'confirmed_by_user_id' =>
                            $actorId,

                        'confirmed_at' =>
                            $now
                                ->toDateTimeString(),
                    ];

                    /*
                     * Referencia de pickup sin copiar token.
                     */
                    $pickupReference = [
                        'status' =>
                            $pickupOrder[
                                'status'
                            ]
                            ?? null,

                        'completed_at' =>
                            $pickupOrder[
                                'completed_at'
                            ]
                            ?? null,

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
                            $pickupProductName,

                        'observed_serial_number' =>
                            $pickupSerialNumber,

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
                            $pickup[
                                'accessories'
                            ]
                            ?? [],

                        'accessories_other' =>
                            $pickup[
                                'accessories_other'
                            ]
                            ?? null,

                        'notes' =>
                            $pickup[
                                'notes'
                            ]
                            ?? null,

                        'evidence_attachment_ids' =>
                            $pickup[
                                'evidence_attachment_ids'
                            ]
                            ?? [],

                        'signature_attachment_id' =>
                            $pickupSignatureId > 0
                                ? $pickupSignatureId
                                : null,

                        'customer_signed_at' =>
                            $pickup[
                                'customer_signed_at'
                            ]
                            ?? null,
                    ];

                    /*
                     * Crear RepairOrder por primera vez.
                     */
                    $repair = RepairOrder::create([
                        'company_id' =>
                            $case->company_id,

                        'service_case_id' =>
                            $case->id,

                        'customer_id' =>
                            $case->customer_id,

                        'product_id' =>
                            $case->product_id,

                        'sale_id' =>
                            $case->sale_id,

                        'invoice_id' =>
                            $case->invoice_id,

                        'invoice_reference' =>
                            $case->invoice_reference,

                        'sale_reference' =>
                            $case->sale_reference,

                        'product_name' =>
                            $productName,

                        'serial_number' =>
                            $serialNumber,

                        'lot_number' =>
                            $lotNumber,

                        'status' =>
                            'recibido',

                        /*
                         * Compatibilidad con workflow actual.
                         * Diagnóstico se rediseñará después.
                         */
                        'workflow_stage' =>
                            'quote_draft',

                        'quote_status' =>
                            'draft',

                        'requires_customer_approval' =>
                            false,

                        'requires_internal_approval' =>
                            false,

                        'warranty_status' =>
                            'pendiente',

                        'received_at' =>
                            $now,

                        'promised_at' =>
                            null,

                        'received_condition' =>
                            null,

                        'initial_diagnosis' =>
                            null,

                        'technical_diagnosis' =>
                            null,

                        'assigned_employee_id' =>
                            $technicianId,

                        'assigned_at' =>
                            $case->assigned_at
                            ?: $now,

                        'assigned_by' =>
                            $case->assigned_by
                            ?: $actorId,

                        'created_by' =>
                            $actorId,

                        'metadata' => [
                            'created_from' =>
                                'service_case_physical_reception',

                            'service_case_folio' =>
                                $case->folio,

                            'pre_reception' =>
                                $preReception,

                            'pickup_reception_reference' =>
                                $pickupReference,

                            'reception_identity' =>
                                $receptionIdentity,

                            'physical_reception' => [
                                'received_by_user_id' =>
                                    $actorId,

                                'received_at' =>
                                    $now
                                        ->toDateTimeString(),

                                'received_from' =>
                                    $receivedFrom,

                                'received_location' =>
                                    $receivedLocation,

                                'received_by_name' =>
                                    $receivedByName,

                                'confirmed_product_name' =>
                                    $productName,

                                'confirmed_serial_number' =>
                                    $serialNumber,

                                'confirmed_lot_number' =>
                                    $lotNumber,
                            ],
                        ],
                    ]);

                    /*
                     * Checklist formal.
                     */
                    $repair =
                        $checklistService
                            ->recordChecklist(
                                $repair,
                                $data
                            );

                    /*
                     * Resolver firma.
                     */
                    $signatureAttachmentId = null;
                    $signaturePath = null;
                    $customerSignedAt = null;
                    $signatureSource = null;

                    if ($reusePickupSignature) {
                        $signatureAttachmentId =
                            (int)
                                $pickupSignatureAttachment
                                    ->id;

                        $signaturePath =
                            (string)
                                $pickupSignatureAttachment
                                    ->file_path;

                        $customerSignedAt =
                            (string) (
                                $pickup[
                                    'customer_signed_at'
                                ]
                                ?? $pickupOrder[
                                    'completed_at'
                                ]
                                ?? $now
                                    ->toDateTimeString()
                            );

                        $signatureSource =
                            'pickup';
                    } else {
                        $newSignaturePath =
                            'service-attachments/reception-signatures/'
                            . $case->getKey()
                            . '-'
                            . \Illuminate\Support\Str::uuid()
                            . '.png';

                        $signatureStored =
                            \Illuminate\Support\Facades\Storage::
                                disk('public')
                                ->put(
                                    $newSignaturePath,
                                    $newSignatureBinary
                                );

                        if (! $signatureStored) {
                            throw ValidationException::withMessages([
                                'reception_customer_signature' =>
                                    'No fue posible guardar la firma del cliente.',
                            ]);
                        }

                        $signatureAttachmentId =
                            DB::table(
                                'service_attachments'
                            )
                            ->insertGetId([
                                'company_id' =>
                                    $case->company_id,

                                'service_case_id' =>
                                    $case->id,

                                'repair_order_id' =>
                                    $repair->id,

                                'uploaded_by' =>
                                    $actorId,

                                'stage' =>
                                    'reception',

                                'file_name' =>
                                    'firma-recepcion-'
                                    . $case->folio
                                    . '.png',

                                'file_path' =>
                                    $newSignaturePath,

                                'mime_type' =>
                                    'image/png',

                                'size' =>
                                    strlen(
                                        $newSignatureBinary
                                    ),

                                'is_customer_visible' =>
                                    false,

                                'notes' =>
                                    'Firma del cliente en recepción física.',

                                'created_at' =>
                                    $now,

                                'updated_at' =>
                                    $now,
                            ]);

                        $signaturePath =
                            $newSignaturePath;

                        $customerSignedAt =
                            $now
                                ->toDateTimeString();

                        $signatureSource =
                            'physical_reception';
                    }

                    /*
                     * Evidencia NUEVA de recepción.
                     *
                     * No mover:
                     * - ticket_opening
                     * - pre_reception
                     * - pickup
                     */
                    ServiceAccess::
                        saveUploadedAttachments(
                            companyId:
                                (int)
                                    $case
                                        ->company_id,

                            serviceCaseId:
                                (int)
                                    $case->id,

                            repairOrderId:
                                (int)
                                    $repair->id,

                            files:
                                $data[
                                    'reception_files'
                                ]
                                ?? null,

                            stage:
                                'reception',

                            isCustomerVisible:
                                false
                        );

                    /*
                     * Metadata final RepairOrder.
                     */
                    $repair->refresh();

                    $repairMetadata =
                        is_array($repair->metadata)
                            ? $repair->metadata
                            : [];

                    $physicalReception =
                        (array) (
                            $repairMetadata[
                                'physical_reception'
                            ]
                            ?? []
                        );

                    $physicalReception[
                        'customer_signature_source'
                    ] = $signatureSource;

                    $physicalReception[
                        'customer_signature_reused_from_pickup'
                    ] = $reusePickupSignature;

                    $physicalReception[
                        'customer_signature_path'
                    ] = $signaturePath;

                    $physicalReception[
                        'customer_signature_attachment_id'
                    ] = $signatureAttachmentId;

                    $physicalReception[
                        'customer_signed_at'
                    ] = $customerSignedAt;

                    $repairMetadata[
                        'physical_reception'
                    ] = $physicalReception;

                    $repairMetadata[
                        'reception_identity'
                    ] = $receptionIdentity;

                    $repairMetadata[
                        'pickup_reception_reference'
                    ] = $pickupReference;

                    $repair->update([
                        'metadata' =>
                            $repairMetadata,
                    ]);

                    /*
                     * Guardar comparison también en ticket.
                     *
                     * pickup_order se conserva intacto.
                     */
                    $caseMetadata[
                        'reception_identity'
                    ] = $receptionIdentity;

                    $oldStatus =
                        (string) $case->status;

                    $case->update([
                        'product_name' =>
                            $productName,

                        'serial_number' =>
                            $serialNumber,

                        'lot_number' =>
                            $lotNumber,

                        'status' =>
                            'en_diagnostico',

                        'metadata' =>
                            $caseMetadata,
                    ]);

                    /*
                     * Evento principal.
                     */
                    $this->logEvent(
                        serviceCaseId:
                            (int) $case->id,

                        repairOrderId:
                            (int) $repair->id,

                        companyId:
                            (int)
                                $case
                                    ->company_id,

                        eventType:
                            'equipo_recibido_orden_reparacion_creada',

                        fromStatus:
                            $oldStatus,

                        toStatus:
                            'en_diagnostico',

                        actorId:
                            $actorId,

                        notes:
                            'Equipo recibido físicamente y orden técnica creada.',

                        oldValues: [
                            'status' =>
                                $oldStatus,

                            'repair_order_id' =>
                                null,

                            'product_name' =>
                                $ticketProductName,

                            'serial_number' =>
                                $ticketSerialNumber,

                            'lot_number' =>
                                $ticketLotNumber,
                        ],

                        newValues: [
                            'status' =>
                                'en_diagnostico',

                            'repair_order_id' =>
                                $repair->id,

                            'received_at' =>
                                $now
                                    ->toDateTimeString(),

                            'received_from' =>
                                $receivedFrom,

                            'received_location' =>
                                $receivedLocation,

                            'received_by_name' =>
                                $receivedByName,

                            'product_name' =>
                                $productName,

                            'serial_number' =>
                                $serialNumber,

                            'lot_number' =>
                                $lotNumber,

                            'customer_signature_source' =>
                                $signatureSource,

                            'customer_signature_attachment_id' =>
                                $signatureAttachmentId,

                            'customer_signature_path' =>
                                $signaturePath,

                            'additional_reception_evidence_count' =>
                                count(
                                    (array) (
                                        $data[
                                            'reception_files'
                                        ]
                                        ?? []
                                    )
                                ),
                        ]
                    );

                    /*
                     * Evento adicional SOLO si la identidad
                     * final corrigió el ticket original.
                     */
                    if (
                        $productCorrected
                        || $serialCorrected
                        || $lotCorrected
                    ) {
                        $this->logEvent(
                            serviceCaseId:
                                (int) $case->id,

                            repairOrderId:
                                (int) $repair->id,

                            companyId:
                                (int)
                                    $case
                                        ->company_id,

                            eventType:
                                'datos_equipo_corregidos_en_recepcion',

                            fromStatus:
                                'en_diagnostico',

                            toStatus:
                                'en_diagnostico',

                            actorId:
                                $actorId,

                            notes:
                                'La identidad definitiva del equipo fue confirmada durante la recepción física.',

                            oldValues: [
                                'ticket_product_name' =>
                                    $ticketProductName,

                                'ticket_serial_number' =>
                                    $ticketSerialNumber,

                                'ticket_lot_number' =>
                                    $ticketLotNumber,

                                'pickup_product_name' =>
                                    $pickupProductName,

                                'pickup_serial_number' =>
                                    $pickupSerialNumber,
                            ],

                            newValues: [
                                'received_product_name' =>
                                    $productName,

                                'received_serial_number' =>
                                    $serialNumber,

                                'received_lot_number' =>
                                    $lotNumber,

                                'product_corrected' =>
                                    $productCorrected,

                                'serial_corrected' =>
                                    $serialCorrected,

                                'lot_corrected' =>
                                    $lotCorrected,
                            ]
                        );
                    }

                    return $repair->fresh();
                }
            );
        } catch (\Throwable $exception) {
            /*
             * Sólo borrar una firma NUEVA creada
             * por esta recepción.
             *
             * Nunca borrar la firma reutilizada
             * del pickup.
             */
            try {
                if (
                    filled(
                        $newSignaturePath
                    )
                    && \Illuminate\Support\Facades\Storage::
                        disk('public')
                        ->exists(
                            $newSignaturePath
                        )
                ) {
                    \Illuminate\Support\Facades\Storage::
                        disk('public')
                        ->delete(
                            $newSignaturePath
                        );
                }
            } catch (\Throwable) {
                /*
                 * No ocultar la excepción original.
                 */
            }

            throw $exception;
        }
    }

    protected function classifyAsRepair(
        ServiceCase $case,
        array $data,
        int $actorId
    ): RepairOrder {
        if (
            RepairOrder::withTrashed()
                ->where('service_case_id', $case->getKey())
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'attention_route' =>
                    'Este ticket ya tiene una reparación vinculada.',
            ]);
        }

        if (
            blank($case->product_id)
            && blank($case->product_name)
        ) {
            throw ValidationException::withMessages([
                'attention_route' =>
                    'El ticket necesita un producto antes de crear la reparación.',
            ]);
        }

        if (
            blank($case->description)
            && blank($case->subject)
        ) {
            throw ValidationException::withMessages([
                'attention_route' =>
                    'El ticket necesita la descripción del problema.',
            ]);
        }

        $technicianId = (int) (
            $data['assigned_employee_id'] ?? 0
        );

        if ($technicianId <= 0) {
            throw ValidationException::withMessages([
                'assigned_employee_id' =>
                    'Selecciona el técnico responsable.',
            ]);
        }

        $technicians = ServiceAccess::technicianEmployeeOptions();

        if (! array_key_exists($technicianId, $technicians)) {
            throw ValidationException::withMessages([
                'assigned_employee_id' =>
                    'El técnico seleccionado no está disponible.',
            ]);
        }

        $diagnosis = trim((string) (
            $data['initial_diagnosis'] ?? ''
        ));

        if ($diagnosis === '') {
            throw ValidationException::withMessages([
                'initial_diagnosis' =>
                    'Captura el diagnóstico preliminar.',
            ]);
        }

        $warrantyStatus = (string) (
            $data['warranty_status'] ?? 'no_aplica'
        );

        if (
            ! array_key_exists(
                $warrantyStatus,
                RepairOrder::WARRANTY_STATUSES
            )
        ) {
            throw ValidationException::withMessages([
                'warranty_status' =>
                    'Selecciona un estado de garantía válido.',
            ]);
        }

        $notes = trim((string) (
            $data['classification_notes'] ?? ''
        ));

        if ($notes === '') {
            throw ValidationException::withMessages([
                'classification_notes' =>
                    'Captura las notas de clasificación.',
            ]);
        }

        $requiresQuote = (bool) (
            $data['requires_quote'] ?? true
        );

        $oldStatus = (string) $case->status;
        $oldAttentionRoute = $case->attention_route;
        $now = now();

        $repair = RepairOrder::create([
            'company_id' => $case->company_id,
            'service_case_id' => $case->id,
            'customer_id' => $case->customer_id,
            'product_id' => $case->product_id,
            'sale_id' => $case->sale_id,
            'invoice_id' => $case->invoice_id,
            'invoice_reference' => $case->invoice_reference,
            'sale_reference' => $case->sale_reference,
            'product_name' => $case->product_name,
            'serial_number' => $case->serial_number,
            'lot_number' => $case->lot_number,
            'status' => 'en_diagnostico',
            'workflow_stage' => 'quote_draft',
            'quote_status' => $requiresQuote
                ? 'draft'
                : 'not_required',
            'requires_customer_approval' => $requiresQuote,
            'requires_internal_approval' => false,
            'warranty_status' => $warrantyStatus,
            'received_at' => $now,
            'promised_at' => $data['promised_at'] ?? null,
            'initial_diagnosis' => $diagnosis,
            'assigned_employee_id' => $technicianId,
            'assigned_at' => $now,
            'assigned_by' => $actorId,
            'created_by' => $actorId,
            'metadata' => [
                'created_from' =>
                    'service_case_classification',
                'classification_notes' => $notes,
            ],
        ]);

        $case->update([
            'attention_route' => 'repair',
            'classified_at' => $now,
            'classified_by' => $actorId,
            'classification_notes' => $notes,
            'non_repair_type' => null,
            'resolution_type' => null,
            'resolution_notes' => null,
            'case_type' => 'reparacion',
            'status' => 'en_diagnostico',
            'assigned_employee_id' => $technicianId,
            'assigned_at' => $now,
            'assigned_by' => $actorId,
            'due_at' => $data['promised_at'] ?? $case->due_at,
        ]);

        if (Schema::hasTable('service_attachments')) {
            DB::table('service_attachments')
                ->where('service_case_id', $case->id)
                ->whereNull('repair_order_id')
                ->update([
                    'repair_order_id' => $repair->id,
                    'updated_at' => $now,
                ]);
        }

        $this->logEvent(
            serviceCaseId: (int) $case->id,
            repairOrderId: (int) $repair->id,
            companyId: (int) $case->company_id,
            eventType: 'ticket_clasificado_reparacion',
            fromStatus: $oldStatus,
            toStatus: 'en_diagnostico',
            actorId: $actorId,
            notes: $notes,
            oldValues: [
                'attention_route' => $oldAttentionRoute,
                'status' => $oldStatus,
            ],
            newValues: [
                'attention_route' => 'repair',
                'status' => 'en_diagnostico',
                'repair_order_id' => $repair->id,
            ]
        );

        $this->logEvent(
            serviceCaseId: (int) $case->id,
            repairOrderId: (int) $repair->id,
            companyId: (int) $case->company_id,
            eventType:
                'reparacion_creada_desde_clasificacion',
            fromStatus: null,
            toStatus: 'en_diagnostico',
            actorId: $actorId,
            notes:
                'Orden creada desde Clasificar atención.',
            oldValues: null,
            newValues: [
                'repair_order_id' => $repair->id,
                'workflow_stage' => 'quote_draft',
            ]
        );

        return $repair;
    }

    protected function classifyAsNonRepair(
        ServiceCase $case,
        array $data,
        int $actorId
    ): void {
        $type = (string) (
            $data['non_repair_type'] ?? ''
        );

        if ($type === '') {
            $type = 'otro';
        }

        if (
            ! array_key_exists(
                $type,
                ServiceCase::NON_REPAIR_TYPES
            )
        ) {
            throw ValidationException::withMessages([
                'non_repair_type' =>
                    'Selecciona el tipo de atención.',
            ]);
        }

        $notes = trim((string) (
            $data['classification_notes'] ?? ''
        ));

        if ($notes === '') {
            $notes = 'Atención / gestión asignada.';
        }

        $technicianId = (int) (
            $data['assigned_employee_id'] ?? 0
        );

        if ($technicianId > 0) {
            $technicians =
                ServiceAccess::managementResponsibleEmployeeOptions();

            if (! array_key_exists(
                $technicianId,
                $technicians
            )) {
                throw ValidationException::withMessages([
                    'assigned_employee_id' =>
                        'El responsable seleccionado no está disponible.',
                ]);
            }
        }

        $oldStatus = (string) $case->status;
        $newStatus = $technicianId > 0
            ? 'asignado'
            : 'en_revision';

        $now = now();

        $updates = [
            'attention_route' => 'non_repair',
            'classified_at' => $now,
            'classified_by' => $actorId,
            'classification_notes' => $notes,
            'non_repair_type' => $type,
            'resolution_type' => null,
            'resolution_notes' => null,
            'status' => $newStatus,
            'due_at' =>
                $data['non_repair_due_at']
                ?? $case->due_at,
        ];

        if ($technicianId > 0) {
            $updates['assigned_employee_id'] =
                $technicianId;
            $updates['assigned_at'] = $now;
            $updates['assigned_by'] = $actorId;
        }

        $case->update($updates);

        $this->logEvent(
            serviceCaseId: (int) $case->id,
            repairOrderId: null,
            companyId: (int) $case->company_id,
            eventType:
                'ticket_clasificado_sin_reparacion',
            fromStatus: $oldStatus,
            toStatus: $newStatus,
            actorId: $actorId,
            notes: $notes,
            oldValues: [
                'attention_route' => null,
                'status' => $oldStatus,
            ],
            newValues: [
                'attention_route' => 'non_repair',
                'non_repair_type' => $type,
                'status' => $newStatus,
            ]
        );
    }

    protected function logEvent(
        int $serviceCaseId,
        ?int $repairOrderId,
        int $companyId,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        int $actorId,
        string $notes,
        ?array $oldValues,
        ?array $newValues
    ): void {
        if (! Schema::hasTable('service_case_events')) {
            return;
        }

        $now = now();

        DB::table('service_case_events')->insert([
            'company_id' => $companyId,
            'service_case_id' => $serviceCaseId,
            'repair_order_id' => $repairOrderId,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'performed_by' => $actorId,
            'performed_at' => $now,
            'notes' => $notes,
            'old_values' => $oldValues
                ? json_encode(
                    $oldValues,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                )
                : null,
            'new_values' => $newValues
                ? json_encode(
                    $newValues,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                )
                : null,
            'metadata' => json_encode([
                'source' =>
                    'service_case_classification',
            ], JSON_UNESCAPED_UNICODE),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
