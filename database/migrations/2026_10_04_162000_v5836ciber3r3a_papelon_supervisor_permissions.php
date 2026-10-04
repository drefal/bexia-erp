<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * V5.83.6-CIBER3R3A
     *
     * Renta de equipos sigue limitada en runtime a
     * Papelería Papelón (company_id = 3).
     *
     * El supervisor de Papelón debe poder:
     * - ver el módulo y reporte;
     * - operar sesiones.
     *
     * Deliberadamente NO recibe computer_rental.manage.
     */
    public function up(): void
    {
        if (
            ! Schema::hasTable('roles')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('role_has_permissions')
        ) {
            return;
        }

        $permissionNames = [
            'computer_rental.view',
            'computer_rental.operate',
        ];

        $permissions = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->get([
                'id',
                'name',
                'guard_name',
            ]);

        if ($permissions->isEmpty()) {
            return;
        }

        /*
         * Spatie Teams puede representar Papelón mediante
         * company_id o team_id dependiendo del esquema.
         *
         * Buscamos únicamente roles supervisor cuyo team/company
         * sea 3. Si roles no tiene esa columna, se conserva el
         * scope runtime estricto del módulo y solo se usa el rol
         * supervisor existente.
         */
        $roles = DB::table('roles')
            ->whereRaw('LOWER(name) = ?', ['supervisor']);

        if (Schema::hasColumn('roles', 'company_id')) {
            $roles->where('company_id', 3);
        } elseif (Schema::hasColumn('roles', 'team_id')) {
            $roles->where('team_id', 3);
        }

        $roles = $roles->get([
            'id',
            'name',
            'guard_name',
        ]);

        foreach ($roles as $role) {
            foreach ($permissions as $permission) {
                /*
                 * No mezclamos guards.
                 */
                if (
                    isset($role->guard_name)
                    && isset($permission->guard_name)
                    && (string) $role->guard_name
                        !== (string) $permission->guard_name
                ) {
                    continue;
                }

                DB::table('role_has_permissions')
                    ->updateOrInsert(
                        [
                            'permission_id' =>
                                (int) $permission->id,
                            'role_id' =>
                                (int) $role->id,
                        ],
                        []
                    );
            }
        }

        app(
            \Spatie\Permission\PermissionRegistrar::class
        )->forgetCachedPermissions();
    }

    public function down(): void
    {
        /*
         * No retiramos permisos en down porque el rol supervisor
         * pudo haberlos tenido antes de esta migración.
         *
         * El rollback operativo usa backup de código y no debe
         * eliminar autorizaciones preexistentes.
         */
    }
};
