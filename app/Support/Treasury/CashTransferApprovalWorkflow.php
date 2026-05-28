<?php

namespace App\Support\Treasury;

use App\Models\ApprovalRequest;
use App\Models\TreasuryCashTransferRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CashTransferApprovalWorkflow
{
    public const DOCUMENT_TYPE = 'treasury_cash_transfer_request';

    public static function sendToApproval(TreasuryCashTransferRequest $request): ?ApprovalRequest
    {
        if (! Schema::hasTable('approval_workflows') || ! Schema::hasTable('approval_workflow_steps') || ! Schema::hasTable('approval_requests') || ! Schema::hasTable('approval_request_steps')) {
            throw new RuntimeException('El motor de aprobaciones no está instalado.');
        }

        $workflow = static::findApplicableWorkflow($request);

        if (! $workflow) {
            throw new RuntimeException('No existe un flujo activo para Solicitud de efectivo / Retiro PDV. Configúralo en Configuración empresa > Flujos de aprobación.');
        }

        $steps = static::workflowSteps($workflow, $request);

        if ($steps->isEmpty()) {
            throw new RuntimeException('El flujo de Solicitud de efectivo / Retiro PDV no tiene etapas activas aplicables.');
        }

        return DB::transaction(function () use ($request, $workflow, $steps): ApprovalRequest {
            DB::table('approval_requests')
                ->where('approvable_type', TreasuryCashTransferRequest::class)
                ->where('approvable_id', $request->id)
                ->where('document_type', self::DOCUMENT_TYPE)
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    'completed_at' => now(),
                    'last_decision_reason' => 'Reemplazada por una nueva solicitud de aprobación.',
                    'updated_at' => now(),
                ]);

            $requester = $request->requested_by_user_id
                ? DB::table('users')->where('id', $request->requested_by_user_id)->first()
                : null;

            $approvalRequestId = DB::table('approval_requests')->insertGetId([
                'company_id' => $request->company_id,
                'approval_workflow_id' => $workflow->id,
                'approvable_type' => TreasuryCashTransferRequest::class,
                'approvable_id' => $request->id,
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => static::documentNumber($request),
                'requester_user_id' => $request->requested_by_user_id,
                'requester_name' => $requester->name ?? $requester->email ?? null,
                'status' => 'pending',
                'current_step_order' => (int) ($steps->first()->sort_order ?? 1),
                'amount_total' => $request->amount,
                'sent_at' => now(),
                'notes' => trim('Solicitud de efectivo enviada al flujo de aprobación. ' . (string) ($request->reason ?? '')),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($steps as $step) {
                DB::table('approval_request_steps')->insert([
                    'approval_request_id' => $approvalRequestId,
                    'approval_workflow_step_id' => $step->id,
                    'step_order' => (int) ($step->sort_order ?? 1),
                    'step_name' => (string) ($step->name ?? 'Aprobación'),
                    'approver_type' => (string) ($step->approver_type ?? 'specific_user'),
                    'approver_user_id' => $step->approver_user_id ?? null,
                    'approver_role_name' => $step->approver_role_name ?? null,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $metadata = static::metadataArray($request->metadata ?? null);
            $metadata['approval_engine'] = [
                'document_type' => self::DOCUMENT_TYPE,
                'approval_request_id' => $approvalRequestId,
                'approval_workflow_id' => $workflow->id,
                'sent_at' => now()->toDateTimeString(),
            ];

            $update = [
                'approval_request_id' => $approvalRequestId,
                'approval_status' => 'pending',
                'approval_requested_at' => now(),
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('treasury_cash_transfer_requests', 'status')) {
                $update['status'] = 'pending_approval';
            }

            DB::table('treasury_cash_transfer_requests')
                ->where('id', $request->id)
                ->update($update);

            static::logTreasuryAction($request->id, 'approval_requested', (string) ($request->status ?? null), 'pending_approval', $request->requested_by_user_id, 'Solicitud enviada al motor general de aprobaciones.', [
                'approval_request_id' => $approvalRequestId,
                'approval_workflow_id' => $workflow->id,
            ]);

            return ApprovalRequest::query()->findOrFail($approvalRequestId);
        });
    }

    public static function markApproved(ApprovalRequest $approvalRequest, ?int $userId = null, ?string $comment = null): void
    {
        if ((string) $approvalRequest->document_type !== self::DOCUMENT_TYPE) {
            return;
        }

        $request = TreasuryCashTransferRequest::query()->find($approvalRequest->approvable_id);

        if (! $request) {
            return;
        }

        DB::table('treasury_cash_transfer_requests')
            ->where('id', $request->id)
            ->update([
                'status' => 'approved',
                'approval_status' => 'approved',
                'approved_by_user_id' => $userId,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

        static::logTreasuryAction($request->id, 'approved_by_workflow', (string) ($request->status ?? null), 'approved', $userId, $comment ?: 'Solicitud aprobada por flujo general.', [
            'approval_request_id' => $approvalRequest->id,
            'approval_workflow_id' => $approvalRequest->approval_workflow_id,
        ]);
    }

    public static function markRejected(ApprovalRequest $approvalRequest, ?int $userId = null, ?string $reason = null): void
    {
        if ((string) $approvalRequest->document_type !== self::DOCUMENT_TYPE) {
            return;
        }

        $request = TreasuryCashTransferRequest::query()->find($approvalRequest->approvable_id);

        if (! $request) {
            return;
        }

        DB::table('treasury_cash_transfer_requests')
            ->where('id', $request->id)
            ->update([
                'status' => 'rejected',
                'approval_status' => 'rejected',
                'rejected_by_user_id' => $userId,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'updated_at' => now(),
            ]);

        static::logTreasuryAction($request->id, 'rejected_by_workflow', (string) ($request->status ?? null), 'rejected', $userId, $reason ?: 'Solicitud rechazada por flujo general.', [
            'approval_request_id' => $approvalRequest->id,
            'approval_workflow_id' => $approvalRequest->approval_workflow_id,
        ]);
    }

    public static function findApplicableWorkflow(TreasuryCashTransferRequest $request): ?object
    {
        if (! Schema::hasTable('approval_workflows')) {
            return null;
        }

        $query = DB::table('approval_workflows')
            ->where('document_type', self::DOCUMENT_TYPE);

        if (Schema::hasColumn('approval_workflows', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('approval_workflows', 'company_id')) {
            $query->where(function ($q) use ($request): void {
                $q->where('company_id', $request->company_id)
                    ->orWhereNull('company_id');
            });
        }

        if (Schema::hasColumn('approval_workflows', 'amount_min')) {
            $query->where(function ($q) use ($request): void {
                $q->whereNull('amount_min')
                    ->orWhere('amount_min', '<=', $request->amount);
            });
        }

        if (Schema::hasColumn('approval_workflows', 'amount_max')) {
            $query->where(function ($q) use ($request): void {
                $q->whereNull('amount_max')
                    ->orWhere('amount_max', '>=', $request->amount);
            });
        }

        if (Schema::hasColumn('approval_workflows', 'applies_to_user_id') && $request->requested_by_user_id) {
            $query->where(function ($q) use ($request): void {
                $q->whereNull('applies_to_user_id')
                    ->orWhere('applies_to_user_id', $request->requested_by_user_id);
            });
        }

        if (Schema::hasColumn('approval_workflows', 'applies_to_warehouse_id') && $request->warehouse_id) {
            $query->where(function ($q) use ($request): void {
                $q->whereNull('applies_to_warehouse_id')
                    ->orWhere('applies_to_warehouse_id', $request->warehouse_id);
            });
        }

        if (Schema::hasColumn('approval_workflows', 'priority')) {
            $query->orderByDesc('priority');
        }

        return $query->orderByDesc('id')->first();
    }

    public static function workflowSteps(object $workflow, TreasuryCashTransferRequest $request)
    {
        if (! Schema::hasTable('approval_workflow_steps')) {
            return collect();
        }

        $query = DB::table('approval_workflow_steps')
            ->where('approval_workflow_id', $workflow->id);

        if (Schema::hasColumn('approval_workflow_steps', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('approval_workflow_steps', 'amount_min')) {
            $query->where(function ($q) use ($request): void {
                $q->whereNull('amount_min')
                    ->orWhere('amount_min', '<=', $request->amount);
            });
        }

        if (Schema::hasColumn('approval_workflow_steps', 'amount_max')) {
            $query->where(function ($q) use ($request): void {
                $q->whereNull('amount_max')
                    ->orWhere('amount_max', '>=', $request->amount);
            });
        }

        return $query
            ->orderBy(Schema::hasColumn('approval_workflow_steps', 'sort_order') ? 'sort_order' : 'id')
            ->get();
    }

    public static function documentNumber(TreasuryCashTransferRequest $request): string
    {
        return (string) ($request->number ?: ('SE-' . str_pad((string) $request->id, 6, '0', STR_PAD_LEFT)));
    }

    public static function documentUrl(object $row): string
    {
        $id = (int) ($row->approvable_id ?? 0);

        if ($id <= 0 && ! empty($row->id)) {
            $id = (int) $row->id;
        }

        $companyId = (int) ($row->company_id ?? 0);

        if ($companyId > 0 && $id > 0) {
            return url('/admin/' . $companyId . '/treasury-cash-transfer-requests/' . $id);
        }

        return url('/admin/treasury-cash-transfer-requests');
    }

    protected static function metadataArray($metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && trim($metadata) !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected static function logTreasuryAction(int $requestId, string $action, ?string $fromStatus, ?string $toStatus, ?int $userId, ?string $notes, array $metadata = []): void
    {
        if (! Schema::hasTable('treasury_cash_transfer_approval_logs')) {
            return;
        }

        $request = DB::table('treasury_cash_transfer_requests')->where('id', $requestId)->first();

        if (! $request) {
            return;
        }

        $user = $userId ? DB::table('users')->where('id', $userId)->first() : null;

        DB::table('treasury_cash_transfer_approval_logs')->insert([
            'company_id' => $request->company_id,
            'treasury_cash_transfer_request_id' => $requestId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'user_id' => $userId,
            'signer_name' => $user->name ?? $user->email ?? null,
            'signature_hash' => hash('sha256', implode('|', [
                $requestId,
                $action,
                $fromStatus,
                $toStatus,
                $userId,
                now()->toDateTimeString(),
            ])),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'notes' => $notes,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
