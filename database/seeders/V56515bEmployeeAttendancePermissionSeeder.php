<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class V56515bEmployeeAttendancePermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $guard = 'web';

        $permissions = [
            'rrhh.asistencias.ver',
            'rrhh.asistencias.crear',
            'rrhh.asistencias.editar',
            'rrhh.asistencias.eliminar',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        Role::query()
            ->whereIn('name', ['admin', 'Admin Empresa', 'Admin Grupo', 'Administrador'])
            ->where('guard_name', $guard)
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
