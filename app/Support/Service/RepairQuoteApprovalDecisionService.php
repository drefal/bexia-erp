<?php

namespace App\Support\Service;

use App\Models\RepairOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RepairQuoteApprovalDecisionService
{
    public const DOCUMENT_TYPE = 'service_repair_quote_internal';

    public static function pendingContextForUser(
        RepairOrder $repair,
        int $userId
    ): ?array {
        if ($userId <= 0) {
            return null;
        }

        $request = DB::table('approval_requests')
            ->where('company_id', $repair->company_id)
            ->where('approvable_id', $repair->id)
            ->where('document_type', self::DOCUMENT_TYPE)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();

        if (! $request) {
            return null;
        }

        $step = DB::table('approval_request_steps')
            ->where('approval_request_id', $request->id)
            ->where('step_order', $request->current_step_order)
            ->where('status', 'pending')
            ->where('approver_user_id', $userId)
            ->first();

        if (! $step) {
            return null;
        }

        return [
            'request' => $request,
            'step' => $step,
        ];
    }

    public static function canCurrentUserDecide(
        RepairOrder $repair,
        int $userId
    ): bool {
        return (string) ($repair->workflow_stage ?? '') === 'pending_approval'
            && self::pendingContextForUser($repair, $userId) !== null;
    }

    public static function approve(
        RepairOrder $repair,
        object $user
    ): array {
        return DB::transaction(function () use ($repair, $user): array {
            [$request, $step] = self::lockCurrentContext(
                $repair,
                (int) $user->id
            );

            $comment = 'Presupuesto de reparación aprobado internamente.';

            $stepUpdate = [
                'status' => 'approved',
                'acted_by_user_id' => $user->id,
                'acted_by_name' => $user->name ?? $user->email ?? 'Usuario',
                'acted_at' => now(),
                'comments' => $comment,
                'updated_at' => now(),
            ];

            if (
                Schema::hasColumn(
                    'approval_request_steps',
                    'decision_reason'
                )
            ) {
                $stepUpdate['decision_reason'] = $comment;
            }

            DB::table('approval_request_steps')
                ->where('id', $step->id)
                ->update($stepUpdate);

            $nextStep = DB::table('approval_request_steps')
                ->where('approval_request_id', $request->id)
                ->where('step_order', '>', $step->step_order)
                ->whereIn('status', ['pending', 'waiting'])
                ->orderBy('step_order')
                ->orderBy('id')
                ->first();

            if ($nextStep) {
                DB::table('approval_request_steps')
                    ->where('id', $nextStep->id)
                    ->update([
                        'status' => 'pending',
                        'updated_at' => now(),
                    ]);

                $requestUpdate = [
                    'current_step_order' => $nextStep->step_order,
                    'updated_at' => now(),
                ];

                if (
                    Schema::hasColumn(
                        'approval_requests',
                        'last_decision_reason'
                    )
                ) {
                    $requestUpdate['last_decision_reason'] = $comment;
                }

                DB::table('approval_requests')
                    ->where('id', $request->id)
                    ->update($requestUpdate);

                self::logEvent(
                    $repair,
                    $user,
                    'presupuesto_aprobado_parcialmente',
                    'pending_approval',
                    'pending_approval',
                    $comment,
                    (int) $request->id
                );

                return [
                    'completed' => false,
                    'request_id' => (int) $request->id,
                ];
            }

            $requestUpdate = [
                'status' => 'approved',
                'completed_at' => now(),
                'updated_at' => now(),
            ];

            if (
                Schema::hasColumn(
                    'approval_requests',
                    'last_decision_reason'
                )
            ) {
                $requestUpdate['last_decision_reason'] = $comment;
            }

            DB::table('approval_requests')
                ->where('id', $request->id)
                ->update($requestUpdate);

            self::syncServiceApproval(
                $repair,
                'approved',
                $user,
                $comment
            );

            if ((bool) ($repair->requires_customer_approval ?? false)) {
                DB::table('repair_orders')
                    ->where('id', $repair->id)
                    ->update([
                        'quote_status' => 'pending_customer',
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('repair_orders')
                    ->where('id', $repair->id)
                    ->update([
                        'workflow_stage' => 'quote_approved',
                        'status' => 'approved_pending_repair',
                        'quote_status' => 'not_required',
                        'quote_approved_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            self::logEvent(
                $repair,
                $user,
                'presupuesto_aprobado_internamente',
                'pending_approval',
                (bool) ($repair->requires_customer_approval ?? false)
                    ? 'pending_customer'
                    : 'quote_approved',
                $comment,
                (int) $request->id
            );

            return [
                'completed' => true,
                'request_id' => (int) $request->id,
            ];
        });
    }

    public static function reject(
        RepairOrder $repair,
        object $user,
        string $reason
    ): void {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw new RuntimeException(
                'Captura un motivo de rechazo de al menos 5 caracteres.'
            );
        }

        DB::transaction(function () use ($repair, $user, $reason): void {
            [$request, $step] = self::lockCurrentContext(
                $repair,
                (int) $user->id
            );

            $stepUpdate = [
                'status' => 'rejected',
                'acted_by_user_id' => $user->id,
                'acted_by_name' => $user->name ?? $user->email ?? 'Usuario',
                'acted_at' => now(),
                'comments' => $reason,
                'updated_at' => now(),
            ];

            if (
                Schema::hasColumn(
                    'approval_request_steps',
                    'decision_reason'
                )
            ) {
                $stepUpdate['decision_reason'] = $reason;
            }

            DB::table('approval_request_steps')
                ->where('id', $step->id)
                ->update($stepUpdate);

            DB::table('approval_request_steps')
                ->where('approval_request_id', $request->id)
                ->whereIn('status', ['pending', 'waiting'])
                ->update([
                    'status' => 'skipped',
                    'updated_at' => now(),
                ]);

            $requestUpdate = [
                'status' => 'rejected',
                'completed_at' => now(),
                'updated_at' => now(),
            ];

            if (
                Schema::hasColumn(
                    'approval_requests',
                    'last_decision_reason'
                )
            ) {
                $requestUpdate['last_decision_reason'] = $reason;
            }

            DB::table('approval_requests')
                ->where('id', $request->id)
                ->update($requestUpdate);

            self::syncServiceApproval(
                $repair,
                'rejected',
                $user,
                $reason
            );

            DB::table('repair_orders')
                ->where('id', $repair->id)
                ->update([
                    'workflow_stage' => 'quote_draft',
                    'status' => 'en_diagnostico',
                    'quote_status' => 'draft',
                    'quote_approved_at' => null,
                    'updated_at' => now(),
                ]);

            self::logEvent(
                $repair,
                $user,
                'presupuesto_rechazado_internamente',
                'pending_approval',
                'quote_draft',
                $reason,
                (int) $request->id
            );
        });
    }

    protected static function lockCurrentContext(
        RepairOrder $repair,
        int $userId
    ): array {
        $request = DB::table('approval_requests')
            ->where('company_id', $repair->company_id)
            ->where('approvable_id', $repair->id)
            ->where('document_type', self::DOCUMENT_TYPE)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (! $request) {
            throw new RuntimeException(
                'La solicitud de aprobación ya no está pendiente.'
            );
        }

        $step = DB::table('approval_request_steps')
            ->where('approval_request_id', $request->id)
            ->where('step_order', $request->current_step_order)
            ->where('status', 'pending')
            ->lockForUpdate()
            ->first();

        if (! $step) {
            throw new RuntimeException(
                'No existe una etapa pendiente para esta aprobación.'
            );
        }

        if ((int) ($step->approver_user_id ?? 0) !== $userId) {
            throw new RuntimeException(
                'Tu usuario no es el aprobador asignado a esta etapa.'
            );
        }

        return [$request, $step];
    }

    protected static function syncServiceApproval(
        RepairOrder $repair,
        string $status,
        object $user,
        string $comments
    ): void {
        if (! Schema::hasTable('repair_order_approvals')) {
            return;
        }

        $approval = DB::table('repair_order_approvals')
            ->where('repair_order_id', $repair->id)
            ->where('approval_type', self::DOCUMENT_TYPE)
            ->whereIn('status', ['pending', 'pendiente'])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (! $approval) {
            return;
        }

        DB::table('repair_order_approvals')
            ->where('id', $approval->id)
            ->update([
                'status' => $status,
                'decided_by' => $user->id,
                'decided_at' => now(),
                'comments' => $comments,
                'updated_at' => now(),
            ]);
    }

    protected static function logEvent(
        RepairOrder $repair,
        object $user,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        string $notes,
        int $requestId
    ): void {
        if (! Schema::hasTable('service_case_events')) {
            return;
        }

        DB::table('service_case_events')->insert([
            'company_id' => $repair->company_id,
            'service_case_id' => $repair->service_case_id,
            'repair_order_id' => $repair->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'performed_by' => $user->id,
            'performed_at' => now(),
            'notes' => $notes,
            'metadata' => json_encode([
                'approval_request_id' => $requestId,
                'document_type' => self::DOCUMENT_TYPE,
            ], JSON_UNESCAPED_UNICODE),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
