<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permissions = [
        'rrhh.departamentos.ver',
        'rrhh.departamentos.crear',
        'rrhh.departamentos.editar',
        'rrhh.departamentos.eliminar',

        'rrhh.puestos.ver',
        'rrhh.puestos.crear',
        'rrhh.puestos.editar',
        'rrhh.puestos.eliminar',

        'rrhh.horarios.ver',
        'rrhh.horarios.crear',
        'rrhh.horarios.editar',
        'rrhh.horarios.eliminar',

        'rrhh.tipos_documento.ver',
        'rrhh.tipos_documento.crear',
        'rrhh.tipos_documento.editar',
        'rrhh.tipos_documento.eliminar',

        'rrhh.tipos_incidencia.ver',
        'rrhh.tipos_incidencia.crear',
        'rrhh.tipos_incidencia.editar',
        'rrhh.tipos_incidencia.eliminar',
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
