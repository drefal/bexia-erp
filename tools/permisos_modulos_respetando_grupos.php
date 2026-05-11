<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

$apply = getenv('BEXIA_APPLY') === '1';

function titleLine(string $title): void
{
    echo PHP_EOL;
    echo "======================================" . PHP_EOL;
    echo $title . PHP_EOL;
    echo "======================================" . PHP_EOL;
}

function requireTable(string $table): void
{
    if (! Schema::hasTable($table)) {
        throw new RuntimeException("Falta tabla requerida: {$table}");
    }
}

function boolValue($value): bool
{
    return in_array($value, [true, 1, '1', 't', 'true', 'yes', 'si'], true);
}

titleLine('1) Validando estructura requerida');

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
    requireTable($table);
    echo "OK tabla: {$table}" . PHP_EOL;
}

foreach ([
    ['companies', 'company_group_id'],
    ['company_group_user', 'company_group_id'],
    ['company_group_user', 'user_id'],
    ['company_group_user', 'is_group_admin'],
    ['company_user', 'company_id'],
    ['company_user', 'user_id'],
    ['roles', 'company_id'],
    ['model_has_roles', 'company_id'],
] as [$table, $column]) {
    if (! Schema::hasColumn($table, $column)) {
        throw new RuntimeException("Falta columna requerida: {$table}.{$column}");
    }

    echo "OK columna: {$table}.{$column}" . PHP_EOL;
}

titleLine('2) Definiendo permisos base');

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

$modulePermissionNames = collect($permissionsByModule)->flatten()->unique()->values();

$missingPermissions = $modulePermissionNames
    ->filter(fn (string $permission) => ! DB::table('permissions')->where('name', $permission)->exists())
    ->values();

echo "Permisos nuevos a crear: {$missingPermissions->count()}" . PHP_EOL;
$missingPermissions->each(fn ($permission) => echo "- {$permission}" . PHP_EOL);

titleLine('3) Empresas detectadas');

$companies = DB::table('companies')
    ->select('id', 'name', 'company_group_id')
    ->orderBy('company_group_id')
    ->orderBy('id')
    ->get();

$companies->each(function ($company): void {
    echo "Empresa {$company->id}: {$company->name} | grupo {$company->company_group_id}" . PHP_EOL;
});

$companyIds = $companies->pluck('id')->all();

titleLine('4) Roles por empresa que se aseguraran');

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

foreach ($companies as $company) {
    echo PHP_EOL . "Empresa {$company->id} - {$company->name}" . PHP_EOL;

    foreach (array_keys($roleTemplates) as $roleName) {
        $exists = DB::table('roles')
            ->where('company_id', $company->id)
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->exists();

        echo ($exists ? 'Existe: ' : 'Crear: ') . $roleName . PHP_EOL;
    }

    foreach ($adminRoleNames as $roleName) {
        $exists = DB::table('roles')
            ->where('company_id', $company->id)
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->exists();

        echo ($exists ? 'Existe admin: ' : 'Crear admin: ') . $roleName . PHP_EOL;
    }
}

titleLine('5) Admin Grupo detectados');

$groupAdmins = DB::table('company_group_user')
    ->where('is_group_admin', true)
    ->orderBy('company_group_id')
    ->orderBy('user_id')
    ->get();

if ($groupAdmins->isEmpty()) {
    echo "No hay registros is_group_admin=true en company_group_user." . PHP_EOL;
}

foreach ($groupAdmins as $groupAdmin) {
    $user = DB::table('users')->where('id', $groupAdmin->user_id)->first();

    echo PHP_EOL;
    echo "Usuario {$groupAdmin->user_id}: " . ($user->email ?? 'sin email') . PHP_EOL;
    echo "Admin del grupo {$groupAdmin->company_group_id}" . PHP_EOL;

    $targetCompanies = $companies
        ->where('company_group_id', $groupAdmin->company_group_id)
        ->values();

    foreach ($targetCompanies as $company) {
        echo "- Debe administrar empresa {$company->id}: {$company->name}" . PHP_EOL;
    }
}

titleLine('6) Estado de aplicacion');

if (! $apply) {
    echo "VISTA PREVIA: no se hizo ningun cambio." . PHP_EOL;
    echo "Para aplicar: APPLY=1 ./permisos_modulos_respetando_grupos.sh" . PHP_EOL;
    return;
}

titleLine('7) Aplicando cambios');

DB::transaction(function () use (
    $modulePermissionNames,
    $permissionsByModule,
    $roleTemplates,
    $adminRoleNames,
    $companies,
    $groupAdmins
): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($modulePermissionNames as $permissionName) {
        Permission::query()->firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($companies as $company) {
        foreach ($roleTemplates as $roleName => $rolePermissions) {
            $role = Role::query()->firstOrCreate([
                'company_id' => $company->id,
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->givePermissionTo($rolePermissions);
        }

        foreach ($adminRoleNames as $roleName) {
            $role = Role::query()->firstOrCreate([
                'company_id' => $company->id,
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $allPermissionNames = DB::table('permissions')
                ->orderBy('name')
                ->pluck('name')
                ->all();

            $role->givePermissionTo($allPermissionNames);
        }
    }

    foreach ($groupAdmins as $groupAdmin) {
        $targetCompanies = $companies
            ->where('company_group_id', $groupAdmin->company_group_id)
            ->values();

        foreach ($targetCompanies as $company) {
            DB::table('company_user')->updateOrInsert([
                'company_id' => $company->id,
                'user_id' => $groupAdmin->user_id,
            ]);

            $role = Role::query()->firstOrCreate([
                'company_id' => $company->id,
                'name' => 'Admin Grupo',
                'guard_name' => 'web',
            ]);

            DB::table('model_has_roles')->updateOrInsert([
                'role_id' => $role->id,
                'model_type' => 'App\\Models\\User',
                'model_id' => $groupAdmin->user_id,
                'company_id' => $company->id,
            ]);
        }
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

echo "Cambios aplicados correctamente." . PHP_EOL;
