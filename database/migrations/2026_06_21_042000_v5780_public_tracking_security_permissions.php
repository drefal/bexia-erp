<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();

        $permissions = [
            'service.repairs.public_tracking.view',
            'service.repairs.public_tracking.regenerate',
            'service.repairs.public_tracking.disable',
        ];

        foreach ($permissions as $name) {
            $exists = DB::table('permissions')->where('name', $name)->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $grant = function (string $permissionName, array $basePermissionNames): void {
            $permissionId = DB::table('permissions')->where('name', $permissionName)->value('id');

            if (! $permissionId) {
                return;
            }

            $basePermissionIds = DB::table('permissions')
                ->whereIn('name', $basePermissionNames)
                ->pluck('id')
                ->all();

            if ($basePermissionIds === []) {
                return;
            }

            $roleIds = DB::table('role_has_permissions')
                ->whereIn('permission_id', $basePermissionIds)
                ->pluck('role_id')
                ->unique()
                ->values()
                ->all();

            foreach ($roleIds as $roleId) {
                $exists = DB::table('role_has_permissions')
                    ->where('permission_id', $permissionId)
                    ->where('role_id', $roleId)
                    ->exists();

                if (! $exists) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                }
            }
        };

        $grant('service.repairs.public_tracking.view', [
            'service.repairs.view',
            'service.repairs.update',
        ]);

        $grant('service.repairs.public_tracking.regenerate', [
            'service.repairs.update',
        ]);

        $grant('service.repairs.public_tracking.disable', [
            'service.repairs.update',
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissions = [
            'service.repairs.public_tracking.view',
            'service.repairs.public_tracking.regenerate',
            'service.repairs.public_tracking.disable',
        ];

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissions)
            ->pluck('id')
            ->all();

        if ($permissionIds !== [] && Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        DB::table('permissions')
            ->whereIn('name', $permissions)
            ->delete();
    }
};
