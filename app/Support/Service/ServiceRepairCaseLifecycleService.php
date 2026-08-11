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
