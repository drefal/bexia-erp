<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $companyId = DB::table('companies')
                ->where('name', 'CEDIS GRUPO L7')
                ->value('id');

            if (! $companyId) {
                return;
            }

            $adrianaId = DB::table('users')
                ->where('email', 'a.rojas@grupolinea7.com')
                ->value('id');

            if (! $adrianaId) {
                return;
            }

            $workflow = DB::table('approval_workflows')
                ->where('company_id', $companyId)
                ->where('document_type', 'expense_report')
                ->orderByDesc('is_active')
                ->orderByDesc('priority')
                ->first();

            if (! $workflow) {
                $workflowId = DB::table('approval_workflows')
                    ->insertGetId([
                        'company_id' => $companyId,
                        'name' => 'Aprobación de comprobaciones de gastos',
                        'document_type' => 'expense_report',
                        'is_active' => true,
                        'priority' => 100,
                        'amount_min' => null,
                        'amount_max' => null,
                        'applies_to_user_id' => null,
                        'applies_to_role_name' => null,
                        'applies_to_warehouse_id' => null,
                        'notes' => null,
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

            $stepData = [
                'name' => 'Aprobación de gasto',
                'is_active' => true,
                'approver_type' => 'specific_user',
                'approver_user_id' => $adrianaId,
                'approver_role_name' => null,
                'require_all' => false,
                'amount_min' => null,
                'amount_max' => null,
                'notes' => null,
                'approval_mode' => 'any',
                'updated_at' => now(),
            ];

            if ($step) {
                DB::table('approval_workflow_steps')
                    ->where('id', $step->id)
                    ->update($stepData);
            } else {
                DB::table('approval_workflow_steps')
                    ->insert(array_merge(
                        $stepData,
                        [
                            'approval_workflow_id' => $workflowId,
                            'sort_order' => 1,
                            'created_at' => now(),
                        ]
                    ));
            }
        });
    }

    public function down(): void
    {
        /*
         * Conservador:
         * no eliminamos automáticamente un flujo de aprobación que
         * pudiera haber recibido uso o modificaciones posteriores.
         */
    }
};
