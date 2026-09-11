<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class V5834ServiceDeliveryPermissionSeeder extends Seeder
{
    /*
     * BEXIA_ATC_DELIVERY_PERMISSION_RECONCILIATION_V5_83_4C5F13
     *
     * Política física de entrega de reparaciones:
     *
     * SI:
     * - Servicio - Encargado de Técnicos
     * - Servicio - Supervisor
     *
     * NO:
     * - Servicio - Recepción
     * - Servicio - Técnico
     * - Servicio - Cajero Reparaciones
     *
     * Este seeder corrige de forma idempotente el permiso
     * service.repairs.delivery creado históricamente por
     * V582P7H22B2.
     *
     * No sustituye el gate de backend de ServiceAccess.
     */
    private array $companyIds = [
        1,
        11,
        14,
        15,
        17,
    ];

    private array $allowedRoles = [
        'Servicio - Encargado de Técnicos',
        'Servicio - Supervisor',
    ];

    private array $deniedRoles = [
        'Servicio - Recepción',
        'Servicio - Técnico',
        'Servicio - Cajero Reparaciones',
    ];

    public function run(): void
    {
        if (
            ! Schema::hasTable('permissions')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('role_has_permissions')
        ) {
            throw new RuntimeException(
                'No existen tablas de permisos requeridas.'
            );
        }

        $permissionId =
            DB::table('permissions')
                ->where(
                    'name',
                    'service.repairs.delivery'
                )
                ->where(
                    'guard_name',
                    'web'
                )
                ->value('id');

        if (! $permissionId) {
            throw new RuntimeException(
                'No existe service.repairs.delivery.'
            );
        }

        DB::transaction(
            function () use ($permissionId): void {
                $deniedRoleIds =
                    DB::table('roles')
                        ->whereIn(
                            'company_id',
                            $this->companyIds
                        )
                        ->whereIn(
                            'name',
                            $this->deniedRoles
                        )
                        ->pluck('id');

                DB::table(
                    'role_has_permissions'
                )
                    ->where(
                        'permission_id',
                        $permissionId
                    )
                    ->whereIn(
                        'role_id',
                        $deniedRoleIds
                    )
                    ->delete();

                $allowedRoles =
                    DB::table('roles')
                        ->whereIn(
                            'company_id',
                            $this->companyIds
                        )
                        ->whereIn(
                            'name',
                            $this->allowedRoles
                        )
                        ->get([
                            'id',
                            'company_id',
                            'name',
                        ]);

                foreach (
                    $this->companyIds
                    as $companyId
                ) {
                    foreach (
                        $this->allowedRoles
                        as $roleName
                    ) {
                        $exists =
                            $allowedRoles
                                ->contains(
                                    fn ($role): bool =>
                                        (int) $role->company_id
                                            === $companyId
                                        && $role->name
                                            === $roleName
                                );

                        if (! $exists) {
                            throw new RuntimeException(
                                'Falta rol requerido '
                                . $roleName
                                . ' en company '
                                . $companyId
                                . '.'
                            );
                        }
                    }
                }

                foreach ($allowedRoles as $role) {
                    DB::table(
                        'role_has_permissions'
                    )->updateOrInsert(
                        [
                            'permission_id' =>
                                $permissionId,

                            'role_id' =>
                                $role->id,
                        ],
                        []
                    );
                }
            }
        );

        if (
            class_exists(
                PermissionRegistrar::class
            )
        ) {
            app(
                PermissionRegistrar::class
            )->forgetCachedPermissions();
        }
    }
}
