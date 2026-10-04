<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'computer_rental.view',
        'computer_rental.operate',
        'computer_rental.manage',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();

        foreach (self::PERMISSIONS as $name) {
            $exists = DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        /*
         * Roles operativos.
         *
         * La página además exige company_id=3, por lo que estos permisos
         * no hacen visible el módulo en otras empresas.
         */
        $operateRoles = [
            'Comercio - Cajero PDV',
            'Comercio - Supervisor',
            'Comercio - Administrador',
            'Admin Empresa',
            'Admin Grupo',
        ];

        $manageRoles = [
            'Comercio - Administrador',
            'Admin Empresa',
            'Admin Grupo',
        ];

        $viewId = DB::table('permissions')
            ->where('name', 'computer_rental.view')
            ->where('guard_name', 'web')
            ->value('id');

        $operateId = DB::table('permissions')
            ->where('name', 'computer_rental.operate')
            ->where('guard_name', 'web')
            ->value('id');

        $manageId = DB::table('permissions')
            ->where('name', 'computer_rental.manage')
            ->where('guard_name', 'web')
            ->value('id');

        if (
            Schema::hasTable('roles')
            && Schema::hasTable('role_has_permissions')
        ) {
            /*
             * CIBER3I1A_PAPELON_ROLE_SCOPE
             *
             * Solo roles realmente asignados dentro de Papelería Papelón.
             * Evitamos otorgar permisos a roles homónimos de otras empresas.
             */
            $papelonRoleIds = DB::table('model_has_roles')
                ->where('company_id', 3)
                ->pluck('role_id')
                ->unique()
                ->values();

            $roles = DB::table('roles')
                ->whereIn('id', $papelonRoleIds)
                ->whereIn(
                    'name',
                    array_values(
                        array_unique(
                            array_merge(
                                $operateRoles,
                                $manageRoles
                            )
                        )
                    )
                )
                ->get();

            foreach ($roles as $role) {
                $permissions = [];

                if (in_array($role->name, $operateRoles, true)) {
                    $permissions[] = $viewId;
                    $permissions[] = $operateId;
                }

                if (in_array($role->name, $manageRoles, true)) {
                    $permissions[] = $manageId;
                }

                foreach (
                    array_unique(
                        array_filter($permissions)
                    )
                    as $permissionId
                ) {
                    $exists = DB::table('role_has_permissions')
                        ->where(
                            'permission_id',
                            $permissionId
                        )
                        ->where(
                            'role_id',
                            $role->id
                        )
                        ->exists();

                    if (! $exists) {
                        DB::table('role_has_permissions')
                            ->insert([
                                'permission_id' =>
                                    $permissionId,
                                'role_id' =>
                                    $role->id,
                            ]);
                    }
                }
            }
        }

        /*
         * CIBER3J1_REPRODUCIBLE_MENU
         *
         * El item fue creado manualmente durante DEV.
         * Para PROD no podemos asumir que ya exista.
         *
         * Esta migración:
         * - actualiza si ya existe;
         * - crea el item si aún no existe;
         * - usa el grupo estándar Punto de Venta.
         */
        if (
            Schema::hasTable('bexia_menu_items')
            && Schema::hasTable('bexia_menu_groups')
        ) {
            $groupId = DB::table('bexia_menu_groups')
                ->where('key', 'punto_de_venta')
                ->value('id');

            if (! $groupId) {
                throw new \RuntimeException(
                    'No existe el grupo de menú Punto de Venta.'
                );
            }

            $menuData = [
                'group_id' => $groupId,
                'label' => 'Renta de equipos',
                'sort' => 80,
                'is_visible' => true,
                'is_system' => true,
                'source' => 'filament_file',
                'file_path' =>
                    'app/Filament/Pages/ComputerRentalControl.php',
                'class_name' =>
                    'ComputerRentalControl',
                'route_name' =>
                    'filament.admin.pages.computer-rental-control',
                'permission_name' =>
                    'computer_rental.view',
            ];

            if (
                Schema::hasColumn(
                    'bexia_menu_items',
                    'default_label'
                )
            ) {
                $menuData['default_label'] =
                    'Renta de equipos';
            }

            if (
                Schema::hasColumn(
                    'bexia_menu_items',
                    'updated_at'
                )
            ) {
                $menuData['updated_at'] = now();
            }

            $exists = DB::table('bexia_menu_items')
                ->where(
                    'key',
                    'pages.computerrentalcontrol'
                )
                ->exists();

            if ($exists) {
                DB::table('bexia_menu_items')
                    ->where(
                        'key',
                        'pages.computerrentalcontrol'
                    )
                    ->update($menuData);
            } else {
                $menuData['key'] =
                    'pages.computerrentalcontrol';

                if (
                    Schema::hasColumn(
                        'bexia_menu_items',
                        'created_at'
                    )
                ) {
                    $menuData['created_at'] = now();
                }

                DB::table('bexia_menu_items')
                    ->insert($menuData);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bexia_menu_items')) {
            DB::table('bexia_menu_items')
                ->where(
                    'key',
                    'pages.computerrentalcontrol'
                )
                ->update([
                    'permission_name' =>
                        'pos.menu.view',
                    'updated_at' =>
                        now(),
                ]);
        }

        if (
            Schema::hasTable('permissions')
            && Schema::hasTable('role_has_permissions')
        ) {
            $ids = DB::table('permissions')
                ->whereIn(
                    'name',
                    self::PERMISSIONS
                )
                ->where('guard_name', 'web')
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                DB::table('role_has_permissions')
                    ->whereIn(
                        'permission_id',
                        $ids
                    )
                    ->delete();
            }
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->whereIn(
                    'name',
                    self::PERMISSIONS
                )
                ->where('guard_name', 'web')
                ->delete();
        }
    }
};
