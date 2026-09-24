<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permissions = [
        'rrhh.empleados.ver',
        'rrhh.empleados.crear',
        'rrhh.empleados.editar',
        'rrhh.empleados.eliminar',

        'rrhh.credenciales.ver',
        'rrhh.credenciales.descargar',

        'rrhh.organigrama.ver',

        'rrhh.terminales.ver',
        'rrhh.terminales.crear',
        'rrhh.terminales.editar',
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
