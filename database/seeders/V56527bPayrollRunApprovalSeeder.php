<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class V56527bPayrollRunApprovalSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'nomina.prenomina.solicitar_aprobacion',
            'nomina.prenomina.aprobar',
            'nomina.prenomina.rechazar',
            'approvals.approve',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Role::query()
            ->whereIn('name', ['admin', 'Admin Empresa', 'Admin Grupo'])
            ->get()
            ->each(function (Role $role) use ($permissions): void {
                foreach ($permissions as $permission) {
                    $role->givePermissionTo($permission);
                }
            });

        if (! Schema::hasTable('approval_workflows') || ! Schema::hasTable('approval_workflow_steps') || ! Schema::hasTable('companies')) {
            return;
        }

        $adminId = DB::table('users')->where('email', 'admin@bexiaerp.com')->value('id')
            ?: DB::table('users')->orderBy('id')->value('id');

        if (! $adminId) {
            return;
        }

        $companies = DB::table('companies')->select('id')->orderBy('id')->get();

        foreach ($companies as $company) {
            $workflow = DB::table('approval_workflows')
                ->where('company_id', $company->id)
                ->where('document_type', 'payroll_run')
                ->first();

            if (! $workflow) {
                $workflowId = DB::table('approval_workflows')->insertGetId([
                    'company_id' => $company->id,
                    'name' => 'Aprobación de pre-nómina',
                    'document_type' => 'payroll_run',
                    'is_active' => true,
                    'priority' => 100,
                    'amount_min' => null,
                    'amount_max' => null,
                    'notes' => 'Flujo default para aprobar pre-nómina antes de cierre.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $workflowId = $workflow->id;

                DB::table('approval_workflows')
                    ->where('id', $workflowId)
                    ->update([
                        'is_active' => true,
                        'updated_at' => now(),
                    ]);
            }

            $step = DB::table('approval_workflow_steps')
                ->where('approval_workflow_id', $workflowId)
                ->where('sort_order', 1)
                ->first();

            $payload = [
                'name' => 'Aprobación pre-nómina',
                'is_active' => true,
                'approver_type' => 'specific_user',
                'approver_user_id' => $adminId,
                'approver_role_name' => null,
                'require_all' => false,
                'amount_min' => null,
                'amount_max' => null,
                'notes' => 'Aprobador default de pre-nómina.',
                'updated_at' => now(),
            ];

            if ($step) {
                DB::table('approval_workflow_steps')->where('id', $step->id)->update($payload);
            } else {
                DB::table('approval_workflow_steps')->insert(array_merge($payload, [
                    'approval_workflow_id' => $workflowId,
                    'sort_order' => 1,
                    'created_at' => now(),
                ]));
            }
        }
    }
}
