<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseRequestApprovalActions
{
    public static function pendingStepForRequest(int $purchaseRequestId, ?int $userId = null): ?object
    {
        if (
            $purchaseRequestId <= 0
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
            ->where('requests.document_type', 'purchase_request')
            ->where('requests.approvable_id', $purchaseRequestId)
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
                'requests.requester_user_id',
            ])
            ->orderBy('steps.step_order')
            ->first();
    }

    public static function approve(int $purchaseRequestId, int $userId): string
    {
        return DB::transaction(function () use ($purchaseRequestId, $userId): string {
            $user = DB::table('users')->where('id', $userId)->first();

            $step = self::pendingStepForRequest($purchaseRequestId, $userId);

            if (! $step) {
                throw new \RuntimeException('No hay una aprobación pendiente asignada a tu usuario para esta solicitud.');
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

            $comment = 'Solicitud de compra aprobada.';

            $stepUpdate = [
                'status' => 'approved',
                'acted_by_user_id' => $userId,
                'acted_by_name' => $user->name ?? $user->email ?? 'Usuario',
                'acted_at' => now(),
                'comments' => $comment,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('approval_request_steps', 'decision_reason')) {
                $stepUpdate['decision_reason'] = $comment;
            }

            DB::table('approval_request_steps')
                ->where('id', $approvalStep->id)
                ->update($stepUpdate);

            $nextStep = DB::table('approval_request_steps')
                ->where('approval_request_id', $approvalRequest->id)
                ->where('status', 'pending')
                ->orderBy('step_order')
                ->first();

            if ($nextStep) {
                $requestUpdate = [
                    'current_step_order' => $nextStep->step_order,
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('approval_requests', 'last_decision_reason')) {
                    $requestUpdate['last_decision_reason'] = $comment;
                }

                DB::table('approval_requests')
                    ->where('id', $approvalRequest->id)
                    ->update($requestUpdate);

                self::notifyRequester(
                    $approvalRequest,
                    'Solicitud aprobada parcialmente',
                    'La solicitud ' . $approvalRequest->document_number . ' fue aprobada en una etapa y continúa pendiente.',
                    'purchase_request_step_approved',
                    $comment
                );

                return 'Etapa aprobada. La solicitud continúa en el siguiente nivel.';
            }

            $requestUpdate = [
                'status' => 'approved',
                'completed_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('approval_requests', 'last_decision_reason')) {
                $requestUpdate['last_decision_reason'] = $comment;
            }

            DB::table('approval_requests')
                ->where('id', $approvalRequest->id)
                ->update($requestUpdate);

            DB::table('purchase_requests')
                ->where('id', $purchaseRequestId)
                ->update([
                    'status' => 'approved',
                    'updated_at' => now(),
                ]);

            self::notifyRequester(
                $approvalRequest,
                'Solicitud de compra aprobada',
                'La solicitud ' . $approvalRequest->document_number . ' fue aprobada.',
                'purchase_request_approved',
                $comment
            );

            return 'Solicitud de compra aprobada.';
        });
    }

    public static function reject(int $purchaseRequestId, int $userId, string $reason): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw new \RuntimeException('El motivo de rechazo es obligatorio y debe tener al menos 5 caracteres.');
        }

        return DB::transaction(function () use ($purchaseRequestId, $userId, $reason): string {
            $user = DB::table('users')->where('id', $userId)->first();

            $step = self::pendingStepForRequest($purchaseRequestId, $userId);

            if (! $step) {
                throw new \RuntimeException('No hay una aprobación pendiente asignada a tu usuario para esta solicitud.');
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

            $stepUpdate = [
                'status' => 'rejected',
                'acted_by_user_id' => $userId,
                'acted_by_name' => $user->name ?? $user->email ?? 'Usuario',
                'acted_at' => now(),
                'comments' => $reason,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('approval_request_steps', 'decision_reason')) {
                $stepUpdate['decision_reason'] = $reason;
            }

            DB::table('approval_request_steps')
                ->where('id', $approvalStep->id)
                ->update($stepUpdate);

            DB::table('approval_request_steps')
                ->where('approval_request_id', $approvalRequest->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'skipped',
                    'updated_at' => now(),
                ]);

            $requestUpdate = [
                'status' => 'rejected',
                'completed_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('approval_requests', 'last_decision_reason')) {
                $requestUpdate['last_decision_reason'] = $reason;
            }

            DB::table('approval_requests')
                ->where('id', $approvalRequest->id)
                ->update($requestUpdate);

            DB::table('purchase_requests')
                ->where('id', $purchaseRequestId)
                ->update([
                    'status' => 'draft',
                    'updated_at' => now(),
                ]);

            self::notifyRequester(
                $approvalRequest,
                'Solicitud de compra rechazada',
                'La solicitud ' . $approvalRequest->document_number . ' fue rechazada. Motivo: ' . $reason,
                'purchase_request_rejected',
                $reason
            );

            return 'Solicitud rechazada. Regresa a borrador.';
        });
    }

    protected static function notifyRequester(object $approvalRequest, string $title, string $body, string $type, string $reason): void
    {
        if (! class_exists(BexiaUserNotification::class)) {
            return;
        }

        $requesterId = (int) ($approvalRequest->requester_user_id ?? 0);
        $companyId = (int) ($approvalRequest->company_id ?? 0);
        $approvableId = (int) ($approvalRequest->approvable_id ?? 0);

        if ($requesterId <= 0 || $companyId <= 0 || $approvableId <= 0) {
            return;
        }

        BexiaUserNotification::send(
            $requesterId,
            $title,
            $body,
            url('/admin/' . $companyId . '/purchase-requests/' . $approvableId),
            $companyId,
            $type,
            [
                'approval_request_id' => (int) $approvalRequest->id,
                'purchase_request_id' => $approvableId,
                'document_number' => (string) ($approvalRequest->document_number ?? ''),
                'reason' => $reason,
            ]
        );
    }
}
