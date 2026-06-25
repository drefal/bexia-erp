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

        $revisarMovilId = DB::table('permissions')
            ->where('name', 'rrhh.asistencias.revisar_movil')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $revisarMovilId) {
            $revisarMovilId = DB::table('permissions')->insertGetId([
                'name' => 'rrhh.asistencias.revisar_movil',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $revisarGeocercaId = DB::table('permissions')
            ->where('name', 'rrhh.asistencias.revisar_geocerca')
            ->where('guard_name', 'web')
            ->value('id');

        if (
            $revisarGeocercaId
            && Schema::hasTable('role_has_permissions')
        ) {
            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $revisarGeocercaId)
                ->pluck('role_id')
                ->unique()
                ->values();

            foreach ($roleIds as $roleId) {
                $exists = DB::table('role_has_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $revisarMovilId)
                    ->exists();

                if (! $exists) {
                    DB::table('role_has_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $revisarMovilId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'rrhh.asistencias.revisar_movil')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        if (Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')
                ->where('permission_id', $permissionId)
                ->delete();
        }

        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')
                ->where('permission_id', $permissionId)
                ->delete();
        }

        DB::table('permissions')
            ->where('id', $permissionId)
            ->delete();
    }
};
