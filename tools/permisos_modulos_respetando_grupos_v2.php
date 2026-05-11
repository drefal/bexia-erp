<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

$apply = getenv('BEXIA_APPLY') === '1';

function bxTitle(string $title): void
{
    echo PHP_EOL;
    echo "======================================" . PHP_EOL;
    echo $title . PHP_EOL;
    echo "======================================" . PHP_EOL;
}

function bxRequireTable(string $table): void
{
    if (! Schema::hasTable($table)) {
        throw new RuntimeException("Falta tabla requerida: {$table}");
    }
}

function bxRequireColumn(string $table, string $column): void
{
    if (! Schema::hasColumn($table, $column)) {
        throw new RuntimeException("Falta columna requerida: {$table}.{$column}");
    }
}

function bxNow(): string
{
    return now()->toDateTimeString();
}

function bxRoleId(int $companyId, string $roleName): ?int
{
    $role = DB::table('roles')
        ->where('company_id', $companyId)
        ->where('name', $roleName)
        ->where('guard_name', 'web')
        ->first();

    return $role ? (int) $role->id : null;
}

function bxEnsureRole(int $companyId, string $roleName, bool $apply): ?int
{
    $existingId = bxRoleId($companyId, $roleName);

    if ($existingId) {
        return $existingId;
    }

    if (! $apply) {
        return null;
    }

    return (int) DB::table('roles')->insertGetId([
        'company_id' => $companyId,
        'name' => $roleName,
        'guard_name' => 'web',
        'created_at' => bxNow(),
        'updated_at' => bxNow(),
    ]);
}

function bxPermissionId(string $permissionName): ?int
{
    $permission = DB::table('permissions')
        ->where('name', $permissionName)
        ->where('guard_name', 'web')
        ->first();

    return $permission ? (int) $permission->id : null;
}

function bxEnsurePermission(string $permissionName, bool $apply): ?int
{
    $existingId = bxPermissionId($permissionName);

    if ($existingId) {
        return $existingId;
    }

    if (! $apply) {
        return null;
    }

    return (int) DB::table('permissions')->insertGetId([
        'name' => $permissionName,
        'guard_name' => 'web',
        'created_at' => bxNow(),
        'updated_at' => bxNow(),
    ]);
}

function bxGivePermissionToRole(int $roleId, int $permissionId, bool $apply): void
{
    $exists = DB::table('role_has_permissions')
        ->where('role_id', $roleId)
        ->where('permission_id', $permissionId)
        ->exists();

    if ($exists || ! $apply) {
        return;
    }

    DB::table('role_has_permissions')->insert([
        'role_id' => $roleId,
        'permission_id' => $permissionId,
    ]);
}

function bxAssignRoleToUser(int $userId, int $roleId, int $companyId, bool $apply): void
{
    $exists = DB::table('model_has_roles')
        ->where('role_id', $roleId)
        ->where('model_type', 'App\\Models\\User')
        ->where('model_id', $userId)
        ->where('company_id', $companyId)
        ->exists();

    if ($exists || ! $apply) {
        return;
    }

    DB::table('model_has_roles')->insert([
        'role_id' => $roleId,
        'model_type' => 'App\\Models\\User',
        'model_id' => $userId,
        'company_id' => $companyId,
    ]);
}

function bxEnsureCompanyUser(int $companyId, int $userId, bool $apply): void
{
    $exists = DB::table('company_user')
        ->where('company_id', $companyId)
        ->where('user_id', $userId)
        ->exists();

    if ($exists || ! $apply) {
        return;
    }

    DB::table('company_user')->insert([
        'company_id' => $companyId,
        'user_id' => $userId,
    ]);
}

bxTitle('1) Validando estructura');

foreach ([
    'companies',
    'company_groups',
    'company_group_user',
    'company_user',
    'users',
    'roles',
    'permissions',
    'model_has_roles',
    'role_has_permissions',
] as $table) {
    bxRequireTable($table);
    echo "OK tabla: {$table}" . PHP_EOL;
}

foreach ([
    ['companies', 'id'],
    ['companies', 'name'],
    ['companies', 'company_group_id'],
    ['company_group_user', 'company_group_id'],
    ['company_group_user', 'user_id'],
    ['company_group_user', 'is_group_admin'],
    ['company_user', 'company_id'],
    ['company_user', 'user_id'],
    ['roles', 'company_id'],
    ['roles', 'name'],
    ['permissions', 'name'],
    ['model_has_roles', 'company_id'],
] as $pair) {
    bxRequireColumn($pair[0], $pair[1]);
    echo "OK columna: {$pair[0]}.{$pair[1]}" . PHP_EOL;
}

$permissionsByModule = [
    'accounting' => [
        'accounting.view',
        'accounting.create',
        'accounting.update',
        'accounting.delete',
        'accounting.configure',
        'accounting.close_periods',
    ],
    'inventory' => [
        'inventory.view',
        'inventory.create',
        'inventory.update',
        'inventory.delete',
        'inventory.adjust_stock',
        'inventory.transfer_stock',
    ],
    'purchases' => [
        'purchases.view',
        'purchases.create',
        'purchases.update',
        'purchases.delete',
        'purchases.approve',
        'purchases.receive',
        'purchases.invoice',
    ],
    'sales' => [
        'sales.view',
        'sales.create',
        'sales.update',
        'sales.delete',
        'sales.approve',
        'sales.deliver',
        'sales.invoice',
    ],
    'pos' => [
        'pos.view',
        'pos.open_shift',
        'pos.sell',
        'pos.discount',
        'pos.refund',
        'pos.close_shift',
        'pos.cash_count',
    ],
    'invoicing' => [
        'invoicing.view',
        'invoicing.create',
        'invoicing.stamp',
        'invoicing.cancel',
        'invoicing.download_xml',
        'invoicing.download_pdf',
    ],
    'reports' => [
        'reports.view',
        'reports.accounting',
        'reports.inventory',
        'reports.sales',
        'reports.purchases',
    ],
];

$roleTemplates = [
    'Contabilidad' => [
        'accounting.view',
        'accounting.create',
        'accounting.update',
        'accounting.delete',
        'accounting.configure',
        'accounting.close_periods',
        'reports.view',
        'reports.accounting',
    ],
    'Inventarios' => [
        'inventory.view',
        'inventory.create',
        'inventory.update',
        'inventory.delete',
        'inventory.adjust_stock',
        'inventory.transfer_stock',
        'reports.view',
        'reports.inventory',
    ],
    'Compras' => [
        'purchases.view',
        'purchases.create',
        'purchases.update',
        'purchases.delete',
        'purchases.approve',
        'purchases.receive',
        'purchases.invoice',
        'inventory.view',
        'reports.view',
        'reports.purchases',
    ],
    'Ventas' => [
        'sales.view',
        'sales.create',
        'sales.update',
        'sales.delete',
        'sales.approve',
        'sales.deliver',
        'sales.invoice',
        'inventory.view',
        'reports.view',
        'reports.sales',
    ],
    'Punto de Venta' => [
        'pos.view',
        'pos.open_shift',
        'pos.sell',
        'pos.discount',
        'pos.refund',
        'pos.close_shift',
        'pos.cash_count',
        'inventory.view',
        'sales.view',
    ],
    'Facturación' => [
        'invoicing.view',
        'invoicing.create',
        'invoicing.stamp',
        'invoicing.cancel',
        'invoicing.download_xml',
        'invoicing.download_pdf',
        'sales.view',
        'reports.view',
        'reports.sales',
    ],
    'Reportes' => [
        'reports.view',
        'reports.accounting',
        'reports.inventory',
        'reports.sales',
        'reports.purchases',
    ],
];

$adminRoleNames = [
    'admin',
    'Admin Empresa',
    'Admin Grupo',
];

$allModulePermissions = collect($permissionsByModule)
    ->flatten()
    ->unique()
    ->values();

$companies = DB::table('companies')
    ->select('id', 'name', 'company_group_id')
    ->orderBy('company_group_id')
    ->orderBy('id')
    ->get();

bxTitle('2) Vista previa de permisos');

foreach ($permissionsByModule as $module => $permissionNames) {
    $existing = DB::table('permissions')
        ->whereIn('name', $permissionNames)
        ->count();

    echo "{$module}: {$existing}/" . count($permissionNames) . " existentes" . PHP_EOL;

    foreach ($permissionNames as $permissionName) {
        $exists = DB::table('permissions')->where('name', $permissionName)->exists();
        echo ($exists ? 'Existe: ' : 'Crear: ') . $permissionName . PHP_EOL;
    }
}

bxTitle('3) Vista previa de roles por empresa');

foreach ($companies as $company) {
    echo PHP_EOL . "Empresa {$company->id}: {$company->name} | grupo {$company->company_group_id}" . PHP_EOL;

    foreach (array_keys($roleTemplates) as $roleName) {
        $exists = bxRoleId((int) $company->id, $roleName);
        echo ($exists ? 'Existe: ' : 'Crear: ') . $roleName . PHP_EOL;
    }

    foreach ($adminRoleNames as $roleName) {
        $exists = bxRoleId((int) $company->id, $roleName);
        echo ($exists ? 'Existe admin: ' : 'Crear admin: ') . $roleName . PHP_EOL;
    }
}

bxTitle('4) Vista previa Admin Grupo');

$groupAdmins = DB::table('company_group_user')
    ->where('is_group_admin', true)
    ->orderBy('company_group_id')
    ->orderBy('user_id')
    ->get();

if ($groupAdmins->isEmpty()) {
    echo "No hay usuarios con is_group_admin=true." . PHP_EOL;
}

foreach ($groupAdmins as $groupAdmin) {
    $user = DB::table('users')->where('id', $groupAdmin->user_id)->first();

    echo PHP_EOL;
    echo "Usuario {$groupAdmin->user_id}: " . ($user->email ?? 'sin email') . PHP_EOL;
    echo "Admin grupo: {$groupAdmin->company_group_id}" . PHP_EOL;

    $targetCompanies = $companies
        ->where('company_group_id', $groupAdmin->company_group_id)
        ->values();

    foreach ($targetCompanies as $company) {
        echo "- Administrara empresa {$company->id}: {$company->name}" . PHP_EOL;
    }
}

if (! $apply) {
    bxTitle('5) Resultado');
    echo "VISTA PREVIA: no se hizo ningun cambio." . PHP_EOL;
    echo "Para aplicar ejecuta: APPLY=1 ./permisos_modulos_respetando_grupos_v2.sh" . PHP_EOL;
    return;
}

bxTitle('5) Aplicando cambios');

$backup = [
    'created_at' => bxNow(),
    'roles' => DB::table('roles')->orderBy('id')->get(),
    'permissions' => DB::table('permissions')->orderBy('id')->get(),
    'role_has_permissions' => DB::table('role_has_permissions')->orderBy('role_id')->orderBy('permission_id')->get(),
    'model_has_roles' => DB::table('model_has_roles')->orderBy('model_id')->orderBy('company_id')->get(),
    'company_user' => DB::table('company_user')->orderBy('company_id')->orderBy('user_id')->get(),
];

$backupFile = storage_path('app/backup_permisos_grupos_v2_' . date('Ymd_His') . '.json');
file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Backup creado: {$backupFile}" . PHP_EOL;

DB::transaction(function () use (
    $permissionsByModule,
    $roleTemplates,
    $adminRoleNames,
    $allModulePermissions,
    $companies,
    $groupAdmins,
    $apply
): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($allModulePermissions as $permissionName) {
        bxEnsurePermission($permissionName, true);
    }

    $allPermissionIds = DB::table('permissions')
        ->orderBy('name')
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->all();

    foreach ($companies as $company) {
        $companyId = (int) $company->id;

        foreach ($roleTemplates as $roleName => $permissionNames) {
            $roleId = bxEnsureRole($companyId, $roleName, true);

            foreach ($permissionNames as $permissionName) {
                $permissionId = bxEnsurePermission($permissionName, true);
                bxGivePermissionToRole($roleId, $permissionId, true);
            }
        }

        foreach ($adminRoleNames as $roleName) {
            $roleId = bxEnsureRole($companyId, $roleName, true);

            foreach ($allPermissionIds as $permissionId) {
                bxGivePermissionToRole($roleId, $permissionId, true);
            }
        }
    }

    foreach ($groupAdmins as $groupAdmin) {
        $userId = (int) $groupAdmin->user_id;
        $groupId = (int) $groupAdmin->company_group_id;

        $targetCompanies = $companies
            ->where('company_group_id', $groupId)
            ->values();

        foreach ($targetCompanies as $company) {
            $companyId = (int) $company->id;

            bxEnsureCompanyUser($companyId, $userId, true);

            $adminGrupoRoleId = bxEnsureRole($companyId, 'Admin Grupo', true);

            bxAssignRoleToUser($userId, $adminGrupoRoleId, $companyId, true);
        }
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

echo "Cambios aplicados correctamente." . PHP_EOL;
