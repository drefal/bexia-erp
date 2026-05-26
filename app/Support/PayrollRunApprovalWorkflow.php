<?php

namespace App\Support;

use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollRunApprovalWorkflow
{
    public const DOCUMENT_TYPE = 'payroll_run';

    public static function sendToApproval(PayrollRun $run, ?int $userId = null): object
    {
        return DB::transaction(function () use ($run, $userId): object {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if (! Schema::hasTable('approval_requests') || ! Schema::hasTable('approval_request_steps')) {
                throw new \RuntimeException('No existen las tablas de aprobación.');
            }

            if (! in_array((string) $run->status, ['calculated'], true)) {
                throw new \RuntimeException('Solo una pre-nómina calculada puede enviarse a aprobación.');
            }

            if (! $run->lines()->exists()) {
                throw new \RuntimeException('La pre-nómina no tiene líneas calculadas.');
            }

            $existing = DB::table('approval_requests')
                ->where('document_type', self::DOCUMENT_TYPE)
                ->where('approvable_id', $run->id)
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            DB::table('approval_requests')
                ->where('document_type', self::DOCUMENT_TYPE)
                ->where('approvable_id', $run->id)
                ->whereIn('status', ['pending'])
                ->update([
                    'status' => 'cancelled',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            $workflow = static::findApplicableWorkflow($run);
            $steps = $workflow
                ? static::workflowSteps((int) $workflow->id, $run)
                : collect();

            if ($steps->isEmpty()) {
                throw new \RuntimeException('No hay flujo de aprobación activo para pre-nómina.');
            }

            $requester = $userId ? User::query()->find($userId) : auth()->user();
            $firstStepOrder = (int) $steps->first()->sort_order;

            $requestId = DB::table('approval_requests')->insertGetId(static::filterColumns('approval_requests', [
                'company_id' => $run->company_id,
                'approval_workflow_id' => $workflow?->id,
                'approvable_type' => PayrollRun::class,
                'approvable_id' => $run->id,
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => static::documentNumber($run),
                'requester_user_id' => $requester?->id,
                'requester_name' => $requester?->name ?? $requester?->email,
                'status' => 'pending',
                'current_step_order' => $firstStepOrder,
                'amount_total' => (float) $run->net_total,
                'sent_at' => now(),
                'notes' => 'Solicitud de aprobación formal de pre-nómina.',
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            foreach ($steps as $step) {
                $approverUserId = static::resolveApproverUserId($step, $run, $requester);

                if (! $approverUserId) {
                    throw new \RuntimeException('No se pudo resolver el aprobador para el paso: ' . ($step->name ?? 'Aprobación'));
                }

                DB::table('approval_request_steps')->insert(static::filterColumns('approval_request_steps', [
                    'approval_request_id' => $requestId,
                    'approval_workflow_step_id' => $step->id,
                    'step_order' => (int) $step->sort_order,
                    'step_name' => $step->name ?: 'Aprobación de pre-nómina',
                    'approver_type' => $step->approver_type ?: 'specific_user',
                    'approver_user_id' => $approverUserId,
                    'approver_role_name' => $step->approver_role_name,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }

            $run->forceFill(static::filterModel($run, [
                'status' => 'pending_approval',
                'approval_status' => 'pending',
                'approval_request_id' => $requestId,
                'approval_requested_by_user_id' => $requester?->id,
                'approval_requested_at' => now(),
                'approved_by_user_id' => null,
                'approved_at' => null,
                'rejected_by_user_id' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'updated_by_user_id' => $requester?->id,
            ]))->save();

            $request = DB::table('approval_requests')->where('id', $requestId)->first();

            static::notifyCurrentApprovers($request);

            return $request;
        });
    }

    public static function approvePendingRequestForRun(PayrollRun $run, int $userId, ?string $comment = null): void
    {
        DB::transaction(function () use ($run, $userId, $comment): void {
            $request = static::pendingRequest($run);

            if (! $request) {
                throw new \RuntimeException('No hay solicitud pendiente para esta pre-nómina.');
            }

            $step = static::pendingStepForRequest((int) $request->id, $userId);

            if (! $step) {
                throw new \RuntimeException('No eres el aprobador actual de esta pre-nómina.');
            }

            DB::table('approval_request_steps')
                ->where('id', $step->id)
                ->update(static::filterColumns('approval_request_steps', [
                    'status' => 'approved',
                    'acted_by_user_id' => $userId,
                    'acted_by_name' => User::query()->find($userId)?->name,
                    'acted_at' => now(),
                    'comments' => $comment ?: 'Pre-nómina aprobada.',
                    'decision_reason' => $comment,
                    'updated_at' => now(),
                ]));

            $nextStep = DB::table('approval_request_steps')
                ->where('approval_request_id', $request->id)
                ->where('status', 'pending')
                ->orderBy('step_order')
                ->first();

            if ($nextStep) {
                DB::table('approval_requests')
                    ->where('id', $request->id)
                    ->update([
                        'current_step_order' => (int) $nextStep->step_order,
                        'updated_at' => now(),
                    ]);

                static::notifyCurrentApprovers(DB::table('approval_requests')->where('id', $request->id)->first());

                return;
            }

            DB::table('approval_requests')
                ->where('id', $request->id)
                ->update(static::filterColumns('approval_requests', [
                    'status' => 'approved',
                    'completed_at' => now(),
                    'last_decision_reason' => $comment,
                    'updated_at' => now(),
                ]));

            static::markApproved($request, $userId, $comment ?: 'Pre-nómina aprobada.');
        });
    }

    public static function rejectPendingRequestForRun(PayrollRun $run, int $userId, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \RuntimeException('Debes indicar el motivo del rechazo.');
        }

        DB::transaction(function () use ($run, $userId, $reason): void {
            $request = static::pendingRequest($run);

            if (! $request) {
                throw new \RuntimeException('No hay solicitud pendiente para esta pre-nómina.');
            }

            $step = static::pendingStepForRequest((int) $request->id, $userId);

            if (! $step) {
                throw new \RuntimeException('No eres el aprobador actual de esta pre-nómina.');
            }

            DB::table('approval_request_steps')
                ->where('id', $step->id)
                ->update(static::filterColumns('approval_request_steps', [
                    'status' => 'rejected',
                    'acted_by_user_id' => $userId,
                    'acted_by_name' => User::query()->find($userId)?->name,
                    'acted_at' => now(),
                    'comments' => $reason,
                    'decision_reason' => $reason,
                    'updated_at' => now(),
                ]));

            DB::table('approval_request_steps')
                ->where('approval_request_id', $request->id)
                ->where('status', 'pending')
                ->update(['status' => 'rejected', 'updated_at' => now()]);

            DB::table('approval_requests')
                ->where('id', $request->id)
                ->update(static::filterColumns('approval_requests', [
                    'status' => 'rejected',
                    'completed_at' => now(),
                    'last_decision_reason' => $reason,
                    'updated_at' => now(),
                ]));

            static::markRejected($request, $userId, $reason);
        });
    }

    public static function currentUserCanActOnPendingRequest(PayrollRun $run): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $request = static::pendingRequest($run);

        if (! $request) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false) || ($user->email ?? null) === 'admin@bexiaerp.com') {
            return true;
        }

        if (! ($user->can('nomina.prenomina.aprobar') || $user->can('approvals.approve'))) {
            return false;
        }

        return DB::table('approval_request_steps')
            ->where('approval_request_id', $request->id)
            ->where('step_order', (int) $request->current_step_order)
            ->where('status', 'pending')
            ->where('approver_user_id', $user->id)
            ->exists();
    }

    public static function pendingRequest(PayrollRun $run): ?object
    {
        if (! Schema::hasTable('approval_requests')) {
            return null;
        }

        return DB::table('approval_requests')
            ->where('document_type', self::DOCUMENT_TYPE)
            ->where('approvable_id', $run->id)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();
    }

    public static function pendingStepForRequest(int $requestId, int $userId): ?object
    {
        $request = DB::table('approval_requests')->where('id', $requestId)->first();

        if (! $request) {
            return null;
        }

        $query = DB::table('approval_request_steps')
            ->where('approval_request_id', $requestId)
            ->where('step_order', (int) $request->current_step_order)
            ->where('status', 'pending');

        $user = User::query()->find($userId);

        if (! $user || (! (bool) ($user->is_system_admin ?? false) && ($user->email ?? null) !== 'admin@bexiaerp.com')) {
            $query->where('approver_user_id', $userId);
        }

        return $query->first();
    }

    public static function markApproved(object $request, int $userId, ?string $comment = null): void
    {
        $run = PayrollRun::query()->find((int) $request->approvable_id);

        if (! $run) {
            return;
        }

        $run->forceFill(static::filterModel($run, [
            'status' => 'approved',
            'approval_status' => 'approved',
            'approval_request_id' => (int) $request->id,
            'approved_by_user_id' => $userId,
            'approved_at' => now(),
            'rejected_by_user_id' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'updated_by_user_id' => $userId,
        ]))->save();

        static::notifyRequester($request, 'Pre-nómina aprobada', $comment ?: 'Tu pre-nómina fue aprobada.', 'payroll_run_approved', '');
    }

    public static function markRejected(object $request, int $userId, string $reason): void
    {
        $run = PayrollRun::query()->find((int) $request->approvable_id);

        if (! $run) {
            return;
        }

        $run->forceFill(static::filterModel($run, [
            'status' => 'calculated',
            'approval_status' => 'rejected',
            'approval_request_id' => (int) $request->id,
            'approved_by_user_id' => null,
            'approved_at' => null,
            'rejected_by_user_id' => $userId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'updated_by_user_id' => $userId,
        ]))->save();

        static::notifyRequester($request, 'Pre-nómina rechazada', $reason, 'payroll_run_rejected', $reason);
    }

    public static function findApplicableWorkflow(PayrollRun $run): ?object
    {
        if (! Schema::hasTable('approval_workflows')) {
            return null;
        }

        return DB::table('approval_workflows')
            ->where('document_type', self::DOCUMENT_TYPE)
            ->where('is_active', true)
            ->where(function ($query) use ($run): void {
                $query->whereNull('company_id')
                    ->orWhere('company_id', $run->company_id);
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('amount_min')
                    ->orWhere('amount_min', '<=', (float) $run->net_total);
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('amount_max')
                    ->orWhere('amount_max', '>=', (float) $run->net_total);
            })
            ->orderByDesc('priority')
            ->orderBy('id')
            ->first();
    }

    protected static function workflowSteps(int $workflowId, PayrollRun $run)
    {
        if (! Schema::hasTable('approval_workflow_steps')) {
            return collect();
        }

        return DB::table('approval_workflow_steps')
            ->where('approval_workflow_id', $workflowId)
            ->where('is_active', true)
            ->where(function ($query) use ($run): void {
                $query->whereNull('amount_min')
                    ->orWhere('amount_min', '<=', (float) $run->net_total);
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('amount_max')
                    ->orWhere('amount_max', '>=', (float) $run->net_total);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    protected static function resolveApproverUserId(object $step, PayrollRun $run, ?User $requester): ?int
    {
        $type = (string) ($step->approver_type ?? '');

        if ($type === 'specific_user' && $step->approver_user_id) {
            return (int) $step->approver_user_id;
        }

        if ($type === 'role' && $step->approver_role_name && Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
            $userId = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', $step->approver_role_name)
                ->where('model_has_roles.model_type', User::class)
                ->orderBy('model_has_roles.model_id')
                ->value('model_has_roles.model_id');

            if ($userId) {
                return (int) $userId;
            }
        }

        if ($type === 'requester_manager' && $requester && isset($requester->approval_manager_user_id) && $requester->approval_manager_user_id) {
            return (int) $requester->approval_manager_user_id;
        }

        return static::fallbackApproverUserId((int) $run->company_id);
    }

    protected static function fallbackApproverUserId(int $companyId): ?int
    {
        $admin = User::query()
            ->where('email', 'admin@bexiaerp.com')
            ->first();

        if ($admin) {
            return (int) $admin->id;
        }

        $query = User::query();

        if (Schema::hasColumn('users', 'is_system_admin')) {
            $query->where('is_system_admin', true);
        }

        return $query->orderBy('id')->value('id');
    }

    public static function documentNumber(PayrollRun $run): string
    {
        return 'PRENOM-' . str_pad((string) $run->id, 6, '0', STR_PAD_LEFT);
    }

    public static function documentUrl(object $row): string
    {
        try {
            if (class_exists(\App\Filament\Resources\PayrollRunResource::class)) {
                return \App\Filament\Resources\PayrollRunResource::getUrl('edit', [
                    'record' => (int) ($row->approvable_id ?? 0),
                ]);
            }
        } catch (\Throwable) {
        }

        return '#';
    }

    protected static function notifyCurrentApprovers(?object $request): void
    {
        if (! $request || ! class_exists(\App\Support\BexiaUserNotification::class) || ! Schema::hasTable('approval_request_steps')) {
            return;
        }

        $steps = DB::table('approval_request_steps')
            ->where('approval_request_id', $request->id)
            ->where('step_order', $request->current_step_order)
            ->where('status', 'pending')
            ->whereNotNull('approver_user_id')
            ->get();

        foreach ($steps as $step) {
            \App\Support\BexiaUserNotification::send(
                (int) $step->approver_user_id,
                'Pre-nómina pendiente de aprobación',
                'Tienes una pre-nómina pendiente: ' . $request->document_number,
                static::documentUrl($request),
                (int) $request->company_id,
                'payroll_run_pending_approval',
                [
                    'approval_request_id' => (int) $request->id,
                    'document_type' => self::DOCUMENT_TYPE,
                    'document_number' => (string) $request->document_number,
                    'approvable_id' => (int) $request->approvable_id,
                ]
            );
        }
    }

    protected static function notifyRequester(object $request, string $title, string $body, string $type, string $reason): void
    {
        if (! class_exists(\App\Support\BexiaUserNotification::class)) {
            return;
        }

        $requesterId = (int) ($request->requester_user_id ?? 0);

        if ($requesterId <= 0) {
            return;
        }

        \App\Support\BexiaUserNotification::send(
            $requesterId,
            $title,
            $body,
            static::documentUrl($request),
            (int) ($request->company_id ?? 0),
            $type,
            [
                'approval_request_id' => (int) $request->id,
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => (string) ($request->document_number ?? ''),
                'approvable_id' => (int) ($request->approvable_id ?? 0),
                'reason' => $reason,
            ]
        );
    }

    protected static function filterColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);

        return array_filter(
            $data,
            fn ($value, $key): bool => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    protected static function filterModel(PayrollRun $run, array $data): array
    {
        $columns = Schema::getColumnListing($run->getTable());

        return array_filter(
            $data,
            fn ($value, $key): bool => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
