<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseRequestApprovalEngine
{
    public static function sendToReview(int $purchaseRequestId): ?int
    {
        if (! Schema::hasTable('purchase_requests')) {
            return null;
        }

        $request = DB::table('purchase_requests')->where('id', $purchaseRequestId)->first();

        if (! $request) {
            return null;
        }

        $workflow = static::findWorkflow($request);

        if (! $workflow) {
            return null;
        }

        $steps = static::workflowSteps($workflow, $request);

        if ($steps->isEmpty()) {
            return null;
        }

        $approvalRequestId = static::createOrUpdateApprovalRequest($request, $workflow, $steps);
        static::syncApprovalSteps($approvalRequestId, $steps);
        static::markPurchaseRequestInReview($request);

        return $approvalRequestId;
    }

    public static function findWorkflow(object $request): ?object
    {
        if (! Schema::hasTable('approval_workflows')) {
            return null;
        }

        $amount = (float) ($request->total_with_tax ?? 0);
        $companyId = $request->company_id ?? null;
        $requesterId = $request->requested_by_user_id ?? null;
        $warehouseId = $request->warehouse_id ?? null;

        $query = DB::table('approval_workflows')
            ->where('document_type', 'purchase_request');

        if (Schema::hasColumn('approval_workflows', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('approval_workflows', 'company_id') && $companyId) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        if (Schema::hasColumn('approval_workflows', 'amount_min')) {
            $query->where(function ($q) use ($amount) {
                $q->whereNull('amount_min')->orWhere('amount_min', '<=', $amount);
            });
        }

        if (Schema::hasColumn('approval_workflows', 'amount_max')) {
            $query->where(function ($q) use ($amount) {
                $q->whereNull('amount_max')->orWhere('amount_max', '>=', $amount);
            });
        }

        if (Schema::hasColumn('approval_workflows', 'applies_to_user_id') && $requesterId) {
            $query->where(function ($q) use ($requesterId) {
                $q->whereNull('applies_to_user_id')->orWhere('applies_to_user_id', $requesterId);
            });
        }

        if (Schema::hasColumn('approval_workflows', 'applies_to_warehouse_id') && $warehouseId) {
            $query->where(function ($q) use ($warehouseId) {
                $q->whereNull('applies_to_warehouse_id')->orWhere('applies_to_warehouse_id', $warehouseId);
            });
        }

        if (Schema::hasColumn('approval_workflows', 'priority')) {
            $query->orderBy('priority');
        }

        return $query->orderBy('id')->first();
    }

    protected static function workflowSteps(object $workflow, object $request)
    {
        if (! Schema::hasTable('approval_workflow_steps')) {
            return collect();
        }

        $amount = (float) ($request->total_with_tax ?? 0);

        $query = DB::table('approval_workflow_steps')
            ->where('approval_workflow_id', $workflow->id);

        if (Schema::hasColumn('approval_workflow_steps', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('approval_workflow_steps', 'amount_min')) {
            $query->where(function ($q) use ($amount) {
                $q->whereNull('amount_min')->orWhere('amount_min', '<=', $amount);
            });
        }

        if (Schema::hasColumn('approval_workflow_steps', 'amount_max')) {
            $query->where(function ($q) use ($amount) {
                $q->whereNull('amount_max')->orWhere('amount_max', '>=', $amount);
            });
        }

        return $query
            ->orderBy(Schema::hasColumn('approval_workflow_steps', 'sort_order') ? 'sort_order' : 'id')
            ->get();
    }

    protected static function createOrUpdateApprovalRequest(object $request, object $workflow, $steps): int
    {
        $approvableType = 'App\\Models\\PurchaseRequest';

        $existing = DB::table('approval_requests')
            ->where(function ($q) use ($request, $approvableType) {
                $q->where(function ($qq) use ($request, $approvableType) {
                    $qq->where('approvable_type', $approvableType)
                        ->where('approvable_id', $request->id);
                })->orWhere(function ($qq) use ($request) {
                    $qq->where('document_type', 'purchase_request')
                        ->where('document_number', $request->number);
                });
            })
            ->orderByDesc('id')
            ->first();

        $firstStep = $steps->first();
        $firstOrder = (int) ($firstStep->sort_order ?? 1);
        $requesterId = $request->requested_by_user_id ?? null;
        $requesterName = null;

        if ($requesterId && Schema::hasTable('users')) {
            $requesterName = DB::table('users')->where('id', $requesterId)->value('name');
        }

        $data = [
            'company_id' => $request->company_id ?? null,
            'approval_workflow_id' => $workflow->id,
            'approvable_type' => $approvableType,
            'approvable_id' => $request->id,
            'document_type' => 'purchase_request',
            'document_number' => $request->number ?? null,
            'requester_user_id' => $requesterId,
            'requester_name' => $requesterName,
            'status' => 'pending',
            'current_step_order' => $firstOrder,
            'amount_total' => (float) ($request->total_with_tax ?? 0),
            'sent_at' => now(),
            'updated_at' => now(),
        ];

        $data = static::filterColumns('approval_requests', $data);

        if ($existing) {
            DB::table('approval_requests')->where('id', $existing->id)->update($data);

            return (int) $existing->id;
        }

        $data['created_at'] = now();

        return (int) DB::table('approval_requests')->insertGetId($data);
    }

    protected static function syncApprovalSteps(int $approvalRequestId, $steps): void
    {
        if (! Schema::hasTable('approval_request_steps')) {
            return;
        }

        $exists = DB::table('approval_request_steps')
            ->where('approval_request_id', $approvalRequestId)
            ->exists();

        if ($exists) {
            DB::table('approval_request_steps')
                ->where('approval_request_id', $approvalRequestId)
                ->delete();
        }

        $firstOrder = null;

        foreach ($steps as $index => $step) {
            $order = (int) ($step->sort_order ?? ($index + 1));

            if ($firstOrder === null) {
                $firstOrder = $order;
            }

            $data = [
                'approval_request_id' => $approvalRequestId,
                'approval_workflow_step_id' => $step->id,
                'step_order' => $order,
                'step_name' => $step->name ?? ('Etapa ' . $order),
                'approver_type' => $step->approver_type ?? null,
                'approver_user_id' => $step->approver_user_id ?? null,
                'approver_role_name' => $step->approver_role_name ?? null,
                'status' => $order === $firstOrder ? 'pending' : 'waiting',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('approval_request_steps')->insert(
                static::filterColumns('approval_request_steps', $data)
            );
        }
    }

    protected static function markPurchaseRequestInReview(object $request): void
    {
        $data = [];

        if (Schema::hasColumn('purchase_requests', 'status')) {
            $data['status'] = 'review';
        }

        if (Schema::hasColumn('purchase_requests', 'updated_at')) {
            $data['updated_at'] = now();
        }

        if (! empty($data)) {
            DB::table('purchase_requests')->where('id', $request->id)->update($data);
        }

        static::logStatusChange($request, 'review');
    }

    protected static function logStatusChange(object $request, string $toStatus): void
    {
        if (! Schema::hasTable('purchase_request_status_logs')) {
            return;
        }

        $data = [
            'purchase_request_id' => $request->id,
            'from_status' => $request->status ?? null,
            'to_status' => $toStatus,
            'event' => 'sent_to_review',
            'notes' => 'Solicitud enviada a revisión.',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('purchase_request_status_logs', 'user_id')) {
            $data['user_id'] = auth()->id();
        }

        DB::table('purchase_request_status_logs')->insert(
            static::filterColumns('purchase_request_status_logs', $data)
        );
    }

    protected static function filterColumns(string $table, array $data): array
    {
        return array_filter(
            $data,
            fn ($value, $key) => Schema::hasColumn($table, $key),
            ARRAY_FILTER_USE_BOTH
        );
    }
    public static function closeAsApproved(int $purchaseRequestId, ?int $actorUserId = null, ?string $comments = null): void
    {
        if (! Schema::hasTable('purchase_requests') || ! Schema::hasTable('approval_requests')) {
            return;
        }

        $request = DB::table('purchase_requests')->where('id', $purchaseRequestId)->first();

        if (! $request) {
            return;
        }

        $approvableType = 'App\\Models\\PurchaseRequest';

        $approvalRequests = DB::table('approval_requests')
            ->where(function ($q) use ($request, $approvableType) {
                $q->where(function ($qq) use ($request, $approvableType) {
                    $qq->where('approvable_type', $approvableType)
                        ->where('approvable_id', $request->id);
                })->orWhere(function ($qq) use ($request) {
                    $qq->where('document_type', 'purchase_request')
                        ->where('document_number', $request->number);
                });
            })
            ->get();

        foreach ($approvalRequests as $approvalRequest) {
            DB::table('approval_requests')
                ->where('id', $approvalRequest->id)
                ->update(static::filterColumns('approval_requests', [
                    'status' => 'approved',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]));

            if (! Schema::hasTable('approval_request_steps')) {
                continue;
            }

            $steps = DB::table('approval_request_steps')
                ->where('approval_request_id', $approvalRequest->id)
                ->get();

            foreach ($steps as $step) {
                $actedByUserId = $actorUserId ?: ($step->approver_user_id ?? null);
                $actedByName = null;

                if ($actedByUserId && Schema::hasTable('users')) {
                    $actedByName = DB::table('users')->where('id', $actedByUserId)->value('name');
                }

                DB::table('approval_request_steps')
                    ->where('id', $step->id)
                    ->update(static::filterColumns('approval_request_steps', [
                        'status' => 'approved',
                        'acted_by_user_id' => $actedByUserId,
                        'acted_by_name' => $actedByName,
                        'acted_at' => $step->acted_at ?: now(),
                        'comments' => $comments ?: ($step->comments ?: 'Solicitud aprobada.'),
                        'updated_at' => now(),
                    ]));
            }
        }
    }



}
