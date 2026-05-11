<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RolBorradorPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::firstOrCreate([
            'name' => 'rol.view',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name' => 'rol.manage',
            'guard_name' => 'web',
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}