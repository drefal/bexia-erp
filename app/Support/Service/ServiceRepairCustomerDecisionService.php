<?php

namespace App\Support\Service;

use App\Models\RepairOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ServiceRepairCustomerDecisionService
{
    public const DECISIONS = [
        'approved' => 'Aprobó / dio VoBo',
        'rejected' => 'No aprobó / rechazó',
    ];

    public const CHANNELS = [
        'whatsapp' => 'WhatsApp',
        'telefono' => 'Teléfono',
        'correo' => 'Correo',
        'presencial' => 'Presencial',
        'otro' => 'Otro',
    ];


    /*
     * BEXIA_ATC_POST_REPAIR_CUSTOMER_APPROVAL_SERVICE_V5_83_4C5D2
     *
     * Flujo nuevo:
     *
     * Tecnico termina
     * -> Encargado costea
     * -> cliente da Vo.Bo.
     *
     * NO vuelve a:
     * quote_approved -> approved_pending_repair.
     *
     * La reparacion fisica ya termino.
     */
    public function recordPostRepairDecision(
        RepairOrder $repairOrder,
        array $data
    ): RepairOrder {
        if (
            ! ServiceAccess::
                canRecordPostRepairCustomerDecision(
                    $repairOrder
                )
        ) {
            throw new AuthorizationException(
                'No tienes permiso para registrar '
                . 'el Vo.Bo. post-reparación.'
            );
        }

        $actorId =
            (int) auth()->id();

        if ($actorId <= 0) {
            throw new AuthorizationException(
                'Debes iniciar sesión.'
            );
        }

        $decision =
            (string) (
                $data[
                    'customer_decision'
                ]
                ?? ''
            );

        if (
            ! array_key_exists(
                $decision,
                self::DECISIONS
            )
        ) {
            throw ValidationException::
                withMessages([
                    'customer_decision' =>
                        'Selecciona la respuesta '
                        . 'del cliente.',
                ]);
        }

        $channel =
            (string) (
                $data[
                    'customer_decision_channel'
                ]
                ?? ''
            );

        if (
            ! array_key_exists(
                $channel,
                self::CHANNELS
            )
        ) {
            throw ValidationException::
                withMessages([
                    'customer_decision_channel' =>
                        'Selecciona cómo confirmó '
                        . 'el cliente.',
                ]);
        }

        $notes =
            trim(
                (string) (
                    $data[
                        'customer_decision_notes'
                    ]
                    ?? ''
                )
            );

        if ($notes === '') {
            throw ValidationException::
                withMessages([
                    'customer_decision_notes' =>
                        'Captura la observación '
                        . 'de la respuesta.',
                ]);
        }

        $decisionAt =
            filled(
                $data[
                    'customer_decision_at'
                ]
                ?? null
            )
                ? Carbon::parse(
                    $data[
                        'customer_decision_at'
                    ]
                )
                : now();

        if (
            $decisionAt->greaterThan(
                now()->addMinutes(5)
            )
        ) {
            throw ValidationException::
                withMessages([
                    'customer_decision_at' =>
                        'La fecha de respuesta '
                        . 'no puede estar en el futuro.',
                ]);
        }

        return DB::transaction(
            function () use (
                $repairOrder,
                $decision,
                $channel,
                $notes,
                $decisionAt,
                $actorId
            ): RepairOrder {
                $repair =
                    RepairOrder::query()
                        ->whereKey(
                            $repairOrder->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    ! ServiceAccess::
                        canRecordPostRepairCustomerDecision(
                            $repair
                        )
                ) {
                    throw new
                        AuthorizationException(
                            'La orden ya no está '
                            . 'disponible para registrar '
                            . 'el Vo.Bo. del cliente.'
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

                $existingApproval =
                    $metadata[
                        'customer_approval'
                    ]
                    ?? [];

                if (
                    is_array(
                        $existingApproval
                    )
                    && in_array(
                        (
                            $existingApproval[
                                'status'
                            ]
                            ?? null
                        ),
                        [
                            'approved',
                            'rejected',
                        ],
                        true
                    )
                ) {
                    throw ValidationException::
                        withMessages([
                            'customer_decision' =>
                                'El Vo.Bo. del cliente '
                                . 'ya fue registrado.',
                        ]);
                }

                $technicalWork =
                    $metadata[
                        'technical_work'
                    ]
                    ?? [];

                $managerReview =
                    $metadata[
                        'manager_review'
                    ]
                    ?? [];

                if (
                    ! is_array(
                        $technicalWork
                    )
                    || (
                        $technicalWork[
                            'status'
                        ]
                        ?? null
                    ) !== 'completed'
                ) {
                    throw ValidationException::
                        withMessages([
                            'customer_decision' =>
                                'El trabajo técnico '
                                . 'no está finalizado.',
                        ]);
                }

                if (
                    ! is_array(
                        $managerReview
                    )
                    || (
                        $managerReview[
                            'status'
                        ]
                        ?? null
                    ) !== 'completed'
                ) {
                    throw ValidationException::
                        withMessages([
                            'customer_decision' =>
                                'La revisión y costeo '
                                . 'no están terminados.',
                        ]);
                }

                $quoteTotal =
                    round(
                        (float) (
                            $repair->quote_total
                            ?? 0
                        ),
                        2
                    );

                $managerTotal =
                    round(
                        (float) (
                            $managerReview[
                                'quote_total'
                            ]
                            ?? 0
                        ),
                        2
                    );

                if (
                    $quoteTotal <= 0
                    || abs(
                        $quoteTotal
                        - $managerTotal
                    ) > 0.01
                ) {
                    throw ValidationException::
                        withMessages([
                            'customer_decision' =>
                                'El importe cambió '
                                . 'desde el costeo. '
                                . 'Recarga antes de continuar.',
                        ]);
                }

                $oldStage =
                    (string) (
                        $repair->
                            workflow_stage
                        ?? ''
                    );

                $oldStatus =
                    (string) (
                        $repair->status
                        ?? ''
                    );

                $oldQuoteStatus =
                    (string) (
                        $repair->quote_status
                        ?? ''
                    );

                $history =
                    $metadata[
                        'customer_approval_history'
                    ]
                    ?? [];

                if (
                    ! is_array(
                        $history
                    )
                ) {
                    $history = [];
                }

                $entry = [
                    'status' =>
                        $decision === 'approved'
                            ? 'approved'
                            : 'rejected',

                    'decision' =>
                        $decision,

                    'channel' =>
                        $channel,

                    'channel_label' =>
                        self::CHANNELS[
                            $channel
                        ],

                    'notes' =>
                        $notes,

                    'authorized_amount' =>
                        $quoteTotal,

                    'decision_at' =>
                        $decisionAt->
                            toDateTimeString(),

                    'recorded_at' =>
                        now()->
                            toDateTimeString(),

                    'recorded_by_user_id' =>
                        $actorId,

                    'manager_review_completed_at' =>
                        $managerReview[
                            'completed_at'
                        ]
                        ?? null,

                    'source' =>
                        'post_repair_customer_approval',
                ];

                $history[] =
                    $entry;

                $metadata[
                    'customer_approval'
                ] =
                    $entry;

                $metadata[
                    'customer_approval_history'
                ] =
                    $history;

                /*
                 * Compatibilidad de lectura con el
                 * historial legacy, sin utilizar sus
                 * transiciones de workflow.
                 */
                $legacyEntry = [
                    'decision' =>
                        $decision,

                    'channel' =>
                        $channel,

                    'notes' =>
                        $notes,

                    'decision_at' =>
                        $decisionAt->
                            toDateTimeString(),

                    'recorded_at' =>
                        now()->
                            toDateTimeString(),

                    'recorded_by' =>
                        $actorId,

                    'authorized_amount' =>
                        $quoteTotal,

                    'post_repair' =>
                        true,
                ];

                $legacyHistory =
                    $metadata[
                        'customer_quote_decision_history'
                    ]
                    ?? [];

                if (
                    ! is_array(
                        $legacyHistory
                    )
                ) {
                    $legacyHistory = [];
                }

                $legacyHistory[] =
                    $legacyEntry;

                $metadata[
                    'customer_quote_decision'
                ] =
                    $legacyEntry;

                $metadata[
                    'customer_quote_decision_history'
                ] =
                    $legacyHistory;

                if (
                    $decision === 'approved'
                ) {
                    /*
                     * Se satisface el Vo.Bo.,
                     * PERO NO avanzamos todavía a:
                     * - ready_for_delivery
                     * - CxC
                     * - delivered
                     *
                     * C5E decidirá el siguiente paso.
                     */
                    $managerReview[
                        'requires_customer_approval'
                    ] = false;

                    $managerReview[
                        'customer_approval_status'
                    ] = 'approved';

                    $managerReview[
                        'customer_approval_satisfied_at'
                    ] =
                        $decisionAt->
                            toDateTimeString();

                    $managerReview[
                        'authorized_total'
                    ] =
                        $quoteTotal;

                    $metadata[
                        'manager_review'
                    ] =
                        $managerReview;

                    $repair->update([
                        'quote_status' =>
                            'customer_approved',

                        'requires_customer_approval' =>
                            false,

                        'quote_approved_at' =>
                            $decisionAt,

                        'customer_approved_at' =>
                            $decisionAt,

                        'customer_rejected_at' =>
                            null,

                        'approved_total_snapshot' =>
                            $quoteTotal,

                        'metadata' =>
                            $metadata,
                    ]);

                    $eventType =
                        'customer_post_repair_approval_recorded';

                    $newQuoteStatus =
                        'customer_approved';
                } else {
                    /*
                     * El rechazo NO borra:
                     * - diagnóstico
                     * - trabajo
                     * - refacciones
                     * - costo
                     * - precio
                     * - total rechazado
                     *
                     * Reabre exclusivamente la revisión
                     * económica del Encargado.
                     */
                    $managerReview[
                        'previous_status'
                    ] =
                        $managerReview[
                            'status'
                        ]
                        ?? 'completed';

                    $managerReview[
                        'status'
                    ] =
                        'needs_revision';

                    $managerReview[
                        'requires_customer_approval'
                    ] =
                        false;

                    $managerReview[
                        'customer_approval_status'
                    ] =
                        'rejected';

                    $managerReview[
                        'reopened_reason'
                    ] =
                        'customer_rejected';

                    $managerReview[
                        'reopened_at'
                    ] =
                        now()->
                            toDateTimeString();

                    $managerReview[
                        'reopened_by_user_id'
                    ] =
                        $actorId;

                    $metadata[
                        'manager_review'
                    ] =
                        $managerReview;

                    $repair->update([
                        'quote_status' =>
                            'customer_rejected',

                        'requires_customer_approval' =>
                            false,

                        'quote_approved_at' =>
                            null,

                        'customer_approved_at' =>
                            null,

                        'customer_rejected_at' =>
                            $decisionAt,

                        'approved_total_snapshot' =>
                            null,

                        'metadata' =>
                            $metadata,
                    ]);

                    $eventType =
                        'customer_post_repair_rejection_recorded';

                    $newQuoteStatus =
                        'customer_rejected';
                }

                /*
                 * status y workflow_stage se preservan.
                 */
                $repair->refresh();

                if (
                    (string) $repair->status
                    !== $oldStatus
                    || (string) $repair->
                        workflow_stage
                    !== $oldStage
                ) {
                    throw new RuntimeException(
                        'El Vo.Bo. post-reparación '
                        . 'no debe modificar status '
                        . 'ni workflow_stage.'
                    );
                }

                $this->
                    logPostRepairDecision(
                        repair: $repair,
                        eventType: $eventType,
                        actorId: $actorId,
                        decision: $decision,
                        channel: $channel,
                        notes: $notes,
                        decisionAt: $decisionAt,
                        amount: $quoteTotal,
                        oldStage: $oldStage,
                        oldStatus: $oldStatus,
                        oldQuoteStatus:
                            $oldQuoteStatus,
                        newQuoteStatus:
                            $newQuoteStatus
                    );

                return
                    $repair->fresh();
            }
        );
    }

    protected function logPostRepairDecision(
        RepairOrder $repair,
        string $eventType,
        int $actorId,
        string $decision,
        string $channel,
        string $notes,
        Carbon $decisionAt,
        float $amount,
        string $oldStage,
        string $oldStatus,
        string $oldQuoteStatus,
        string $newQuoteStatus
    ): void {
        if (
            ! Schema::hasTable(
                'service_case_events'
            )
        ) {
            return;
        }

        $columns =
            Schema::getColumnListing(
                'service_case_events'
            );

        $now = now();

        $description =
            self::DECISIONS[
                $decision
            ]
            . ' vía '
            . self::CHANNELS[
                $channel
            ]
            . ' por $'
            . number_format(
                $amount,
                2
            )
            . '. '
            . $notes;

        $row = [
            'company_id' =>
                $repair->company_id,

            'service_case_id' =>
                $repair->service_case_id,

            'repair_order_id' =>
                $repair->id,

            'event_type' =>
                $eventType,

            /*
             * Se preserva el estado operativo:
             * recibido -> recibido.
             */
            'from_status' =>
                $oldStatus,

            'to_status' =>
                $oldStatus,

            'performed_by' =>
                $actorId,

            'performed_at' =>
                $now,

            'notes' =>
                $description,

            'old_values' =>
                json_encode(
                    [
                        'workflow_stage' =>
                            $oldStage,

                        'status' =>
                            $oldStatus,

                        'quote_status' =>
                            $oldQuoteStatus,

                        'requires_customer_approval' =>
                            true,
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

            'new_values' =>
                json_encode(
                    [
                        'workflow_stage' =>
                            $oldStage,

                        'status' =>
                            $oldStatus,

                        'quote_status' =>
                            $newQuoteStatus,

                        'requires_customer_approval' =>
                            false,

                        'customer_decision' =>
                            $decision,

                        'customer_decision_channel' =>
                            $channel,

                        'customer_decision_at' =>
                            $decisionAt->
                                toDateTimeString(),

                        'authorized_amount' =>
                            $amount,
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

            'metadata' =>
                json_encode(
                    [
                        'source' =>
                            'post_repair_customer_approval',

                        'decision' =>
                            $decision,

                        'channel' =>
                            $channel,

                        'amount' =>
                            $amount,

                        'recorded_by' =>
                            $actorId,
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

            'ip_address' =>
                request()?->ip(),

            'user_agent' =>
                request()?->userAgent(),

            'created_at' =>
                $now,

            'updated_at' =>
                $now,
        ];

        $safe =
            array_intersect_key(
                $row,
                array_flip(
                    $columns
                )
            );

        DB::table(
            'service_case_events'
        )->insert(
            $safe
        );
    }


    public function recordDecision(
        RepairOrder $repairOrder,
        array $data
    ): RepairOrder {
        if (
            ! ServiceAccess::canRecordRepairCustomerDecision(
                $repairOrder
            )
        ) {
            throw new AuthorizationException(
                'No tienes permiso para registrar el VoBo del cliente.'
            );
        }

        $actorId = (int) auth()->id();

        if ($actorId <= 0) {
            throw new AuthorizationException(
                'Debes iniciar sesión.'
            );
        }

        $decision = (string) (
            $data['customer_decision'] ?? ''
        );

        if (
            ! array_key_exists(
                $decision,
                self::DECISIONS
            )
        ) {
            throw ValidationException::withMessages([
                'customer_decision' =>
                    'Selecciona la respuesta del cliente.',
            ]);
        }

        $channel = (string) (
            $data['customer_decision_channel'] ?? ''
        );

        if (
            ! array_key_exists(
                $channel,
                self::CHANNELS
            )
        ) {
            throw ValidationException::withMessages([
                'customer_decision_channel' =>
                    'Selecciona cómo confirmó el cliente.',
            ]);
        }

        $notes = trim((string) (
            $data['customer_decision_notes'] ?? ''
        ));

        if ($notes === '') {
            throw ValidationException::withMessages([
                'customer_decision_notes' =>
                    'Captura la observación de la respuesta del cliente.',
            ]);
        }

        $decisionAt = filled(
            $data['customer_decision_at'] ?? null
        )
            ? Carbon::parse(
                $data['customer_decision_at']
            )
            : now();

        if ($decisionAt->greaterThan(now()->addMinutes(5))) {
            throw ValidationException::withMessages([
                'customer_decision_at' =>
                    'La fecha de respuesta no puede estar en el futuro.',
            ]);
        }

        return DB::transaction(function () use (
            $repairOrder,
            $decision,
            $channel,
            $notes,
            $decisionAt,
            $actorId
        ): RepairOrder {
            $repair = RepairOrder::query()
                ->whereKey($repairOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                ! ServiceAccess::canRecordRepairCustomerDecision(
                    $repair
                )
            ) {
                throw new AuthorizationException(
                    'La cotización ya no está disponible para registrar respuesta del cliente.'
                );
            }

            $oldStage = (string) (
                $repair->workflow_stage ?? ''
            );

            $oldStatus = (string) (
                $repair->status ?? ''
            );

            $oldQuoteStatus = (string) (
                $repair->quote_status ?? ''
            );

            $metadata = (array) (
                $repair->metadata ?? []
            );

            $history = $metadata[
                'customer_quote_decision_history'
            ] ?? [];

            if (! is_array($history)) {
                $history = [];
            }

            $historyEntry = [
                'decision' => $decision,
                'channel' => $channel,
                'notes' => $notes,
                'decision_at' =>
                    $decisionAt->toDateTimeString(),
                'recorded_at' =>
                    now()->toDateTimeString(),
                'recorded_by' => $actorId,
            ];

            $history[] = $historyEntry;

            $metadata[
                'customer_quote_decision'
            ] = $historyEntry;

            $metadata[
                'customer_quote_decision_history'
            ] = $history;

            if ($decision === 'approved') {
                $repair->update([
                    'workflow_stage' =>
                        'quote_approved',

                    'status' =>
                        'approved_pending_repair',

                    'quote_status' =>
                        'customer_approved',

                    'quote_approved_at' =>
                        $decisionAt,

                    'customer_approved_at' =>
                        $decisionAt,

                    'customer_rejected_at' =>
                        null,

                    'metadata' =>
                        $metadata,
                ]);

                $newStage = 'quote_approved';
                $newStatus =
                    'approved_pending_repair';
                $newQuoteStatus =
                    'customer_approved';

                $eventType =
                    'customer_quote_approved_recorded';
            } else {
                /*
                 * El cliente rechazó.
                 *
                 * Regresamos a borrador para permitir
                 * modificar la cotización y volver a
                 * enviarla al flujo interno.
                 */
                $repair->update([
                    'workflow_stage' =>
                        'quote_draft',

                    'status' =>
                        'cotizacion_pendiente',

                    'quote_status' =>
                        'customer_rejected',

                    'quote_approved_at' =>
                        null,

                    'customer_approved_at' =>
                        null,

                    'customer_rejected_at' =>
                        $decisionAt,

                    'metadata' =>
                        $metadata,
                ]);

                $newStage = 'quote_draft';
                $newStatus =
                    'cotizacion_pendiente';
                $newQuoteStatus =
                    'customer_rejected';

                $eventType =
                    'customer_quote_rejected_recorded';
            }

            $this->logDecision(
                repair: $repair,
                eventType: $eventType,
                actorId: $actorId,
                decision: $decision,
                channel: $channel,
                notes: $notes,
                decisionAt: $decisionAt,
                oldStage: $oldStage,
                newStage: $newStage,
                oldStatus: $oldStatus,
                newStatus: $newStatus,
                oldQuoteStatus: $oldQuoteStatus,
                newQuoteStatus: $newQuoteStatus
            );

            return $repair->fresh();
        });
    }

    protected function logDecision(
        RepairOrder $repair,
        string $eventType,
        int $actorId,
        string $decision,
        string $channel,
        string $notes,
        Carbon $decisionAt,
        string $oldStage,
        string $newStage,
        string $oldStatus,
        string $newStatus,
        string $oldQuoteStatus,
        string $newQuoteStatus
    ): void {
        if (
            ! Schema::hasTable(
                'service_case_events'
            )
        ) {
            return;
        }

        $columns = Schema::getColumnListing(
            'service_case_events'
        );

        $now = now();

        $description =
            self::DECISIONS[$decision]
            . ' vía '
            . self::CHANNELS[$channel]
            . '. '
            . $notes;

        $row = [
            'company_id' =>
                $repair->company_id,

            'service_case_id' =>
                $repair->service_case_id,

            'repair_order_id' =>
                $repair->id,

            'event_type' =>
                $eventType,

            'from_status' =>
                $oldStatus,

            'to_status' =>
                $newStatus,

            'performed_by' =>
                $actorId,

            'performed_at' =>
                $now,

            'description' =>
                $description,

            'notes' =>
                $description,

            'old_values' => json_encode([
                'workflow_stage' =>
                    $oldStage,

                'status' =>
                    $oldStatus,

                'quote_status' =>
                    $oldQuoteStatus,
            ], JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES),

            'new_values' => json_encode([
                'workflow_stage' =>
                    $newStage,

                'status' =>
                    $newStatus,

                'quote_status' =>
                    $newQuoteStatus,

                'customer_decision' =>
                    $decision,

                'customer_decision_channel' =>
                    $channel,

                'customer_decision_at' =>
                    $decisionAt
                        ->toDateTimeString(),
            ], JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES),

            'metadata' => json_encode([
                'source' =>
                    'repair_customer_decision',

                'decision' =>
                    $decision,

                'channel' =>
                    $channel,

                'decision_at' =>
                    $decisionAt
                        ->toDateTimeString(),

                'recorded_by' =>
                    $actorId,
            ], JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES),

            'created_at' => $now,
            'updated_at' => $now,
        ];

        $safe = array_intersect_key(
            $row,
            array_flip($columns)
        );

        DB::table('service_case_events')
            ->insert($safe);
    }
}
