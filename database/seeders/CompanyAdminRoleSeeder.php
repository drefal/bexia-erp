<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CompanyAdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'company.view',
            'company.update',

            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            'branches.view',
            'branches.create',
            'branches.update',
            'branches.delete',

            'employees.view',
            'employees.create',
            'employees.update',
            'employees.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate('company_admin', 'web');
        $role->syncPermissions($permissions);
    }
}
