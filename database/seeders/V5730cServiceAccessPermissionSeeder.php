<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

class V5730cServiceAccessPermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $guard = 'web';

        $servicePermissions = [
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

        $serviceCashierCxcPermissions = [
            'account_receivables.view',
            'account_receivables.collect',
        ];

        $adminCxcPermissions = [
            'account_receivables.view',
            'account_receivables.create',
            'account_receivables.update',
            'account_receivables.cancel',
            'account_receivables.collect',
        ];

        $allPermissions = array_values(array_unique(array_merge(
            $servicePermissions,
            $serviceCashierCxcPermissions,
            $adminCxcPermissions,
        )));

        foreach ($allPermissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                [
                    'name' => $permission,
                    'guard_name' => $guard,
                ],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $allPermissions)
            ->pluck('id', 'name')
            ->all();

        $roleProfiles = [
            'Servicio - Recepción' => [
                'service.menu.view',
                'service.cases.view',
                'service.cases.create',
                'service.cases.update',
                'service.repairs.view',
                'service.repairs.create',
                'service.repairs.update',
                'service.events.view',
            ],

            'Servicio - Técnico' => [
                'service.menu.view',
                'service.cases.view',
                'service.repairs.view',
                'service.repairs.update',
                'service.events.view',
            ],

            'Servicio - Supervisor' => $servicePermissions,

            'Servicio - Cajero Reparaciones' => [
                'service.menu.view',
                'service.repairs.view',
                'service.events.view',
                'account_receivables.view',
                'account_receivables.collect',
            ],
        ];

        $companyIds = [];

        if (Schema::hasTable('companies')) {
            $companyIds = DB::table('companies')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        if ($companyIds === []) {
            $companyIds = [null];
        }

        foreach ($companyIds as $companyId) {
            foreach ($roleProfiles as $roleName => $permissions) {
                $roleId = $this->ensureRole($roleName, $guard, $companyId);

                $this->assignPermissionsToRole(
                    roleId: $roleId,
                    permissions: $permissions,
                    permissionIds: $permissionIds,
                );
            }
        }

        $adminRoleNames = [
            'admin',
            'Administrador',
            'Admin Empresa',
            'Admin Grupo',
            'Comercio - Administrador',
        ];

        $adminPermissions = array_values(array_unique(array_merge(
            $servicePermissions,
            $adminCxcPermissions,
        )));

        $adminRoles = DB::table('roles')
            ->whereIn('name', $adminRoleNames)
            ->pluck('id')
            ->all();

        foreach ($adminRoles as $roleId) {
            $this->assignPermissionsToRole(
                roleId: (int) $roleId,
                permissions: $adminPermissions,
                permissionIds: $permissionIds,
            );
        }

        $this->clearPermissionCache();
    }

    protected function ensureRole(string $name, string $guard, ?int $companyId): int
    {
        $query = DB::table('roles')
            ->where('name', $name)
            ->where('guard_name', $guard);

        if (Schema::hasColumn('roles', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $existingId = $query->value('id');

        if ($existingId) {
            DB::table('roles')
                ->where('id', $existingId)
                ->update([
                    'updated_at' => now(),
                ]);

            return (int) $existingId;
        }

        $data = [
            'name' => $name,
            'guard_name' => $guard,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('roles', 'company_id')) {
            $data['company_id'] = $companyId;
        }

        return (int) DB::table('roles')->insertGetId($data);
    }

    protected function assignPermissionsToRole(int $roleId, array $permissions, array $permissionIds): void
    {
        foreach ($permissions as $permissionName) {
            $permissionId = $permissionIds[$permissionName] ?? null;

            if (! $permissionId) {
                continue;
            }

            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => (int) $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    protected function clearPermissionCache(): void
    {
        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        if (Schema::hasTable('cache')) {
            DB::table('cache')->where('key', 'like', '%spatie.permission.cache%')->delete();
        }
    }
}
