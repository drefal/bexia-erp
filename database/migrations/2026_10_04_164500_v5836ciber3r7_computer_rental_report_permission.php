<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * CIBER3R7
         *
         * Separamos ver/operar Renta de consultar sus reportes.
         *
         * Cajero:
         *   view + operate
         *
         * Supervisor:
         *   view + operate + report
         *
         * Administradores:
         *   view + operate + report + manage
         */

        $permissionId = DB::table('permissions')
            ->where('name', 'computer_rental.report')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')
                ->insertGetId([
                    'name' => 'computer_rental.report',
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        /*
         * 1. Todo rol de Papelón que ya administra Renta
         *    recibe también acceso a reportes.
         */
        $managePermissionId = DB::table('permissions')
            ->where('name', 'computer_rental.manage')
            ->where('guard_name', 'web')
            ->value('id');

        $roleIds = collect();

        if ($managePermissionId) {
            $manageRoleIds = DB::table('role_has_permissions as rhp')
                ->join(
                    'roles as r',
                    'r.id',
                    '=',
                    'rhp.role_id'
                )
                ->where(
                    'rhp.permission_id',
                    $managePermissionId
                )
                ->where(
                    'r.company_id',
                    3
                )
                ->pluck('r.id');

            $roleIds = $roleIds->merge(
                $manageRoleIds
            );
        }

        /*
         * 2. Supervisor de Comercio de Papelón recibe report,
         *    aunque deliberadamente no tenga manage.
         */
        $supervisorId = DB::table('roles')
            ->where('company_id', 3)
            ->where(
                'name',
                'Comercio - Supervisor'
            )
            ->value('id');

        if ($supervisorId) {
            $roleIds->push(
                (int) $supervisorId
            );
        }

        foreach (
            $roleIds
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
            as $roleId
        ) {
            DB::table('role_has_permissions')
                ->updateOrInsert(
                    [
                        'permission_id' =>
                            (int) $permissionId,
                        'role_id' =>
                            $roleId,
                    ],
                    []
                );
        }

        /*
         * No se concede explícitamente a Comercio - Cajero PDV.
         */

        app(
            PermissionRegistrar::class
        )->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'computer_rental.report')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('role_has_permissions')
            ->where(
                'permission_id',
                $permissionId
            )
            ->delete();

        DB::table('model_has_permissions')
            ->where(
                'permission_id',
                $permissionId
            )
            ->delete();

        DB::table('permissions')
            ->where('id', $permissionId)
            ->delete();

        app(
            PermissionRegistrar::class
        )->forgetCachedPermissions();
    }
};
