<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $permissions = [
        'inventory.products.view',
        'inventory.products.create',
        'inventory.products.update',
        'inventory.products.delete',

        'inventory.product_categories.view',
        'inventory.product_categories.create',
        'inventory.product_categories.update',

        'inventory.product_attributes.view',
        'inventory.product_attributes.create',
        'inventory.product_attributes.update',
        'inventory.product_attributes.delete',
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
