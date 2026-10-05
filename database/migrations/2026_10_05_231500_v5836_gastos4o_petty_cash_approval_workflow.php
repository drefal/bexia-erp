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

        $company = DB::table('companies')
            ->where('name', 'CEDIS GRUPO L7')
            ->first();

        $adriana = DB::table('users')
            ->where('email', 'a.rojas@grupolinea7.com')
            ->first();

        if (! $company || ! $adriana) {
            return;
        }

        $now = now();

        $workflow = DB::table('approval_workflows')
            ->where('company_id', $company->id)
            ->where('document_type', 'petty_cash_transfer_request')
            ->orderByDesc('id')
            ->first();

        $workflowData = [
            'company_id' => $company->id,
            'name' => 'Aprobación de movimientos de caja chica',
            'document_type' => 'petty_cash_transfer_request',
            'priority' => 100,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('approval_workflows', 'is_active')) {
            $workflowData['is_active'] = true;
        }

        foreach ([
            'amount_min',
            'amount_max',
            'applies_to_user_id',
            'applies_to_role_name',
            'applies_to_warehouse_id',
            'notes',
        ] as $column) {
            if (Schema::hasColumn('approval_workflows', $column)) {
                $workflowData[$column] = null;
            }
        }

        if ($workflow) {
            DB::table('approval_workflows')
                ->where('id', $workflow->id)
                ->update($workflowData);

            $workflowId = (int) $workflow->id;
        } else {
            $workflowData['created_at'] = $now;

            $workflowId = (int) DB::table('approval_workflows')
                ->insertGetId($workflowData);
        }

        $step = DB::table('approval_workflow_steps')
            ->where('approval_workflow_id', $workflowId)
            ->where('sort_order', 1)
            ->orderBy('id')
            ->first();

        $stepData = [
            'approval_workflow_id' => $workflowId,
            'sort_order' => 1,
            'name' => 'Aprobación de caja chica',
            'approver_type' => 'specific_user',
            'approver_user_id' => $adriana->id,
            'approver_role_name' => null,
            'require_all' => false,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('approval_workflow_steps', 'is_active')) {
            $stepData['is_active'] = true;
        }

        if (Schema::hasColumn('approval_workflow_steps', 'approval_mode')) {
            $stepData['approval_mode'] = 'any';
        }

        foreach ([
            'amount_min',
            'amount_max',
            'notes',
        ] as $column) {
            if (Schema::hasColumn('approval_workflow_steps', $column)) {
                $stepData[$column] = null;
            }
        }

        if ($step) {
            DB::table('approval_workflow_steps')
                ->where('id', $step->id)
                ->update($stepData);
        } else {
            $stepData['created_at'] = $now;

            DB::table('approval_workflow_steps')
                ->insert($stepData);
        }
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('approval_workflows')
            || ! Schema::hasTable('approval_workflow_steps')
        ) {
            return;
        }

        $workflowIds = DB::table('approval_workflows')
            ->where('document_type', 'petty_cash_transfer_request')
            ->where('name', 'Aprobación de movimientos de caja chica')
            ->pluck('id');

        if ($workflowIds->isEmpty()) {
            return;
        }

        DB::table('approval_workflow_steps')
            ->whereIn('approval_workflow_id', $workflowIds)
            ->delete();

        DB::table('approval_workflows')
            ->whereIn('id', $workflowIds)
            ->delete();
    }
};
