<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const COMPANY_ID = 3;

    private const USER_ROLE = 'Globos - Usuario';
    private const ADMIN_ROLE = 'Globos - Administrador';

    private const PERMISSIONS = [
        'custom_balloons.view',
        'custom_balloons.create',
        'custom_balloons.update',
        'custom_balloons.change_status',
        'custom_balloons.cancel',

        'custom_balloons.production',
        'custom_balloons.calendar',

        'custom_balloons.catalog.view',
        'custom_balloons.catalog.manage',

        'custom_balloons.prices.manage',
        'custom_balloons.extras.manage',

        'custom_balloons.reports',
    ];

    private const USER_PERMISSIONS = [
        'custom_balloons.view',
        'custom_balloons.create',
        'custom_balloons.update',
        'custom_balloons.change_status',

        'custom_balloons.production',
        'custom_balloons.calendar',

        'custom_balloons.catalog.view',
    ];

    public function up(): void
    {
        $now = now();

        /*
         * ================================================================
         * 1. PERMISOS GLOBALES SPATIE
         * ================================================================
         */
        foreach (self::PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(
                [
                    'name' => $permission,
                    'guard_name' => 'web',
                ],
                [
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        /*
         * ================================================================
         * 2. ROLES SOLO PAPELON
         * ================================================================
         */
        foreach ([self::USER_ROLE, self::ADMIN_ROLE] as $roleName) {
            $exists = DB::table('roles')
                ->where('company_id', self::COMPANY_ID)
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->exists();

            if (! $exists) {
                DB::table('roles')->insert([
                    'company_id' => self::COMPANY_ID,
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $userRoleId = DB::table('roles')
            ->where('company_id', self::COMPANY_ID)
            ->where('name', self::USER_ROLE)
            ->where('guard_name', 'web')
            ->value('id');

        $adminRoleId = DB::table('roles')
            ->where('company_id', self::COMPANY_ID)
            ->where('name', self::ADMIN_ROLE)
            ->where('guard_name', 'web')
            ->value('id');

        /*
         * ================================================================
         * 3. PERMISOS DEL ROL USUARIO
         * ================================================================
         */
        $userPermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', self::USER_PERMISSIONS)
            ->pluck('id');

        foreach ($userPermissionIds as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $userRoleId,
            ]);
        }

        /*
         * ================================================================
         * 4. ADMINISTRADOR = TODOS LOS PERMISOS DEL MODULO
         * ================================================================
         */
        $adminPermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id');

        foreach ($adminPermissionIds as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $adminRoleId,
            ]);
        }

        /*
         * ================================================================
         * 5. TAMANOS INICIALES
         * ================================================================
         *
         * Precio NO vive aqui.
         */
        $sizes = [
            [
                'code' => 'CHICO',
                'name' => 'Chico',
                'sort_order' => 10,
                'price' => 160.00,
            ],
            [
                'code' => 'MEDIANO',
                'name' => 'Mediano',
                'sort_order' => 20,
                'price' => 185.00,
            ],
            [
                'code' => 'GRANDE',
                'name' => 'Grande',
                'sort_order' => 30,
                'price' => 220.00,
            ],
        ];

        foreach ($sizes as $sizeData) {
            DB::table('custom_balloon_sizes')->updateOrInsert(
                [
                    'company_id' => self::COMPANY_ID,
                    'code' => $sizeData['code'],
                ],
                [
                    'name' => $sizeData['name'],
                    'is_active' => true,
                    'sort_order' => $sizeData['sort_order'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $sizeId = DB::table('custom_balloon_sizes')
                ->where('company_id', self::COMPANY_ID)
                ->where('code', $sizeData['code'])
                ->value('id');

            /*
             * Como la tabla acaba de crearse, normalmente no habrá precio.
             *
             * Si hubiera uno por alguna prueba previa:
             * - si coincide, no hacemos nada;
             * - si es distinto, cerramos vigencia e insertamos el nuevo.
             */
            $current = DB::table('custom_balloon_prices')
                ->where('company_id', self::COMPANY_ID)
                ->where('size_id', $sizeId)
                ->whereNull('valid_to')
                ->first();

            if (! $current) {
                DB::table('custom_balloon_prices')->insert([
                    'company_id' => self::COMPANY_ID,
                    'size_id' => $sizeId,
                    'price' => $sizeData['price'],
                    'valid_from' => $now,
                    'valid_to' => null,
                    'created_by_user_id' => null,
                    'notes' => 'Precio inicial GLB3A',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            if ((float) $current->price !== (float) $sizeData['price']) {
                DB::table('custom_balloon_prices')
                    ->where('id', $current->id)
                    ->update([
                        'valid_to' => $now,
                        'updated_at' => $now,
                    ]);

                DB::table('custom_balloon_prices')->insert([
                    'company_id' => self::COMPANY_ID,
                    'size_id' => $sizeId,
                    'price' => $sizeData['price'],
                    'valid_from' => $now,
                    'valid_to' => null,
                    'created_by_user_id' => null,
                    'notes' => 'Precio inicial actualizado por GLB3A',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        /*
         * No creamos extras ficticios.
         * custom_balloon_extras queda vacío y listo para configuración.
         */
    }

    public function down(): void
    {
        /*
         * Catálogo inicial.
         * Sólo borramos tamaños si todavía no han sido utilizados.
         */
        $sizeIds = DB::table('custom_balloon_sizes')
            ->where('company_id', self::COMPANY_ID)
            ->whereIn('code', ['CHICO', 'MEDIANO', 'GRANDE'])
            ->pluck('id');

        $usedSizeIds = DB::table('custom_balloon_order_lines')
            ->whereIn('size_id', $sizeIds)
            ->pluck('size_id')
            ->unique();

        foreach ($sizeIds as $sizeId) {
            if ($usedSizeIds->contains($sizeId)) {
                continue;
            }

            DB::table('custom_balloon_prices')
                ->where('company_id', self::COMPANY_ID)
                ->where('size_id', $sizeId)
                ->delete();

            DB::table('custom_balloon_model_sizes')
                ->where('company_id', self::COMPANY_ID)
                ->where('size_id', $sizeId)
                ->delete();

            DB::table('custom_balloon_sizes')
                ->where('id', $sizeId)
                ->delete();
        }

        /*
         * Roles propios del módulo.
         */
        $roleIds = DB::table('roles')
            ->where('company_id', self::COMPANY_ID)
            ->whereIn('name', [
                self::USER_ROLE,
                self::ADMIN_ROLE,
            ])
            ->pluck('id');

        DB::table('role_has_permissions')
            ->whereIn('role_id', $roleIds)
            ->delete();

        DB::table('model_has_roles')
            ->whereIn('role_id', $roleIds)
            ->delete();

        DB::table('roles')
            ->whereIn('id', $roleIds)
            ->delete();

        /*
         * Los permisos son exclusivos de este módulo.
         */
        $permissionIds = DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('model_has_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        DB::table('role_has_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }
};
