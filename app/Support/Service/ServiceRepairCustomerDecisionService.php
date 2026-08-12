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
