<?php

namespace App\Support\Service;

use App\Models\RepairOrder;
use App\Models\ServiceCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ServiceRepairCaseLifecycleService
{
    public function closeCaseAfterDelivery(
        int|RepairOrder $repairOrder
    ): array {
        $repairId = $repairOrder instanceof RepairOrder
            ? (int) $repairOrder->getKey()
            : (int) $repairOrder;

        if ($repairId <= 0) {
            throw ValidationException::withMessages([
                'repair' =>
                    'No se encontró la reparación.',
            ]);
        }

        return DB::transaction(function () use (
            $repairId
        ): array {
            $repair = RepairOrder::query()
                ->whereKey($repairId)
                ->lockForUpdate()
                ->firstOrFail();

            $stage = (string) (
                $repair->workflow_stage ?? ''
            );

            $status = (string) (
                $repair->status ?? ''
            );

            if (
                $stage !== 'delivered'
                && ! in_array(
                    $status,
                    ['delivered', 'entregado'],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'repair' =>
                        'La reparación todavía no ha sido entregada.',
                ]);
            }

            $serviceCaseId =
                (int) ($repair->service_case_id ?? 0);

            if (
                $serviceCaseId <= 0
                || ! Schema::hasTable('service_cases')
            ) {
                return [
                    'updated' => false,
                    'reason' =>
                        'without_service_case',
                    'service_case_id' => null,
                ];
            }

            $case = ServiceCase::query()
                ->whereKey($serviceCaseId)
                ->lockForUpdate()
                ->first();

            if (! $case) {
                return [
                    'updated' => false,
                    'reason' =>
                        'service_case_not_found',
                    'service_case_id' =>
                        $serviceCaseId,
                ];
            }

            if (
                (string) (
                    $case->attention_route ?? ''
                ) !== 'repair'
            ) {
                return [
                    'updated' => false,
                    'reason' =>
                        'service_case_not_repair',
                    'service_case_id' =>
                        $serviceCaseId,
                ];
            }

            $oldStatus =
                (string) ($case->status ?? '');

            $wasClosed =
                $oldStatus === 'cerrado'
                && $case->closed_at !== null;

            if ($wasClosed) {
                return [
                    'updated' => false,
                    'reason' =>
                        'already_closed',
                    'service_case_id' =>
                        $serviceCaseId,
                    'status' => 'cerrado',
                ];
            }

            $now = now();

            $case->update([
                'status' => 'cerrado',
                'closed_at' =>
                    $case->closed_at ?: $now,
                'closed_by' =>
                    $case->closed_by
                    ?: auth()->id(),
            ]);

            $this->logEvent(
                case: $case,
                repair: $repair,
                fromStatus: $oldStatus,
                actorId: auth()->id(),
                notes:
                    'Ticket ATC cerrado automáticamente al entregar la reparación al cliente.'
            );

            return [
                'updated' => true,
                'reason' =>
                    'closed_after_repair_delivery',
                'service_case_id' =>
                    $serviceCaseId,
                'old_status' =>
                    $oldStatus,
                'new_status' =>
                    'cerrado',
                'closed_at' =>
                    (string) $case->fresh()->closed_at,
            ];
        });
    }

    public function reopenAfterDelivery(
        int|RepairOrder $repairOrder,
        string $reason
    ): array {
        $repairId =
            $repairOrder instanceof RepairOrder
                ? (int) $repairOrder->getKey()
                : (int) $repairOrder;

        if ($repairId <= 0) {
            throw ValidationException::withMessages([
                'repair' =>
                    'No se encontró la reparación.',
            ]);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' =>
                    'Captura el motivo de reapertura.',
            ]);
        }

        if (
            ! ServiceAccess::can(
                'service.repairs.reopen'
            )
        ) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'No tienes permiso para reabrir reparaciones.'
            );
        }

        return DB::transaction(function () use (
            $repairId,
            $reason
        ): array {
            $repair = RepairOrder::query()
                ->whereKey($repairId)
                ->lockForUpdate()
                ->firstOrFail();

            $oldStage = (string) (
                $repair->workflow_stage ?? ''
            );

            $oldStatus = (string) (
                $repair->status ?? ''
            );

            $isDelivered =
                $oldStage === 'delivered'
                || in_array(
                    $oldStatus,
                    [
                        'delivered',
                        'entregado',
                        'cerrado',
                    ],
                    true
                );

            if (! $isDelivered) {
                throw ValidationException::withMessages([
                    'repair' =>
                        'Solo se puede reabrir una reparación entregada.',
                ]);
            }

            $actorId = auth()->id();
            $now = now();

            /*
             * Conservamos una fotografía completa del
             * ciclo anterior antes de limpiar los campos
             * operativos para la nueva intervención.
             */
            $metadata = (array) (
                $repair->metadata ?? []
            );

            $history = $metadata[
                'repair_reopen_history'
            ] ?? [];

            if (! is_array($history)) {
                $history = [];
            }

            $historyEntry = [
                'reopened_at' =>
                    $now->toDateTimeString(),

                'reopened_by' =>
                    $actorId,

                'reason' =>
                    $reason,

                'previous_workflow_stage' =>
                    $oldStage,

                'previous_status' =>
                    $oldStatus,

                'previous_quote_status' =>
                    $repair->quote_status,

                'previous_repair_started_at' =>
                    $repair->repair_started_at
                        ? (string)
                            $repair->repair_started_at
                        : null,

                'previous_repair_finished_at' =>
                    $repair->repair_finished_at
                        ? (string)
                            $repair->repair_finished_at
                        : null,

                'previous_ready_for_delivery_at' =>
                    $repair->ready_for_delivery_at
                        ? (string)
                            $repair->ready_for_delivery_at
                        : null,

                'previous_delivered_at' =>
                    $repair->delivered_at
                        ? (string)
                            $repair->delivered_at
                        : null,

                'previous_delivered_to' =>
                    $repair->delivered_to,

                'previous_delivery_notes' =>
                    $repair->delivery_notes,

                'previous_actual_labor_hours' =>
                    $repair->actual_labor_hours,

                'previous_actual_labor_cost' =>
                    $repair->actual_labor_cost,

                'economic_status' =>
                    $repair->economic_status,

                'economic_payment_status' =>
                    $repair->economic_payment_status,

                'account_receivable_id' =>
                    $repair->account_receivable_id,
            ];

            $history[] = $historyEntry;

            $metadata[
                'repair_reopen_history'
            ] = $history;

            $metadata[
                'last_reopen'
            ] = $historyEntry;

            /*
             * Nueva intervención operativa.
             *
             * No tocamos:
             * - quote_status / VoBo cliente
             * - economic_status
             * - economic_payment_status
             * - account_receivable_id
             * - CxC
             * - pagos
             * - evidencias
             * - firmas
             */
            $repair->update([
                'workflow_stage' =>
                    'in_repair',

                'status' =>
                    'in_repair',

                'repair_started_at' =>
                    $now,

                'repair_finished_at' =>
                    null,

                'ready_for_delivery_at' =>
                    null,

                'delivered_at' =>
                    null,

                'closed_at' =>
                    null,

                'delivered_to' =>
                    null,

                'delivery_notes' =>
                    null,

                'actual_labor_hours' =>
                    null,

                'actual_labor_cost' =>
                    null,

                'metadata' =>
                    $metadata,
            ]);

            $serviceCaseId =
                (int) (
                    $repair->service_case_id
                    ?? 0
                );

            $case = null;
            $oldCaseStatus = null;

            if (
                $serviceCaseId > 0
                && Schema::hasTable(
                    'service_cases'
                )
            ) {
                $case = ServiceCase::query()
                    ->whereKey($serviceCaseId)
                    ->lockForUpdate()
                    ->first();

                if (
                    $case
                    && (string) (
                        $case->attention_route
                        ?? ''
                    ) === 'repair'
                ) {
                    $oldCaseStatus =
                        (string) (
                            $case->status
                            ?? ''
                        );

                    $case->update([
                        'status' =>
                            'en_diagnostico',

                        'closed_at' =>
                            null,

                        'closed_by' =>
                            null,
                    ]);
                }
            }

            $repair->refresh();

            $this->logReopenEvent(
                repair: $repair,
                case: $case,
                eventType:
                    'reparacion_reabierta',
                fromStatus:
                    $oldStatus,
                toStatus:
                    'in_repair',
                actorId:
                    $actorId,
                reason:
                    $reason,
                metadata: [
                    'from_workflow_stage' =>
                        $oldStage,

                    'to_workflow_stage' =>
                        'in_repair',

                    'cxc_unchanged' =>
                        true,

                    'account_receivable_id' =>
                        $repair
                            ->account_receivable_id,
                ]
            );

            if ($case) {
                $this->logReopenEvent(
                    repair: $repair,
                    case: $case,
                    eventType:
                        'ticket_reabierto_por_reparacion',
                    fromStatus:
                        $oldCaseStatus,
                    toStatus:
                        'en_diagnostico',
                    actorId:
                        $actorId,
                    reason:
                        $reason,
                    metadata: [
                        'source' =>
                            'repair_reopen',

                        'cxc_unchanged' =>
                            true,
                    ]
                );
            }

            return [
                'updated' =>
                    true,

                'repair_order_id' =>
                    $repair->id,

                'service_case_id' =>
                    $serviceCaseId ?: null,

                'old_workflow_stage' =>
                    $oldStage,

                'new_workflow_stage' =>
                    'in_repair',

                'old_status' =>
                    $oldStatus,

                'new_status' =>
                    'in_repair',

                'old_case_status' =>
                    $oldCaseStatus,

                'new_case_status' =>
                    $case
                        ? 'en_diagnostico'
                        : null,

                'account_receivable_id' =>
                    $repair->account_receivable_id,

                'cxc_modified' =>
                    false,

                'history_preserved' =>
                    true,
            ];
        });
    }

    protected function logReopenEvent(
        RepairOrder $repair,
        ?ServiceCase $case,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $actorId,
        string $reason,
        array $metadata = []
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
                $fromStatus,

            'to_status' =>
                $toStatus,

            'performed_by' =>
                $actorId,

            'performed_at' =>
                $now,

            'description' =>
                $reason,

            'notes' =>
                $reason,

            'old_values' =>
                json_encode(
                    [
                        'status' =>
                            $fromStatus,
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

            'new_values' =>
                json_encode(
                    [
                        'status' =>
                            $toStatus,
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

            'metadata' =>
                json_encode(
                    array_merge(
                        [
                            'source' =>
                                'repair_reopen',
                        ],
                        $metadata
                    ),
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

            'created_at' =>
                $now,

            'updated_at' =>
                $now,
        ];

        $safe = array_intersect_key(
            $row,
            array_flip($columns)
        );

        DB::table(
            'service_case_events'
        )->insert($safe);
    }

    protected function logEvent(
        ServiceCase $case,
        RepairOrder $repair,
        ?string $fromStatus,
        ?int $actorId,
        string $notes
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

        $row = [
            'company_id' =>
                $case->company_id,

            'service_case_id' =>
                $case->id,

            'repair_order_id' =>
                $repair->id,

            'event_type' =>
                'ticket_cerrado_por_entrega_reparacion',

            'from_status' =>
                $fromStatus,

            'to_status' =>
                'cerrado',

            'performed_by' =>
                $actorId,

            'performed_at' =>
                $now,

            'notes' =>
                $notes,

            'old_values' =>
                json_encode(
                    [
                        'status' =>
                            $fromStatus,
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

            'new_values' =>
                json_encode(
                    [
                        'status' =>
                            'cerrado',
                        'repair_order_id' =>
                            $repair->id,
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

            'metadata' =>
                json_encode(
                    [
                        'source' =>
                            'repair_delivery',
                        'economic_status_ignored' =>
                            true,
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

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
