<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const EXPECTED_PERMISSION_COUNT = 91;

    private const EXPECTED_ROLE_PERMISSION_LINKS = 2217;

    private function canonicalMatrix(): array
    {
        return json_decode(
            <<<'JSON'
{"catalogs.fiscal.create":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"catalogs.fiscal.delete":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"catalogs.fiscal.update":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"catalogs.fiscal.view":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"inventory.adjustments.audit.view":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.adjustments.delete":[3,8,15,16,17,18,23,30,36,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167],"inventory.adjustments.view":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.as_of_date.view":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.costing_diagnostic.view":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.kardex.view":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.locations.create":[3,8,15,16,17,18,23,30,36,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,171],"inventory.locations.delete":[3,8,15,16,17,18,23,30,36,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167],"inventory.locations.update":[3,8,15,16,17,18,23,30,36,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,171],"inventory.locations.view":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.location_types.create":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"inventory.location_types.delete":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"inventory.location_types.update":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"inventory.location_types.view":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"inventory.lots.manage":[],"inventory.lots.view":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.movements.create":[3,8,15,16,17,18,23,30,36,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,171],"inventory.movements.delete":[3,8,15,16,17,18,23,30,36,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167],"inventory.movements.update":[3,8,15,16,17,18,23,30,36,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,171],"inventory.movements.view":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.operation_types.create":[3,8,15,16,17,18,23,30,36,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,171],"inventory.operation_types.delete":[3,8,15,16,17,18,23,30,36,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167],"inventory.operation_types.update":[3,8,15,16,17,18,23,30,36,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,171],"inventory.operation_types.view":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"inventory.product_attributes.create":[3,8,15,16,17,18,23,30,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,171],"inventory.product_attributes.delete":[3,8,15,16,17,18,23,30,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167],"inventory.product_attributes.update":[3,8,15,16,17,18,23,30,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,171],"inventory.product_attributes.view":[3,8,15,16,17,18,23,30,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.product_categories.create":[3,8,15,16,17,18,23,30,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,171],"inventory.product_categories.update":[3,8,15,16,17,18,23,30,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,171],"inventory.product_categories.view":[3,8,15,16,17,18,23,30,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.products.create":[3,8,15,16,17,18,23,30,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,171],"inventory.products.delete":[3,8,15,16,17,18,23,30,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167],"inventory.products.update":[3,8,15,16,17,18,23,30,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,171],"inventory.products.view":[3,8,15,16,17,18,23,30,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.serials.audit.view":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.serials.manage":[],"inventory.serials.view":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.stock.cost.view":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.stock.view":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.traceability.view":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.valuation.view":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"inventory.warehouses.create":[3,8,15,16,17,18,23,30,36,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,171],"inventory.warehouses.delete":[3,8,15,16,17,18,23,30,36,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167],"inventory.warehouses.update":[3,8,15,16,17,18,23,30,36,117,119,120,123,138,139,140,141,142,143,156,157,158,159,160,161,162,163,164,165,166,167,168,171],"inventory.warehouses.view":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171],"nomina.compras_via_nomina.crear":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"nomina.compras_via_nomina.editar":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"nomina.compras_via_nomina.eliminar":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"nomina.compras_via_nomina.ver":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"nomina.procesos.ver":[],"nomina.recibos_cfdi.ver":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.credenciales.descargar":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.credenciales.ver":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.departamentos.crear":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.departamentos.editar":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.departamentos.eliminar":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.departamentos.ver":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.empleados.crear":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.empleados.editar":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.empleados.eliminar":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.empleados.ver":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.geocercas.crear":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.geocercas.editar":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.geocercas.eliminar":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.geocercas.ver":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.horarios.crear":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.horarios.editar":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.horarios.eliminar":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.horarios.ver":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.organigrama.ver":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.puestos.crear":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.puestos.editar":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.puestos.eliminar":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.puestos.ver":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.terminales.crear":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.terminales.editar":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.terminales.ver":[3,8,15,16,17,18,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.tipos_documento.crear":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.tipos_documento.editar":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.tipos_documento.eliminar":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.tipos_documento.ver":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.tipos_incidencia.crear":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.tipos_incidencia.editar":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.tipos_incidencia.eliminar":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"rrhh.tipos_incidencia.ver":[3,8,15,16,17,18,36,117,119,120,156,157,158,159,160,161,162,163,164,165,166,167],"sales.deliver":[3,8,15,16,17,18,23,25,30,32,117,119,120,123,125,138,139,140,141,142,143,150,151,152,153,154,155,156,157,158,159,160,161,162,163,164,165,166,167,168,169,171,172]}
JSON,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    private function expectedRoles(): array
    {
        return json_decode(
            <<<'JSON'
{"3":{"company_id":1,"name":"admin","guard_name":"web"},"8":{"company_id":3,"name":"admin","guard_name":"web"},"15":{"company_id":1,"name":"Admin Empresa","guard_name":"web"},"16":{"company_id":3,"name":"Admin Empresa","guard_name":"web"},"17":{"company_id":1,"name":"Admin Grupo","guard_name":"web"},"18":{"company_id":3,"name":"Admin Grupo","guard_name":"web"},"23":{"company_id":1,"name":"Inventarios","guard_name":"web"},"25":{"company_id":1,"name":"Ventas","guard_name":"web"},"30":{"company_id":3,"name":"Inventarios","guard_name":"web"},"32":{"company_id":3,"name":"Ventas","guard_name":"web"},"36":{"company_id":1,"name":"Administrador","guard_name":"web"},"117":{"company_id":10,"name":"admin","guard_name":"web"},"119":{"company_id":10,"name":"Admin Empresa","guard_name":"web"},"120":{"company_id":10,"name":"Admin Grupo","guard_name":"web"},"123":{"company_id":10,"name":"Inventarios","guard_name":"web"},"125":{"company_id":10,"name":"Ventas","guard_name":"web"},"138":{"company_id":15,"name":"Inventarios","guard_name":"web"},"139":{"company_id":16,"name":"Inventarios","guard_name":"web"},"140":{"company_id":14,"name":"Inventarios","guard_name":"web"},"141":{"company_id":11,"name":"Inventarios","guard_name":"web"},"142":{"company_id":17,"name":"Inventarios","guard_name":"web"},"143":{"company_id":12,"name":"Inventarios","guard_name":"web"},"150":{"company_id":15,"name":"Ventas","guard_name":"web"},"151":{"company_id":16,"name":"Ventas","guard_name":"web"},"152":{"company_id":14,"name":"Ventas","guard_name":"web"},"153":{"company_id":11,"name":"Ventas","guard_name":"web"},"154":{"company_id":17,"name":"Ventas","guard_name":"web"},"155":{"company_id":12,"name":"Ventas","guard_name":"web"},"156":{"company_id":15,"name":"Admin Grupo","guard_name":"web"},"157":{"company_id":16,"name":"Admin Grupo","guard_name":"web"},"158":{"company_id":14,"name":"Admin Grupo","guard_name":"web"},"159":{"company_id":11,"name":"Admin Grupo","guard_name":"web"},"160":{"company_id":17,"name":"Admin Grupo","guard_name":"web"},"161":{"company_id":12,"name":"Admin Grupo","guard_name":"web"},"162":{"company_id":15,"name":"Admin Empresa","guard_name":"web"},"163":{"company_id":16,"name":"Admin Empresa","guard_name":"web"},"164":{"company_id":14,"name":"Admin Empresa","guard_name":"web"},"165":{"company_id":11,"name":"Admin Empresa","guard_name":"web"},"166":{"company_id":17,"name":"Admin Empresa","guard_name":"web"},"167":{"company_id":12,"name":"Admin Empresa","guard_name":"web"},"168":{"company_id":3,"name":"Comercio - Administrador","guard_name":"web"},"169":{"company_id":3,"name":"Comercio - Supervisor","guard_name":"web"},"171":{"company_id":3,"name":"Comercio - Almacén/Compras","guard_name":"web"},"172":{"company_id":3,"name":"Comercio - Ventas mostrador","guard_name":"web"}}
JSON,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $matrix = $this->canonicalMatrix();
            $expectedRoles = $this->expectedRoles();

            if (count($matrix) !== self::EXPECTED_PERMISSION_COUNT) {
                throw new \RuntimeException(
                    'SEC7F9C2: canonical permission count mismatch.'
                );
            }

            foreach ($expectedRoles as $roleId => $expected) {
                $role = DB::table('roles')
                    ->where('id', (int) $roleId)
                    ->first([
                        'id',
                        'company_id',
                        'name',
                        'guard_name',
                    ]);

                if (! $role) {
                    throw new \RuntimeException(
                        "SEC7F9C2: missing role {$roleId}."
                    );
                }

                $actualCompanyId = $role->company_id === null
                    ? null
                    : (int) $role->company_id;

                $expectedCompanyId = $expected['company_id'] === null
                    ? null
                    : (int) $expected['company_id'];

                if (
                    $actualCompanyId !== $expectedCompanyId
                    || (string) $role->name !== (string) $expected['name']
                    || (string) $role->guard_name !== (string) $expected['guard_name']
                ) {
                    throw new \RuntimeException(
                        "SEC7F9C2: role identity mismatch for {$roleId}."
                    );
                }
            }

            $permissionIds = DB::table('permissions')
                ->where('guard_name', 'web')
                ->whereIn(
                    'name',
                    array_keys($matrix),
                )
                ->pluck('id', 'name');

            if ($permissionIds->count() !== self::EXPECTED_PERMISSION_COUNT) {
                throw new \RuntimeException(
                    'SEC7F9C2: canonical permissions missing.'
                );
            }

            foreach ($matrix as $permissionName => $allowedRoleIds) {
                $permissionId = (int) $permissionIds[$permissionName];

                $allowedRoleIds = array_values(
                    array_map(
                        'intval',
                        $allowedRoleIds,
                    )
                );

                sort($allowedRoleIds);

                $deleteQuery = DB::table('role_has_permissions')
                    ->where(
                        'permission_id',
                        $permissionId,
                    );

                if ($allowedRoleIds === []) {
                    $deleteQuery->delete();
                } else {
                    $deleteQuery
                        ->whereNotIn(
                            'role_id',
                            $allowedRoleIds,
                        )
                        ->delete();
                }

                if ($allowedRoleIds !== []) {
                    $rows = array_map(
                        static fn (int $roleId): array => [
                            'permission_id' => $permissionId,
                            'role_id' => $roleId,
                        ],
                        $allowedRoleIds,
                    );

                    foreach (
                        array_chunk($rows, 500)
                        as $chunk
                    ) {
                        DB::table('role_has_permissions')
                            ->insertOrIgnore($chunk);
                    }
                }

                $actualRoleIds = DB::table(
                    'role_has_permissions'
                )
                    ->where(
                        'permission_id',
                        $permissionId,
                    )
                    ->orderBy('role_id')
                    ->pluck('role_id')
                    ->map(
                        static fn ($id): int => (int) $id
                    )
                    ->all();

                if ($actualRoleIds !== $allowedRoleIds) {
                    throw new \RuntimeException(
                        "SEC7F9C2: matrix mismatch for {$permissionName}."
                    );
                }
            }

            $targetPermissionIds = $permissionIds
                ->values()
                ->map(
                    static fn ($id): int => (int) $id
                )
                ->all();

            $actualLinks = DB::table(
                'role_has_permissions'
            )
                ->whereIn(
                    'permission_id',
                    $targetPermissionIds,
                )
                ->count();

            if (
                $actualLinks
                !== self::EXPECTED_ROLE_PERMISSION_LINKS
            ) {
                throw new \RuntimeException(
                    'SEC7F9C2: canonical link count mismatch.'
                );
            }
        });
    }

    public function down(): void
    {
        /*
         * Intentionally no-op.
         *
         * This migration reconciles the audited final SEC7
         * permission matrix. A rollback must restore the
         * release database backup together with the code.
         */
    }
};
