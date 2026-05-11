<?php

namespace App\Support;

use App\Models\ApprovalRequest;
use App\Models\ApprovalRequestStep;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\PurchaseRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ApprovalEngine
{
    public static function hasApplicableWorkflowForPurchaseRequest(PurchaseRequest $purchaseRequest): bool
    {
        return static::findWorkflowForPurchaseRequest($purchaseRequest) !== null;
    }

    public static function findWorkflowForPurchaseRequest(PurchaseRequest $purchaseRequest): ?ApprovalWorkflow
    {
        if (! Schema::hasTable('approval_workflows') || ! Schema::hasTable('approval_workflow_steps')) {
            return null;
        }

        $amount = (float) ($purchaseRequest->total_with_tax ?? 0);
        $companyId = $purchaseRequest->company_id;
        $warehouseId = $purchaseRequest->warehouse_id;
        $requesterUserId = $purchaseRequest->requested_by_user_id ?: auth()->id();

        $query = ApprovalWorkflow::query()
            ->with(['steps' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->where('document_type', 'purchase_request')
            ->where('is_active', true)
            ->whereHas('steps', fn ($query) => $query->where('is_active', true))
            ->where(function ($query) use ($companyId): void {
                if ($companyId) {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');
                } else {
                    $query->whereNull('company_id');
                }
            })
            ->where(function ($query) use ($amount): void {
                $query->whereNull('amount_min')->orWhere('amount_min', '<=', $amount);
            })
            ->where(function ($query) use ($amount): void {
                $query->whereNull('amount_max')->orWhere('amount_max', '>=', $amount);
            })
            ->where(function ($query) use ($warehouseId): void {
                if ($warehouseId) {
                    $query->whereNull('applies_to_warehouse_id')->orWhere('applies_to_warehouse_id', $warehouseId);
                } else {
                    $query->whereNull('applies_to_warehouse_id');
                }
            })
            ->orderBy('priority')
            ->orderByDesc('company_id')
            ->get();

        foreach ($query as $workflow) {
            if ($workflow->applies_to_user_id && (int) $workflow->applies_to_user_id !== (int) $requesterUserId) {
                continue;
            }

            if ($workflow->applies_to_role_name && ! static::userHasRole($requesterUserId, (string) $workflow->applies_to_role_name)) {
                continue;
            }

            $applicableSteps = $workflow->steps
                ->filter(fn (ApprovalWorkflowStep $step): bool => static::stepAmountApplies($step, $amount))
                ->values();

            if ($applicableSteps->isEmpty()) {
                continue;
            }

            $workflow->setRelation('steps', $applicableSteps);

            return $workflow;
        }

        return null;
    }

    public static function startPurchaseRequest(PurchaseRequest $purchaseRequest): ApprovalRequest
    {
        if (! Schema::hasTable('approval_requests') || ! Schema::hasTable('approval_request_steps')) {
            throw new RuntimeException('El motor de aprobaciones no está instalado.');
        }

        $purchaseRequest->refresh();

        $existing = ApprovalRequest::query()
            ->where('approvable_type', PurchaseRequest::class)
            ->where('approvable_id', $purchaseRequest->id)
            ->whereIn('status', ['pending'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $workflow = static::findWorkflowForPurchaseRequest($purchaseRequest);

        if (! $workflow) {
            throw new RuntimeException('No se encontró flujo de aprobación aplicable.');
        }

        return DB::transaction(function () use ($purchaseRequest, $workflow): ApprovalRequest {
            $steps = $workflow->steps->values();

            if ($steps->isEmpty()) {
                throw new RuntimeException('El flujo no tiene etapas activas aplicables.');
            }

            $firstOrder = (int) $steps->min('sort_order');

            $approvalRequest = ApprovalRequest::create([
                'company_id' => $purchaseRequest->company_id,
                'approval_workflow_id' => $workflow->id,
                'approvable_type' => PurchaseRequest::class,
                'approvable_id' => $purchaseRequest->id,
                'document_type' => 'purchase_request',
                'document_number' => $purchaseRequest->number,
                'requester_user_id' => $purchaseRequest->requested_by_user_id ?: auth()->id(),
                'requester_name' => auth()->user()?->name ?? auth()->user()?->email,
                'status' => 'pending',
                'current_step_order' => $firstOrder,
                'amount_total' => $purchaseRequest->total_with_tax,
                'sent_at' => now(),
                'notes' => 'Solicitud enviada a aprobación.',
            ]);

            foreach ($steps as $step) {
                foreach (static::approvalStepRows($step, $purchaseRequest) as $stepRow) {
                    $approvalRequest->steps()->create($stepRow);
                }
            }

            $purchaseRequest->update(['status' => 'review']);

            return $approvalRequest;
        });
    }

    public static function hasOpenApprovalForPurchaseRequest(PurchaseRequest $purchaseRequest): bool
    {
        if (! Schema::hasTable('approval_requests')) {
            return false;
        }

        return ApprovalRequest::query()
            ->where('approvable_type', PurchaseRequest::class)
            ->where('approvable_id', $purchaseRequest->id)
            ->where('status', 'pending')
            ->exists();
    }

    public static function userCanApprovePurchaseRequest(PurchaseRequest $purchaseRequest, $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user || ! Schema::hasTable('approval_requests') || ! Schema::hasTable('approval_request_steps')) {
            return false;
        }

        $approvalRequest = static::openApprovalForPurchaseRequest($purchaseRequest);

        if (! $approvalRequest) {
            return false;
        }

        return static::currentPendingSteps($approvalRequest)
            ->contains(fn (ApprovalRequestStep $step): bool => static::userCanActOnStep($user, $step));
    }

    public static function approvePurchaseRequestStep(PurchaseRequest $purchaseRequest, $user = null, ?string $comments = null): ApprovalRequest
    {
        $user ??= auth()->user();

        if (! $user) {
            throw new RuntimeException('No hay usuario autenticado.');
        }

        return DB::transaction(function () use ($purchaseRequest, $user, $comments): ApprovalRequest {
            $approvalRequest = static::openApprovalForPurchaseRequest($purchaseRequest);

            if (! $approvalRequest) {
                throw new RuntimeException('No hay aprobación pendiente para esta solicitud.');
            }

            $step = static::currentPendingSteps($approvalRequest)
                ->first(fn (ApprovalRequestStep $step): bool => static::userCanActOnStep($user, $step));

            if (! $step) {
                throw new RuntimeException('El usuario actual no puede aprobar esta etapa.');
            }

            $step->update([
                'status' => 'approved',
                'acted_by_user_id' => $user->id,
                'acted_by_name' => $user->name ?? $user->email,
                'acted_at' => now(),
                'comments' => $comments,
            ]);

            $remainingCurrentStep = ApprovalRequestStep::query()
                ->where('approval_request_id', $approvalRequest->id)
                ->where('step_order', $approvalRequest->current_step_order)
                ->where('status', 'pending')
                ->exists();

            if ($remainingCurrentStep) {
                return $approvalRequest->refresh();
            }

            $nextOrder = ApprovalRequestStep::query()
                ->where('approval_request_id', $approvalRequest->id)
                ->where('status', 'pending')
                ->where('step_order', '>', $approvalRequest->current_step_order)
                ->min('step_order');

            if ($nextOrder) {
                $approvalRequest->update([
                    'current_step_order' => (int) $nextOrder,
                ]);

                return $approvalRequest->refresh();
            }

            $approvalRequest->update([
                'status' => 'approved',
                'completed_at' => now(),
            ]);

            $purchaseRequest->update([
                'status' => 'approved',
            ]);

            return $approvalRequest->refresh();
        });
    }

    public static function rejectPurchaseRequestStep(PurchaseRequest $purchaseRequest, $user = null, ?string $comments = null): ApprovalRequest
    {
        $user ??= auth()->user();

        if (! $user) {
            throw new RuntimeException('No hay usuario autenticado.');
        }

        return DB::transaction(function () use ($purchaseRequest, $user, $comments): ApprovalRequest {
            $approvalRequest = static::openApprovalForPurchaseRequest($purchaseRequest);

            if (! $approvalRequest) {
                throw new RuntimeException('No hay aprobación pendiente para esta solicitud.');
            }

            $step = static::currentPendingSteps($approvalRequest)
                ->first(fn (ApprovalRequestStep $step): bool => static::userCanActOnStep($user, $step));

            if (! $step) {
                throw new RuntimeException('El usuario actual no puede rechazar esta etapa.');
            }

            $step->update([
                'status' => 'rejected',
                'acted_by_user_id' => $user->id,
                'acted_by_name' => $user->name ?? $user->email,
                'acted_at' => now(),
                'comments' => $comments,
            ]);

            $approvalRequest->update([
                'status' => 'rejected',
                'completed_at' => now(),
            ]);

            ApprovalRequestStep::query()
                ->where('approval_request_id', $approvalRequest->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'skipped',
                    'comments' => 'Etapa omitida por rechazo previo.',
                    'updated_at' => now(),
                ]);

            $purchaseRequest->update([
                'status' => 'rejected',
            ]);

            return $approvalRequest->refresh();
        });
    }

    public static function openApprovalForPurchaseRequest(PurchaseRequest $purchaseRequest): ?ApprovalRequest
    {
        return ApprovalRequest::query()
            ->with('steps')
            ->where('approvable_type', PurchaseRequest::class)
            ->where('approvable_id', $purchaseRequest->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
    }

    public static function approvalStatusForPurchaseRequest(PurchaseRequest $purchaseRequest): ?ApprovalRequest
    {
        if (! Schema::hasTable('approval_requests')) {
            return null;
        }

        return ApprovalRequest::query()
            ->with(['steps' => fn ($query) => $query->orderBy('step_order')->orderBy('id')])
            ->where('approvable_type', PurchaseRequest::class)
            ->where('approvable_id', $purchaseRequest->id)
            ->latest('id')
            ->first();
    }

    protected static function currentPendingSteps(ApprovalRequest $approvalRequest): Collection
    {
        return ApprovalRequestStep::query()
            ->where('approval_request_id', $approvalRequest->id)
            ->where('step_order', $approvalRequest->current_step_order)
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();
    }

    protected static function approvalStepRows(ApprovalWorkflowStep $step, PurchaseRequest $purchaseRequest): array
    {
        $base = [
            'approval_workflow_step_id' => $step->id,
            'step_order' => $step->sort_order,
            'step_name' => $step->name,
            'approver_type' => $step->approver_type,
            'approver_user_id' => $step->approver_user_id,
            'approver_role_name' => $step->approver_role_name,
            'status' => 'pending',
        ];

        if ($step->approver_type === 'specific_user') {
            return [$base];
        }

        if ($step->approver_type === 'requester_manager') {
            $managerId = static::requesterManagerId($purchaseRequest);

            if ($managerId) {
                $base['approver_user_id'] = $managerId;
                $base['approver_role_name'] = null;

                return [$base];
            }

            $base['approver_type'] = 'company_admin';
            $base['approver_role_name'] = 'Admin Empresa';

            return [$base];
        }

        if ($step->approver_type === 'role') {
            if ($step->require_all && $step->approver_role_name) {
                $userIds = static::userIdsWithRole((string) $step->approver_role_name, $purchaseRequest->company_id);

                if (! empty($userIds)) {
                    return collect($userIds)
                        ->map(function ($userId) use ($base, $step): array {
                            $row = $base;
                            $row['approver_user_id'] = $userId;
                            $row['approver_role_name'] = $step->approver_role_name;

                            return $row;
                        })
                        ->all();
                }
            }

            return [$base];
        }

        $role = match ($step->approver_type) {
            'company_admin' => 'Admin Empresa',
            'group_admin' => 'Admin Grupo',
            'warehouse_responsible' => 'Inventarios',
            'purchase_responsible' => 'Compras',
            'accounting_responsible' => 'Contabilidad',
            default => null,
        };

        if ($role) {
            $base['approver_role_name'] = $role;
        }

        return [$base];
    }

    protected static function stepAmountApplies(ApprovalWorkflowStep $step, float $amount): bool
    {
        if ($step->amount_min !== null && (float) $step->amount_min > $amount) {
            return false;
        }

        if ($step->amount_max !== null && (float) $step->amount_max < $amount) {
            return false;
        }

        return true;
    }

    protected static function userCanActOnStep($user, ApprovalRequestStep $step): bool
    {
        if ($step->approver_user_id && (int) $step->approver_user_id === (int) $user->id) {
            return true;
        }

        if ($step->approver_role_name && static::userObjectHasRole($user, (string) $step->approver_role_name)) {
            return true;
        }

        return match ($step->approver_type) {
            'company_admin' => static::userObjectHasAnyRole($user, ['Admin Empresa', 'Administrador', 'admin', 'super_admin', 'Super Admin']),
            'group_admin' => static::userObjectHasAnyRole($user, ['Admin Grupo', 'super_admin', 'Super Admin']),
            'warehouse_responsible' => static::userObjectHasAnyRole($user, ['Inventarios', 'Admin Empresa', 'Administrador', 'admin']),
            'purchase_responsible' => static::userObjectHasAnyRole($user, ['Compras', 'Admin Empresa', 'Administrador', 'admin']),
            'accounting_responsible' => static::userObjectHasAnyRole($user, ['Contabilidad', 'Admin Empresa', 'Administrador', 'admin']),
            default => false,
        };
    }

    protected static function requesterManagerId(PurchaseRequest $purchaseRequest): ?int
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'approval_manager_user_id')) {
            return null;
        }

        $requesterUserId = $purchaseRequest->requested_by_user_id ?: auth()->id();

        if (! $requesterUserId) {
            return null;
        }

        $managerId = DB::table('users')
            ->where('id', $requesterUserId)
            ->value('approval_manager_user_id');

        return $managerId ? (int) $managerId : null;
    }

    protected static function userHasRole($userId, string $roleName): bool
    {
        if (! $userId || $roleName === '') {
            return false;
        }

        $userModel = config('auth.providers.users.model', \App\Models\User::class);

        if (class_exists($userModel)) {
            $user = $userModel::query()->find($userId);

            if ($user && method_exists($user, 'hasRole')) {
                return $user->hasRole($roleName);
            }
        }

        return false;
    }

    protected static function userObjectHasRole($user, string $roleName): bool
    {
        if (! $user || $roleName === '') {
            return false;
        }

        return method_exists($user, 'hasRole') && $user->hasRole($roleName);
    }

    protected static function userObjectHasAnyRole($user, array $roles): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }

        foreach ($roles as $role) {
            if (static::userObjectHasRole($user, $role)) {
                return true;
            }
        }

        return false;
    }

    protected static function userIdsWithRole(string $roleName, ?int $companyId = null): array
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return [];
        }

        $query = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', $roleName);

        if ($companyId && Schema::hasColumn('roles', 'company_id')) {
            $query->where(function ($query) use ($companyId): void {
                $query->where('roles.company_id', $companyId)->orWhereNull('roles.company_id');
            });
        }

        return $query
            ->pluck('model_has_roles.model_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
