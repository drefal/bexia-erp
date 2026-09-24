<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permissions = [
        'inventory.stock.view',
        'inventory.stock.cost.view',
        'inventory.as_of_date.view',
        'inventory.kardex.view',
        'inventory.traceability.view',
        'inventory.valuation.view',
        'inventory.costing_diagnostic.view',
        'inventory.adjustments.view',
        'inventory.adjustments.audit.view',
        'inventory.movements.view',
        'inventory.warehouses.view',
        'inventory.locations.view',
        'inventory.location_types.view',
        'inventory.operation_types.view',
        'inventory.lots.view',
        'inventory.serials.view',
        'inventory.serials.audit.view',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach ($this->permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $ids = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        if (Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')
                ->whereIn('permission_id', $ids)
                ->delete();
        }

        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')
                ->whereIn('permission_id', $ids)
                ->delete();
        }

        DB::table('permissions')
            ->whereIn('id', $ids)
            ->delete();
    }
};
