<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permissionNames = [
        'expenses.admin',
        'expenses.view',
        'expenses.create',
        'expenses.update',
        'expenses.submit',
        'expenses.view_all',
        'expenses.create_for_others',
        'expenses.categories.manage',
        'petty_cash.view',
        'petty_cash.manage',
        'petty_cash.transfer',
    ];

    public function up(): void
    {
        foreach ($this->permissionNames as $name) {
            DB::table('permissions')->updateOrInsert(
                [
                    'name' => $name,
                    'guard_name' => 'web',
                ],
                []
            );
        }

        $alfredo = DB::table('users')
            ->where('email', 'a.zuniga@grupolinea7.com')
            ->first();

        $adriana = DB::table('users')
            ->where('email', 'a.rojas@grupolinea7.com')
            ->first();

        $alfredoCompanyIds = $alfredo
            ? DB::table('company_user')
                ->where('user_id', $alfredo->id)
                ->pluck('company_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $cedisId = DB::table('companies')
            ->where('name', 'CEDIS GRUPO L7')
            ->value('id');

        $adminCompanyIds = array_values(
            array_unique(
                array_filter([
                    ...$alfredoCompanyIds,
                    $cedisId ? (int) $cedisId : null,
                ])
            )
        );

        foreach ($adminCompanyIds as $companyId) {
            $role = DB::table('roles')
                ->where('name', 'Gastos - Administrador')
                ->where('guard_name', 'web')
                ->where('company_id', $companyId)
                ->first();

            if (! $role) {
                $roleId = DB::table('roles')->insertGetId([
                    'name' => 'Gastos - Administrador',
                    'guard_name' => 'web',
                    'company_id' => $companyId,
                ]);
            } else {
                $roleId = $role->id;
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('name', $this->permissionNames)
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }

            if ($alfredo && in_array($companyId, $alfredoCompanyIds, true)) {
                DB::table('model_has_roles')->updateOrInsert([
                    'role_id' => $roleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $alfredo->id,
                    'company_id' => $companyId,
                ]);
            }

            if (
                $adriana
                && $cedisId
                && $companyId === (int) $cedisId
            ) {
                DB::table('model_has_roles')->updateOrInsert([
                    'role_id' => $roleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $adriana->id,
                    'company_id' => $companyId,
                ]);
            }
        }

        if ($adriana && $cedisId) {
            $workflowIds = DB::table('approval_workflows')
                ->where('company_id', $cedisId)
                ->where('document_type', 'expense_report')
                ->where('is_active', true)
                ->pluck('id');

            DB::table('approval_workflow_steps')
                ->whereIn('approval_workflow_id', $workflowIds)
                ->where('is_active', true)
                ->update([
                    'approver_type' => 'specific_user',
                    'approver_user_id' => $adriana->id,
                    'approver_role_name' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Reversión conservadora: no eliminar asignaciones administrativas
        // que podrían haber sido modificadas posteriormente.
    }
};
