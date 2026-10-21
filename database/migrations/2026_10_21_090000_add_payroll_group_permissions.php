<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $names = [
            'nomina.grupos.ver',
            'nomina.grupos.crear',
            'nomina.grupos.editar',
            'nomina.grupos.eliminar',
        ];

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $permissions = DB::table('permissions')
            ->whereIn('name', $names)
            ->where('guard_name', 'web')
            ->pluck('id', 'name');

        if ($permissions->count() !== count($names)) {
            throw new RuntimeException(
                'No se pudieron verificar los cuatro permisos.'
            );
        }

        $roles = DB::table('roles')
            ->whereIn('name', [
                'RRHH Administrador',
                'RRHH Asistente',
            ])
            ->where('guard_name', 'web')
            ->get(['id', 'name', 'company_id']);

        foreach ($roles as $role) {
            if (!$role->company_id) {
                throw new RuntimeException(
                    'Rol RRHH sin empresa.'
                );
            }

            $grants = $role->name === 'RRHH Administrador'
                ? [
                    'nomina.grupos.ver',
                    'nomina.grupos.crear',
                    'nomina.grupos.editar',
                ]
                : ['nomina.grupos.ver'];

            foreach ($grants as $name) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'role_id' => $role->id,
                    'permission_id' => $permissions[$name],
                ]);
            }
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No eliminar permisos ni sus asignaciones:
        // podrían estar siendo utilizados por administradores.
    }
};
