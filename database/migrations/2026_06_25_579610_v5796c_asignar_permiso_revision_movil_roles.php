<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('permissions')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('role_has_permissions')
        ) {
            return;
        }

        $now = now();

        $permissionNames = [
            'rrhh.asistencias.revisar_geocerca',
            'rrhh.asistencias.revisar_movil',
        ];

        foreach ($permissionNames as $permissionName) {
            $exists = DB::table('permissions')
                ->where('name', $permissionName)
                ->where('guard_name', 'web')
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $permissions = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->pluck('id', 'name');

        $targetRoles = DB::table('roles')
            ->where(function ($query): void {
                $query->where('name', 'ILIKE', '%admin%')
                    ->orWhere('name', 'ILIKE', '%rrhh%')
                    ->orWhere('name', 'ILIKE', '%recursos%')
                    ->orWhere('name', 'ILIKE', '%humano%');
            })
            ->get(['id']);

        foreach ($targetRoles as $role) {
            foreach ($permissions as $permissionId) {
                $exists = DB::table('role_has_permissions')
                    ->where('role_id', $role->id)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if (! $exists) {
                    DB::table('role_has_permissions')->insert([
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('permissions')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('role_has_permissions')
        ) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', [
                'rrhh.asistencias.revisar_geocerca',
                'rrhh.asistencias.revisar_movil',
            ])
            ->where('guard_name', 'web')
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        $targetRoleIds = DB::table('roles')
            ->where(function ($query): void {
                $query->where('name', 'ILIKE', '%admin%')
                    ->orWhere('name', 'ILIKE', '%rrhh%')
                    ->orWhere('name', 'ILIKE', '%recursos%')
                    ->orWhere('name', 'ILIKE', '%humano%');
            })
            ->pluck('id');

        if ($targetRoleIds->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')
            ->whereIn('role_id', $targetRoleIds)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
