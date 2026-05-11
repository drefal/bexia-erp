<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseOrderApprovalActions
{
    public static function pendingStepForOrder(int $purchaseOrderId, ?int $userId = null): ?object
    {
        if (
            $purchaseOrderId <= 0
            || ! Schema::hasTable('approval_requests')
            || ! Schema::hasTable('approval_request_steps')
        ) {
            return null;
        }

        $userId = $userId ?: (int) auth()->id();

        if ($userId <= 0) {
            return null;
        }

        return DB::table('approval_request_steps as steps')
            ->join('approval_requests as requests', 'requests.id', '=', 'steps.approval_request_id')
            ->where('requests.document_type', 'purchase_order')
            ->where('requests.approvable_id', $purchaseOrderId)
            ->where('requests.status', 'pending')
            ->where('steps.status', 'pending')
            ->where('steps.approver_user_id', $userId)
            ->select([
                'steps.id as step_id',
                'steps.step_order',
                'steps.step_name',
                'steps.approver_user_id',
                'requests.id as request_id',
                'requests.document_number',
                'requests.approvable_id',
                'requests.company_id',
            ])
            ->orderBy('steps.step_order')
            ->first();
    }

    public static function approve(int $purchaseOrderId, int $userId, ?string $reason = null): string
    {
        return DB::transaction(function () use ($purchaseOrderId, $userId, $reason): string {
            $user = DB::table('users')->where('id', $userId)->first();

            $step = self::pendingStepForOrder($purchaseOrderId, $userId);

            if (! $step) {
                throw new \RuntimeException('No hay una aprobación pendiente asignada a tu usuario para esta orden.');
            }

            $approvalRequest = DB::table('approval_requests')
                ->where('id', $step->request_id)
                ->lockForUpdate()
                ->first();

            $approvalStep = DB::table('approval_request_steps')
                ->where('id', $step->step_id)
                ->lockForUpdate()
                ->first();

            if (! $approvalRequest || (string) $approvalRequest->status !== 'pending') {
                throw new \RuntimeException('La aprobación ya no está pendiente.');
            }

            if (! $approvalStep || (string) $approvalStep->status !== 'pending') {
                throw new \RuntimeException('Esta etapa ya fue atendida.');
            }

            $comment = trim((string) $reason) ?: 'Orden de compra aprobada.';

            DB::table('approval_request_steps')
                ->where('id', $approvalStep->id)
                ->update([
                    'status' => 'approved',
                    'acted_by_user_id' => $userId,
                    'acted_by_name' => $user->name ?? $user->email ?? 'Usuario',
                    'acted_at' => now(),
                    'comments' => $comment,
                    'decision_reason' => $comment,
                    'updated_at' => now(),
                ]);

            $nextStep = DB::table('approval_request_steps')
                ->where('approval_request_id', $approvalRequest->id)
                ->where('status', 'pending')
                ->orderBy('step_order')
                ->first();

            if ($nextStep) {
                DB::table('approval_requests')
                    ->where('id', $approvalRequest->id)
                    ->update([
                        'current_step_order' => $nextStep->step_order,
                        'last_decision_reason' => $comment,
                        'updated_at' => now(),
                    ]);

                self::notifyRequester(
                    $approvalRequest,
                    'OC aprobada parcialmente',
                    'La orden ' . $approvalRequest->document_number . ' fue aprobada en una etapa y continúa pendiente de la siguiente aprobación.',
                    'purchase_order_step_approved',
                    [
                        'decision' => 'approved_step',
                        'reason' => $comment,
                    ]
                );

                return 'Etapa aprobada. La orden continúa en el siguiente nivel de aprobación.';
            }

            DB::table('approval_requests')
                ->where('id', $approvalRequest->id)
                ->update([
                    'status' => 'approved',
                    'completed_at' => now(),
                    'last_decision_reason' => $comment,
                    'updated_at' => now(),
                ]);

            DB::table('purchase_orders')
                ->where('id', $purchaseOrderId)
                ->update([
                    'status' => 'confirmed',
                    'updated_at' => now(),
                ]);

            self::logHistory(
                $purchaseOrderId,
                'approved',
                'review',
                'confirmed',
                'Orden de compra aprobada y confirmada.',
                [
                    'approval_request_id' => (int) $approvalRequest->id,
                    'acted_by_user_id' => $userId,
                    'acted_by_name' => $user->name ?? $user->email ?? 'Usuario',
                    'reason' => $comment,
                ]
            );

            self::notifyRequester(
                $approvalRequest,
                'OC aprobada',
                'La orden ' . $approvalRequest->document_number . ' fue aprobada y quedó confirmada.',
                'purchase_order_approved',
                [
                    'decision' => 'approved',
                    'reason' => $comment,
                ]
            );

            return 'Orden de compra aprobada y confirmada.';
        });
    }

    public static function reject(int $purchaseOrderId, int $userId, string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \RuntimeException('El motivo de rechazo es obligatorio.');
        }

        return DB::transaction(function () use ($purchaseOrderId, $userId, $reason): string {
            $user = DB::table('users')->where('id', $userId)->first();

            $step = self::pendingStepForOrder($purchaseOrderId, $userId);

            if (! $step) {
                throw new \RuntimeException('No hay una aprobación pendiente asignada a tu usuario para esta orden.');
            }

            $approvalRequest = DB::table('approval_requests')
                ->where('id', $step->request_id)
                ->lockForUpdate()
                ->first();

            $approvalStep = DB::table('approval_request_steps')
                ->where('id', $step->step_id)
                ->lockForUpdate()
                ->first();

            if (! $approvalRequest || (string) $approvalRequest->status !== 'pending') {
                throw new \RuntimeException('La aprobación ya no está pendiente.');
            }

            if (! $approvalStep || (string) $approvalStep->status !== 'pending') {
                throw new \RuntimeException('Esta etapa ya fue atendida.');
            }

            DB::table('approval_request_steps')
                ->where('id', $approvalStep->id)
                ->update([
                    'status' => 'rejected',
                    'acted_by_user_id' => $userId,
                    'acted_by_name' => $user->name ?? $user->email ?? 'Usuario',
                    'acted_at' => now(),
                    'comments' => $reason,
                    'decision_reason' => $reason,
                    'updated_at' => now(),
                ]);

            DB::table('approval_request_steps')
                ->where('approval_request_id', $approvalRequest->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'skipped',
                    'updated_at' => now(),
                ]);

            DB::table('approval_requests')
                ->where('id', $approvalRequest->id)
                ->update([
                    'status' => 'rejected',
                    'completed_at' => now(),
                    'last_decision_reason' => $reason,
                    'updated_at' => now(),
                ]);

            DB::table('purchase_orders')
                ->where('id', $purchaseOrderId)
                ->update([
                    'status' => 'draft',
                    'updated_at' => now(),
                ]);

            self::logHistory(
                $purchaseOrderId,
                'rejected',
                'review',
                'draft',
                'Orden de compra rechazada. Regresa a borrador para corrección.',
                [
                    'approval_request_id' => (int) $approvalRequest->id,
                    'acted_by_user_id' => $userId,
                    'acted_by_name' => $user->name ?? $user->email ?? 'Usuario',
                    'reason' => $reason,
                ]
            );

            self::notifyRequester(
                $approvalRequest,
                'OC rechazada',
                'La orden ' . $approvalRequest->document_number . ' fue rechazada. Motivo: ' . $reason,
                'purchase_order_rejected',
                [
                    'decision' => 'rejected',
                    'reason' => $reason,
                ]
            );

            return 'Orden de compra rechazada. Regresa a borrador.';
        });
    }

    protected static function notifyRequester(object $approvalRequest, string $title, string $body, string $type, array $metadata = []): void
    {
        $requesterId = (int) ($approvalRequest->requester_user_id ?? 0);
        $companyId = (int) ($approvalRequest->company_id ?? 0);
        $approvableId = (int) ($approvalRequest->approvable_id ?? 0);

        if ($requesterId <= 0 || $companyId <= 0 || $approvableId <= 0) {
            return;
        }

        $url = url('/admin/' . $companyId . '/purchase-orders/' . $approvableId . '/edit');

        if (class_exists(BexiaUserNotification::class)) {
            BexiaUserNotification::send(
                $requesterId,
                $title,
                $body,
                $url,
                $companyId,
                $type,
                array_merge($metadata, [
                    'approval_request_id' => (int) $approvalRequest->id,
                    'purchase_order_id' => $approvableId,
                    'document_number' => (string) ($approvalRequest->document_number ?? ''),
                ])
            );
        }
    }

    protected static function logHistory(
        int $purchaseOrderId,
        string $event,
        ?string $fromStatus,
        ?string $toStatus,
        string $notes,
        array $metadata = []
    ): void {
        if (class_exists(PurchaseOrderHistory::class)) {
            PurchaseOrderHistory::log(
                $purchaseOrderId,
                $event,
                $fromStatus,
                $toStatus,
                $notes,
                $metadata
            );
        }
    }
}
