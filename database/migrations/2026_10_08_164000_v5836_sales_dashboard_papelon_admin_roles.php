<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $companyId = 3;
        $permissionName = 'sales.dashboard.view';

        $permissionId = DB::table('permissions')
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            throw new RuntimeException(
                "No existe el permiso {$permissionName}"
            );
        }

        /*
         * Bexia usa Spatie Permission Teams.
         *
         * Los roles pueden existir varias veces con el mismo nombre,
         * cada uno ligado a una empresa/team distinto.
         *
         * Solo otorgamos el permiso a roles administrativos
         * pertenecientes a Papelería Papelón (company/team 3).
         */
        $teamKey = config(
            'permission.column_names.team_foreign_key',
            'team_id'
        );

        $roleColumns = Schema::getColumnListing('roles');

        if (! in_array($teamKey, $roleColumns, true)) {
            throw new RuntimeException(
                "La tabla roles no contiene la columna Teams esperada: {$teamKey}"
            );
        }

        $roles = DB::table('roles')
            ->where($teamKey, $companyId)
            ->where('guard_name', 'web')
            ->where(function ($query) {
                $query
                    ->whereRaw('LOWER(name) = ?', ['admin'])
                    ->orWhereRaw('LOWER(name) = ?', ['admin empresa'])
                    ->orWhereRaw('LOWER(name) = ?', ['admin_empresa']);
            })
            ->get(['id', 'name']);

        if ($roles->isEmpty()) {
            throw new RuntimeException(
                'No se encontraron roles administrativos para company_id=3'
            );
        }

        foreach ($roles as $role) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $role->id,
            ]);
        }
    }

    public function down(): void
    {
        $companyId = 3;

        $permissionId = DB::table('permissions')
            ->where('name', 'sales.dashboard.view')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $teamKey = config(
            'permission.column_names.team_foreign_key',
            'team_id'
        );

        $roleColumns = Schema::getColumnListing('roles');

        if (! in_array($teamKey, $roleColumns, true)) {
            return;
        }

        $roleIds = DB::table('roles')
            ->where($teamKey, $companyId)
            ->where('guard_name', 'web')
            ->where(function ($query) {
                $query
                    ->whereRaw('LOWER(name) = ?', ['admin'])
                    ->orWhereRaw('LOWER(name) = ?', ['admin empresa'])
                    ->orWhereRaw('LOWER(name) = ?', ['admin_empresa']);
            })
            ->pluck('id');

        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $roleIds)
            ->delete();
    }
};
