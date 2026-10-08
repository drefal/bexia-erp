<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'name' => 'sales.dashboard.view',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'sales.dashboard.view')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->delete();

        DB::table('model_has_permissions')
            ->where('permission_id', $permissionId)
            ->delete();

        DB::table('permissions')
            ->where('id', $permissionId)
            ->delete();
    }
};
