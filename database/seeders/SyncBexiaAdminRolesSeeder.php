<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncBexiaAdminRolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        $permissions = [
            'company.view',
            'company.update',

            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            'salidas.ver',
            'salidas.create',
            'salidas.update',
            'salidas.delete',
            'salidas.enviar_pdf',
            'salidas.ver_todas',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $guard,
            ]);
        }

        $adminEmpresa = Role::firstOrCreate([
            'name' => 'Admin Empresa',
            'guard_name' => $guard,
            'company_id' => null,
        ]);

        $adminGrupo = Role::firstOrCreate([
            'name' => 'Admin Grupo',
            'guard_name' => $guard,
            'company_id' => null,
        ]);

        $adminEmpresa->syncPermissions([
            'company.view',
            'company.update',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'salidas.ver',
            'salidas.create',
            'salidas.update',
            'salidas.delete',
            'salidas.enviar_pdf',
        ]);

        $adminGrupo->syncPermissions([
            'company.view',
            'company.update',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'salidas.ver',
            'salidas.create',
            'salidas.update',
            'salidas.delete',
            'salidas.enviar_pdf',
            'salidas.ver_todas',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Roles Admin Empresa y Admin Grupo sincronizados.');
    }
}
