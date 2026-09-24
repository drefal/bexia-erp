<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permissions = [
        'inventory.adjustments.delete',

        'inventory.movements.create',
        'inventory.movements.update',
        'inventory.movements.delete',

        'inventory.warehouses.create',
        'inventory.warehouses.update',
        'inventory.warehouses.delete',

        'inventory.locations.create',
        'inventory.locations.update',
        'inventory.locations.delete',

        'inventory.location_types.create',
        'inventory.location_types.update',
        'inventory.location_types.delete',

        'inventory.operation_types.create',
        'inventory.operation_types.update',
        'inventory.operation_types.delete',

        'inventory.lots.manage',
        'inventory.serials.manage',
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
