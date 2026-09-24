<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permissions = [
        'catalogs.fiscal.view',
        'catalogs.fiscal.create',
        'catalogs.fiscal.update',
        'catalogs.fiscal.delete',
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->permissions as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')
            ->whereIn('permission_id', $ids)
            ->delete();

        DB::table('model_has_permissions')
            ->whereIn('permission_id', $ids)
            ->delete();

        DB::table('permissions')
            ->whereIn('id', $ids)
            ->delete();
    }
};
