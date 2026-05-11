<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SalidasCatalogPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        Permission::findOrCreate('salidas.configurar', 'web');

        $roles = ['Super Admin', 'Admin Grupo'];

        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->first();

            if ($role) {
                $role->givePermissionTo('salidas.configurar');
            }
        }
    }
}
