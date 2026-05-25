<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class V5641fHrPayrollPermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $guard = 'web';

        $permissions = [
            'rrhh.catalogos.ver',
            'rrhh.catalogos.crear',
            'rrhh.catalogos.editar',
            'rrhh.catalogos.eliminar',

            'nomina.catalogos.ver',
            'nomina.catalogos.crear',
            'nomina.catalogos.editar',
            'nomina.catalogos.eliminar',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        $adminRoleNames = [
            'admin',
            'Admin Empresa',
            'Admin Grupo',
            'Administrador',
        ];

        Role::query()
            ->whereIn('name', $adminRoleNames)
            ->where('guard_name', $guard)
            ->get()
            ->each(function (Role $role) use ($permissions) {
                $role->givePermissionTo($permissions);
            });

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('cache')->where('key', 'like', '%spatie.permission.cache%')->delete();
    }
}
