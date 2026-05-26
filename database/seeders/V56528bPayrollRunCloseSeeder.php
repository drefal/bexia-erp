<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class V56528bPayrollRunCloseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'nomina.prenomina.cerrar',
            'nomina.prenomina.reabrir',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Role::query()
            ->whereIn('name', ['admin', 'Admin Empresa', 'Admin Grupo', 'Super Admin', 'super_admin'])
            ->get()
            ->each(function (Role $role) use ($permissions): void {
                foreach ($permissions as $permission) {
                    $role->givePermissionTo($permission);
                }
            });
    }
}
