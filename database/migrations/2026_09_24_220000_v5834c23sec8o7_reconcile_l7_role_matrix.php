<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GUARD = 'web';

    private const ANCHOR_COMPANY = 'CEDIS GRUPO L7';

    private const EXPECTED_GROUP_NAME = 'Grupo Línea 7';

    public function up(): void
    {
        $this->assertRequiredTables();

        DB::transaction(function (): void {
            $groupId = $this->resolveL7GroupId();

            $companyIds = DB::table('companies')
                ->where('company_group_id', $groupId)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            if ($companyIds === []) {
                throw new RuntimeException(
                    'SEC8O7: Grupo Línea 7 no contiene empresas.'
                );
            }

            $matrices = [
                'RRHH Administrador' =>
                    $this->rrhhAdministratorPermissionIds(),

                'RRHH Asistente' =>
                    $this->rrhhAssistantPermissionIds(),

                'Servicio - Recepción' =>
                    $this->permissionIdsForNames(
                        $this->serviceReceptionPermissions()
                    ),

                'Servicio - Técnico' =>
                    $this->permissionIdsForNames(
                        $this->serviceTechnicianPermissions()
                    ),

                'Servicio - Supervisor' =>
                    $this->permissionIdsForNames(
                        $this->serviceSupervisorPermissions()
                    ),

                'Servicio - Cajero Reparaciones' =>
                    $this->permissionIdsForNames(
                        $this->serviceCashierPermissions()
                    ),

                'Servicio - Encargado de Técnicos' =>
                    $this->permissionIdsForNames(
                        $this->serviceManagerPermissions()
                    ),
            ];

            $this->assertCount(
                102,
                $matrices['RRHH Administrador'],
                'RRHH Administrador'
            );

            $this->assertCount(
                48,
                $matrices['RRHH Asistente'],
                'RRHH Asistente'
            );

            $this->assertCount(
                12,
                $matrices['Servicio - Recepción'],
                'Servicio - Recepción'
            );

            $this->assertCount(
                7,
                $matrices['Servicio - Técnico'],
                'Servicio - Técnico'
            );

            $this->assertCount(
                24,
                $matrices['Servicio - Supervisor'],
                'Servicio - Supervisor'
            );

            $this->assertCount(
                7,
                $matrices['Servicio - Cajero Reparaciones'],
                'Servicio - Cajero Reparaciones'
            );

            $this->assertCount(
                16,
                $matrices['Servicio - Encargado de Técnicos'],
                'Servicio - Encargado de Técnicos'
            );

            foreach ($companyIds as $companyId) {
                foreach ($matrices as $roleName => $permissionIds) {
                    $roleId = $this->ensureUniqueRole(
                        $companyId,
                        $roleName
                    );

                    $this->syncFullRole(
                        $roleId,
                        $permissionIds
                    );
                }
            }

            $this->syncAdministrativeServicePermissions(
                $companyIds
            );

            $this->assertFinalMatrix(
                $companyIds
            );
        });
    }

    /**
     * Esta migración reconcilia datos que históricamente tuvieron
     * matrices distintas entre empresas.
     *
     * No existe un único estado anterior que pueda reconstruirse
     * correctamente mediante "migrate:rollback".
     *
     * Los despliegues deben generar respaldo y rollback de datos
     * antes de ejecutar esta migración.
     */
    public function down(): void
    {
        // Intencionalmente no destructivo.
    }

    private function assertRequiredTables(): void
    {
        foreach ([
            'companies',
            'company_groups',
            'permissions',
            'roles',
            'role_has_permissions',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException(
                    "SEC8O7: falta tabla {$table}."
                );
            }
        }
    }

    private function resolveL7GroupId(): int
    {
        $anchors = DB::table('companies')
            ->where('name', self::ANCHOR_COMPANY)
            ->get([
                'id',
                'company_group_id',
            ]);

        if ($anchors->count() !== 1) {
            throw new RuntimeException(
                'SEC8O7: CEDIS GRUPO L7 no es único.'
            );
        }

        $groupId = (int) ($anchors->first()->company_group_id ?? 0);

        if ($groupId <= 0) {
            throw new RuntimeException(
                'SEC8O7: CEDIS GRUPO L7 no tiene grupo.'
            );
        }

        $groupName = DB::table('company_groups')
            ->where('id', $groupId)
            ->value('name');

        if ((string) $groupName !== self::EXPECTED_GROUP_NAME) {
            throw new RuntimeException(
                'SEC8O7: el grupo de CEDIS no es '
                . self::EXPECTED_GROUP_NAME
                . '.'
            );
        }

        return $groupId;
    }

    private function ensureUniqueRole(
        int $companyId,
        string $roleName
    ): int {
        $roleIds = DB::table('roles')
            ->where('company_id', $companyId)
            ->where('name', $roleName)
            ->where('guard_name', self::GUARD)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if (count($roleIds) > 1) {
            throw new RuntimeException(
                "SEC8O7: rol duplicado {$roleName} "
                . "en company_id={$companyId}."
            );
        }

        if ($roleIds !== []) {
            return $roleIds[0];
        }

        return (int) DB::table('roles')->insertGetId([
            'name' => $roleName,
            'guard_name' => self::GUARD,
            'company_id' => $companyId,
        ]);
    }

    private function syncFullRole(
        int $roleId,
        array $permissionIds
    ): void {
        DB::table('role_has_permissions')
            ->where('role_id', $roleId)
            ->delete();

        $this->insertRolePermissions(
            $roleId,
            $permissionIds
        );
    }

    private function insertRolePermissions(
        int $roleId,
        array $permissionIds
    ): void {
        if ($permissionIds === []) {
            return;
        }

        $rows = array_map(
            static fn (int $permissionId): array => [
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ],
            $permissionIds
        );

        DB::table('role_has_permissions')
            ->insertOrIgnore($rows);
    }

    private function syncAdministrativeServicePermissions(
        array $companyIds
    ): void {
        $adminNames = [
            'admin',
            'Admin Empresa',
            'Admin Grupo',
            'Administrador',
            'Comercio - Administrador',
        ];

        $adminRoleIds = DB::table('roles')
            ->whereIn('company_id', $companyIds)
            ->where('guard_name', self::GUARD)
            ->whereIn('name', $adminNames)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $servicePermissionIds = DB::table('permissions')
            ->where('guard_name', self::GUARD)
            ->where('name', 'like', 'service.%')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $this->assertCount(
            24,
            $servicePermissionIds,
            'catálogo service.*'
        );

        foreach ($adminRoleIds as $roleId) {
            DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->whereIn(
                    'permission_id',
                    $servicePermissionIds
                )
                ->delete();

            $this->insertRolePermissions(
                $roleId,
                $servicePermissionIds
            );
        }
    }

    private function rrhhAdministratorPermissionIds(): array
    {
        return $this->permissionIdsForNames(
            $this->rrhhAdministratorPermissions()
        );
    }

    private function rrhhAssistantPermissionIds(): array
    {
        return $this->permissionIdsForNames(
            $this->rrhhAssistantPermissions()
        );
    }

    private function rrhhAdministratorPermissions(): array
    {
        return [
            'nomina.catalogos.crear',
            'nomina.catalogos.editar',
            'nomina.catalogos.eliminar',
            'nomina.catalogos.ver',
            'nomina.compras_via_nomina.crear',
            'nomina.compras_via_nomina.editar',
            'nomina.compras_via_nomina.eliminar',
            'nomina.compras_via_nomina.ver',
            'nomina.conceptos.crear',
            'nomina.conceptos.editar',
            'nomina.conceptos.eliminar',
            'nomina.conceptos.ver',
            'nomina.descuentos.crear',
            'nomina.descuentos.editar',
            'nomina.descuentos.eliminar',
            'nomina.descuentos.ver',
            'nomina.percepciones.crear',
            'nomina.percepciones.editar',
            'nomina.percepciones.eliminar',
            'nomina.percepciones.ver',
            'nomina.politicas.crear',
            'nomina.politicas.editar',
            'nomina.politicas.eliminar',
            'nomina.politicas.ver',
            'nomina.prenomina.aprobar',
            'nomina.prenomina.calcular',
            'nomina.prenomina.cerrar',
            'nomina.prenomina.crear',
            'nomina.prenomina.editar',
            'nomina.prenomina.eliminar',
            'nomina.prenomina.reabrir',
            'nomina.prenomina.rechazar',
            'nomina.prenomina.solicitar_aprobacion',
            'nomina.prenomina.ver',
            'nomina.procesos.ver',
            'nomina.recibos_cfdi.ver',
            'payroll.menu.view',
            'rrhh.asistencias.crear',
            'rrhh.asistencias.editar',
            'rrhh.asistencias.eliminar',
            'rrhh.asistencias.mobile_clock',
            'rrhh.asistencias.revisar_geocerca',
            'rrhh.asistencias.revisar_movil',
            'rrhh.asistencias.ver',
            'rrhh.bajas.crear',
            'rrhh.bajas.editar',
            'rrhh.bajas.eliminar',
            'rrhh.bajas.ver',
            'rrhh.catalogos.crear',
            'rrhh.catalogos.editar',
            'rrhh.catalogos.eliminar',
            'rrhh.catalogos.ver',
            'rrhh.contratos.crear',
            'rrhh.contratos.editar',
            'rrhh.contratos.eliminar',
            'rrhh.contratos.ver',
            'rrhh.credenciales.descargar',
            'rrhh.credenciales.ver',
            'rrhh.departamentos.crear',
            'rrhh.departamentos.editar',
            'rrhh.departamentos.eliminar',
            'rrhh.departamentos.ver',
            'rrhh.empleados.crear',
            'rrhh.empleados.editar',
            'rrhh.empleados.eliminar',
            'rrhh.empleados.ver',
            'rrhh.expediente.crear',
            'rrhh.expediente.editar',
            'rrhh.expediente.eliminar',
            'rrhh.expediente.ver',
            'rrhh.geocercas.crear',
            'rrhh.geocercas.editar',
            'rrhh.geocercas.eliminar',
            'rrhh.geocercas.ver',
            'rrhh.horarios.crear',
            'rrhh.horarios.editar',
            'rrhh.horarios.eliminar',
            'rrhh.horarios.ver',
            'rrhh.incidencias.crear',
            'rrhh.incidencias.editar',
            'rrhh.incidencias.eliminar',
            'rrhh.incidencias.ver',
            'rrhh.organigrama.ver',
            'rrhh.puestos.crear',
            'rrhh.puestos.editar',
            'rrhh.puestos.eliminar',
            'rrhh.puestos.ver',
            'rrhh.terminales.crear',
            'rrhh.terminales.editar',
            'rrhh.terminales.ver',
            'rrhh.tipos_documento.crear',
            'rrhh.tipos_documento.editar',
            'rrhh.tipos_documento.eliminar',
            'rrhh.tipos_documento.ver',
            'rrhh.tipos_incidencia.crear',
            'rrhh.tipos_incidencia.editar',
            'rrhh.tipos_incidencia.eliminar',
            'rrhh.tipos_incidencia.ver',
            'rrhh.vacaciones.crear',
            'rrhh.vacaciones.editar',
            'rrhh.vacaciones.eliminar',
            'rrhh.vacaciones.ver',
        ];
    }

    private function rrhhAssistantPermissions(): array
    {
        return [
            'nomina.compras_via_nomina.crear',
            'nomina.compras_via_nomina.editar',
            'nomina.compras_via_nomina.eliminar',
            'nomina.compras_via_nomina.ver',
            'nomina.descuentos.crear',
            'nomina.descuentos.editar',
            'nomina.descuentos.eliminar',
            'nomina.descuentos.ver',
            'nomina.percepciones.crear',
            'nomina.percepciones.editar',
            'nomina.percepciones.eliminar',
            'nomina.percepciones.ver',
            'nomina.recibos_cfdi.ver',
            'payroll.menu.view',
            'rrhh.asistencias.crear',
            'rrhh.asistencias.editar',
            'rrhh.asistencias.eliminar',
            'rrhh.asistencias.mobile_clock',
            'rrhh.asistencias.revisar_geocerca',
            'rrhh.asistencias.revisar_movil',
            'rrhh.asistencias.ver',
            'rrhh.bajas.crear',
            'rrhh.bajas.editar',
            'rrhh.bajas.eliminar',
            'rrhh.bajas.ver',
            'rrhh.contratos.crear',
            'rrhh.contratos.editar',
            'rrhh.contratos.eliminar',
            'rrhh.contratos.ver',
            'rrhh.credenciales.descargar',
            'rrhh.credenciales.ver',
            'rrhh.empleados.crear',
            'rrhh.empleados.editar',
            'rrhh.empleados.eliminar',
            'rrhh.empleados.ver',
            'rrhh.expediente.crear',
            'rrhh.expediente.editar',
            'rrhh.expediente.eliminar',
            'rrhh.expediente.ver',
            'rrhh.incidencias.crear',
            'rrhh.incidencias.editar',
            'rrhh.incidencias.eliminar',
            'rrhh.incidencias.ver',
            'rrhh.organigrama.ver',
            'rrhh.vacaciones.crear',
            'rrhh.vacaciones.editar',
            'rrhh.vacaciones.eliminar',
            'rrhh.vacaciones.ver',
        ];
    }

    private function permissionIdsForNames(array $names): array
    {
        $names = array_values(
            array_unique($names)
        );

        $rows = DB::table('permissions')
            ->where('guard_name', self::GUARD)
            ->whereIn('name', $names)
            ->pluck('id', 'name');

        if ($rows->count() !== count($names)) {
            $missing = array_values(
                array_diff(
                    $names,
                    $rows->keys()->all()
                )
            );

            throw new RuntimeException(
                'SEC8O7: faltan permisos: '
                . implode(', ', $missing)
            );
        }

        return $rows
            ->values()
            ->map(static fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
    }

    private function assertCount(
        int $expected,
        array $ids,
        string $context
    ): void {
        if (count($ids) !== $expected) {
            throw new RuntimeException(
                "SEC8O7: {$context} esperaba {$expected}; "
                . 'obtuvo '
                . count($ids)
                . '.'
            );
        }
    }

    private function assertFinalMatrix(
        array $companyIds
    ): void {
        $expected = [
            'RRHH Administrador' => 102,
            'RRHH Asistente' => 48,
            'Servicio - Recepción' => 12,
            'Servicio - Técnico' => 7,
            'Servicio - Supervisor' => 24,
            'Servicio - Cajero Reparaciones' => 7,
            'Servicio - Encargado de Técnicos' => 16,
        ];

        foreach ($companyIds as $companyId) {
            foreach ($expected as $roleName => $permissionCount) {
                $roleIds = DB::table('roles')
                    ->where('company_id', $companyId)
                    ->where('guard_name', self::GUARD)
                    ->where('name', $roleName)
                    ->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all();

                if (count($roleIds) !== 1) {
                    throw new RuntimeException(
                        "SEC8O7: {$roleName} no es único "
                        . "en company_id={$companyId}."
                    );
                }

                $actual = DB::table('role_has_permissions')
                    ->where('role_id', $roleIds[0])
                    ->count();

                if ($actual !== $permissionCount) {
                    throw new RuntimeException(
                        "SEC8O7: {$roleName} company_id="
                        . "{$companyId} esperaba {$permissionCount}; "
                        . "obtuvo {$actual}."
                    );
                }
            }
        }
    }

    private function serviceReceptionPermissions(): array
    {
        return [
            'contacts.view',
            'contacts.create',
            'contacts.update',
            'service.menu.view',
            'service.events.view',
            'service.cases.view',
            'service.cases.create',
            'service.cases.update',
            'service.cases.classify',
            'service.repairs.view',
            'service.repairs.update',
            'service.repairs.public_tracking.view',
        ];
    }

    private function serviceTechnicianPermissions(): array
    {
        return [
            'service.menu.view',
            'service.events.view',
            'service.cases.view',
            'service.repairs.view',
            'service.repairs.update',
            'service.repairs.work',
            'service.repairs.public_tracking.view',
        ];
    }

    private function serviceSupervisorPermissions(): array
    {
        return [
            'contacts.view',
            'contacts.create',
            'contacts.update',
            'service.menu.view',
            'service.events.view',
            'service.cases.view',
            'service.cases.create',
            'service.cases.update',
            'service.cases.delete',
            'service.cases.classify',
            'service.repairs.view',
            'service.repairs.create',
            'service.repairs.update',
            'service.repairs.delete',
            'service.repairs.delivery',
            'service.repairs.quote.approve',
            'service.repairs.supervisor_review.approve',
            'service.repairs.approve_warranty',
            'service.repairs.reject_warranty',
            'service.repairs.authorize_delivery',
            'service.repairs.reopen',
            'service.repairs.public_tracking.view',
            'service.repairs.public_tracking.regenerate',
            'service.repairs.public_tracking.disable',
        ];
    }

    private function serviceCashierPermissions(): array
    {
        return [
            'account_receivables.view',
            'account_receivables.collect',
            'service.menu.view',
            'service.events.view',
            'service.repairs.view',
            'service.repairs.public_tracking.view',
            'service.repairs.economic',
        ];
    }

    private function serviceManagerPermissions(): array
    {
        return [
            'contacts.view',
            'contacts.create',
            'contacts.update',
            'service.menu.view',
            'service.events.view',
            'service.cases.view',
            'service.cases.update',
            'service.cases.classify',
            'service.repairs.view',
            'service.repairs.update',
            'service.repairs.work',
            'service.repairs.delivery',
            'service.repairs.quote.submit',
            'service.repairs.public_tracking.view',
            'service.repairs.public_tracking.regenerate',
            'service.repairs.public_tracking.disable',
        ];
    }
};
