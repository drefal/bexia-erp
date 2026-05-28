<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('companies')
            || ! Schema::hasTable('users')
            || ! Schema::hasTable('approval_workflows')
            || ! Schema::hasTable('approval_workflow_steps')
        ) {
            return;
        }

        $documentType = 'treasury_cash_transfer_request';
        $approverUserId = (int) (DB::table('users')->orderBy('id')->value('id') ?? 0);

        if ($approverUserId <= 0) {
            return;
        }

        foreach (DB::table('companies')->orderBy('id')->get() as $company) {
            $companyId = (int) $company->id;

            $workflow = DB::table('approval_workflows')
                ->where('company_id', $companyId)
                ->where('document_type', $documentType)
                ->orderBy('id')
                ->first();

            $workflowPayload = $this->payload('approval_workflows', [
                'company_id' => $companyId,
                'name' => 'Aprobación de solicitudes de efectivo',
                'document_type' => $documentType,
                'is_active' => true,
                'priority' => 100,
                'amount_min' => null,
                'amount_max' => null,
                'applies_to_user_id' => null,
                'applies_to_role_name' => null,
                'applies_to_warehouse_id' => null,
                'notes' => 'Flujo default para retiros PDV, traspasos de caja y solicitudes de efectivo. Puede ajustarse por empresa.',
                'updated_at' => now(),
            ]);

            if ($workflow) {
                DB::table('approval_workflows')->where('id', $workflow->id)->update($workflowPayload);
                $workflowId = (int) $workflow->id;
            } else {
                $workflowPayload = array_merge($workflowPayload, $this->payload('approval_workflows', [
                    'created_at' => now(),
                ]));

                $workflowId = (int) DB::table('approval_workflows')->insertGetId($workflowPayload);
            }

            $step = DB::table('approval_workflow_steps')
                ->where('approval_workflow_id', $workflowId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            $stepPayload = $this->payload('approval_workflow_steps', [
                'approval_workflow_id' => $workflowId,
                'sort_order' => 1,
                'name' => 'Aprobación Tesorería',
                'is_active' => true,
                'approver_type' => 'specific_user',
                'approver_user_id' => $approverUserId,
                'approver_role_name' => null,
                'require_all' => false,
                'amount_min' => null,
                'amount_max' => null,
                'notes' => 'Aprobador default creado automáticamente. Ajustar en Configuración empresa > Flujos de aprobación.',
                'updated_at' => now(),
            ]);

            if ($step) {
                DB::table('approval_workflow_steps')->where('id', $step->id)->update($stepPayload);
            } else {
                $stepPayload = array_merge($stepPayload, $this->payload('approval_workflow_steps', [
                    'created_at' => now(),
                ]));

                DB::table('approval_workflow_steps')->insert($stepPayload);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('approval_workflows') || ! Schema::hasTable('approval_workflow_steps')) {
            return;
        }

        $workflowIds = DB::table('approval_workflows')
            ->where('document_type', 'treasury_cash_transfer_request')
            ->where('name', 'Aprobación de solicitudes de efectivo')
            ->pluck('id');

        if ($workflowIds->isNotEmpty()) {
            DB::table('approval_workflow_steps')
                ->whereIn('approval_workflow_id', $workflowIds)
                ->delete();

            DB::table('approval_workflows')
                ->whereIn('id', $workflowIds)
                ->delete();
        }
    }

    private function payload(string $table, array $values): array
    {
        return collect($values)
            ->filter(fn ($value, string $column): bool => Schema::hasColumn($table, $column))
            ->all();
    }
};
