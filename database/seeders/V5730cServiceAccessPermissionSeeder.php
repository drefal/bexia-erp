<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class V5730cServiceAccessPermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $guard = 'web';

        $permissions = [
            'service.menu.view',

            'service.cases.view',
            'service.cases.create',
            'service.cases.update',
            'service.cases.delete',

            'service.repairs.view',
            'service.repairs.create',
            'service.repairs.update',
            'service.repairs.delete',

            'service.repairs.approve_warranty',
            'service.repairs.reject_warranty',
            'service.repairs.authorize_delivery',
            'service.repairs.reopen',

            'service.events.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        $adminRoleNames = [
            'admin',
            'Administrador',
            'Admin Empresa',
            'Admin Grupo',
            'Comercio - Administrador',
        ];

        $rolesQuery = Role::query()->whereIn('name', $adminRoleNames);

        $rolesQuery->get()->each(function (Role $role) use ($permissions): void {
            $companyId = null;

            if (Schema::hasColumn('roles', 'company_id') && isset($role->company_id)) {
                $companyId = $role->company_id ? (int) $role->company_id : null;
            }

            try {
                if ($companyId && class_exists(PermissionRegistrar::class)) {
                    app(PermissionRegistrar::class)->setPermissionsTeamId($companyId);
                }

                foreach ($permissions as $permission) {
                    $role->givePermissionTo($permission);
                }
            } catch (\Throwable $e) {
                DB::table('cache')->where('key', 'like', '%spatie.permission.cache%')->delete();
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (Schema::hasTable('cache')) {
            DB::table('cache')->where('key', 'like', '%spatie.permission.cache%')->delete();
        }
    }
}
