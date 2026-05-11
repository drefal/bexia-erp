<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function lineTitle(string $title): void
{
    echo PHP_EOL;
    echo "======================================" . PHP_EOL;
    echo $title . PHP_EOL;
    echo "======================================" . PHP_EOL;
}

function tableColumns(string $table): void
{
    if (! Schema::hasTable($table)) {
        echo $table . ": NO EXISTE" . PHP_EOL;
        return;
    }

    echo $table . ": " . implode(', ', Schema::getColumnListing($table)) . PHP_EOL;
}

function existingColumns(string $table, array $columns): array
{
    return array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
}

lineTitle('1) Config Spatie');
dump([
    'permission_teams' => config('permission.teams'),
    'team_foreign_key' => config('permission.column_names.team_foreign_key'),
    'role_model' => config('permission.models.role'),
    'permission_model' => config('permission.models.permission'),
]);

lineTitle('2) Columnas de tablas clave');

$tables = [
    'organizations',
    'company_groups',
    'companies',
    'branches',
    'company_group_user',
    'company_user',
    'users',
    'roles',
    'permissions',
    'model_has_roles',
    'model_has_permissions',
    'role_has_permissions',
];

foreach ($tables as $table) {
    tableColumns($table);
}

lineTitle('3) Cliente Bexia / Organizations');

if (Schema::hasTable('organizations')) {
    $cols = existingColumns('organizations', ['id', 'name', 'created_at']);
    DB::table('organizations')
        ->select($cols)
        ->orderBy('id')
        ->get()
        ->each(fn ($row) => dump((array) $row));
}

lineTitle('4) Grupos de empresas');

if (Schema::hasTable('company_groups')) {
    $cols = existingColumns('company_groups', ['id', 'organization_id', 'name', 'code', 'is_active', 'created_at']);
    DB::table('company_groups')
        ->select($cols)
        ->orderBy('id')
        ->get()
        ->each(fn ($row) => dump((array) $row));
}

lineTitle('5) Empresas por grupo');

if (Schema::hasTable('companies')) {
    $cols = existingColumns('companies', [
        'id',
        'organization_id',
        'company_group_id',
        'name',
        'business_name',
        'legal_name',
        'rfc',
        'is_active',
        'created_at',
    ]);

    DB::table('companies')
        ->select($cols)
        ->orderBy(Schema::hasColumn('companies', 'company_group_id') ? 'company_group_id' : 'id')
        ->orderBy('id')
        ->get()
        ->each(fn ($row) => dump((array) $row));
}

lineTitle('6) Sucursales por empresa');

if (Schema::hasTable('branches')) {
    $cols = existingColumns('branches', ['id', 'company_id', 'name', 'code', 'is_active', 'created_at']);

    DB::table('branches')
        ->select($cols)
        ->orderBy(Schema::hasColumn('branches', 'company_id') ? 'company_id' : 'id')
        ->orderBy('id')
        ->get()
        ->each(fn ($row) => dump((array) $row));
}

lineTitle('7) Usuarios asignados a grupos');

if (Schema::hasTable('company_group_user')) {
    $cols = existingColumns('company_group_user', ['id', 'company_group_id', 'user_id', 'role', 'created_at']);
    DB::table('company_group_user')
        ->select($cols)
        ->orderBy(Schema::hasColumn('company_group_user', 'company_group_id') ? 'company_group_id' : 'user_id')
        ->get()
        ->each(fn ($row) => dump((array) $row));
}

lineTitle('8) Usuarios asignados a empresas');

if (Schema::hasTable('company_user')) {
    $cols = existingColumns('company_user', ['id', 'company_id', 'user_id', 'role', 'created_at']);
    DB::table('company_user')
        ->select($cols)
        ->orderBy(Schema::hasColumn('company_user', 'company_id') ? 'company_id' : 'user_id')
        ->get()
        ->each(fn ($row) => dump((array) $row));
}

lineTitle('9) Roles por empresa');

if (Schema::hasTable('roles')) {
    DB::table('roles')
        ->select(existingColumns('roles', ['id', 'company_id', 'name', 'guard_name']))
        ->orderBy('company_id')
        ->orderBy('name')
        ->get()
        ->each(fn ($row) => dump((array) $row));
}

lineTitle('10) Permisos existentes');

if (Schema::hasTable('permissions')) {
    DB::table('permissions')
        ->select(existingColumns('permissions', ['id', 'name', 'guard_name']))
        ->orderBy('name')
        ->get()
        ->each(fn ($row) => dump((array) $row));
}

lineTitle('11) Usuarios con roles por empresa');

if (
    Schema::hasTable('model_has_roles') &&
    Schema::hasTable('roles') &&
    Schema::hasTable('users')
) {
    DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->join('users', 'users.id', '=', 'model_has_roles.model_id')
        ->where('model_has_roles.model_type', 'App\\Models\\User')
        ->select([
            'users.id as user_id',
            'users.name as user_name',
            'users.email as user_email',
            'roles.id as role_id',
            'roles.name as role_name',
            'roles.company_id as role_company_id',
            'model_has_roles.company_id as assigned_company_id',
        ])
        ->orderBy('users.id')
        ->orderBy('model_has_roles.company_id')
        ->orderBy('roles.name')
        ->get()
        ->each(fn ($row) => dump((array) $row));
}

lineTitle('12) Resumen empresas por grupo');

if (
    Schema::hasTable('companies') &&
    Schema::hasColumn('companies', 'company_group_id')
) {
    DB::table('companies')
        ->select('company_group_id', DB::raw('count(*) as companies_count'))
        ->groupBy('company_group_id')
        ->orderBy('company_group_id')
        ->get()
        ->each(fn ($row) => dump((array) $row));
}

lineTitle('13) Archivos clave a revisar');
$files = [
    app_path('Models/User.php'),
    app_path('Models/Company.php'),
    app_path('Models/CompanyGroup.php'),
    app_path('Http/Middleware/SetSpatieCompanyFromTenant.php'),
];

foreach ($files as $file) {
    echo file_exists($file) ? $file . PHP_EOL : $file . ' NO EXISTE' . PHP_EOL;
}
