<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $basePermissions = [
        'expenses.view',
        'expenses.create',
        'expenses.update',
        'expenses.submit',
        'expenses.reports.view',
        'expenses.reports.export',
    ];

    public function up(): void
    {
        $allPermissions = [
            'expenses.admin',
            'expenses.view',
            'expenses.create',
            'expenses.update',
            'expenses.submit',
            'expenses.view_all',
            'expenses.create_for_others',
            'expenses.categories.manage',
            'expenses.reports.view',
            'expenses.reports.export',
            'petty_cash.view',
            'petty_cash.manage',
            'petty_cash.transfer',
        ];

        foreach ($allPermissions as $name) {
            DB::table('permissions')
                ->updateOrInsert(
                    [
                        'name' => $name,
                        'guard_name' => 'web',
                    ],
                    [
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
        }

        /*
         * Creamos Usuario y Asistente solamente
         * en las empresas donde ya existe
         * Gastos - Administrador.
         */
        $companyIds = DB::table('roles')
            ->where(
                'name',
                'Gastos - Administrador'
            )
            ->whereNotNull('company_id')
            ->pluck('company_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        foreach ($companyIds as $companyId) {
            $this->syncRole(
                $companyId,
                'Gastos - Usuario',
                $this->basePermissions
            );

            $this->syncRole(
                $companyId,
                'Gastos - Asistente',
                [
                    ...$this->basePermissions,
                    'expenses.view_all',
                    'expenses.create_for_others',
                ]
            );

            $this->syncRole(
                $companyId,
                'Gastos - Administrador',
                [
                    ...$this->basePermissions,
                    'expenses.admin',
                    'expenses.view_all',
                    'expenses.create_for_others',
                    'expenses.categories.manage',
                    'petty_cash.view',
                    'petty_cash.manage',
                    'petty_cash.transfer',
                ]
            );
        }
    }

    private function syncRole(
        int $companyId,
        string $name,
        array $permissions
    ): void {
        $role = DB::table('roles')
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->first();

        if (! $role) {
            $data = [
                'company_id' => $companyId,
                'name' => $name,
                'guard_name' => 'web',
            ];

            if (
                Schema::hasColumn(
                    'roles',
                    'created_at'
                )
            ) {
                $data['created_at'] = now();
                $data['updated_at'] = now();
            }

            $roleId = DB::table('roles')
                ->insertGetId($data);
        } else {
            $roleId = (int) $role->id;
        }

        $permissionIds =
            DB::table('permissions')
                ->whereIn(
                    'name',
                    $permissions
                )
                ->pluck('id');

        DB::table('role_has_permissions')
            ->where('role_id', $roleId)
            ->delete();

        foreach ($permissionIds as $permissionId) {
            DB::table(
                'role_has_permissions'
            )->insert([
                'permission_id' =>
                    (int) $permissionId,
                'role_id' =>
                    $roleId,
            ]);
        }
    }

    public function down(): void
    {
        /*
         * No eliminamos roles automáticamente
         * porque podrían haber sido asignados
         * después de ejecutar la migración.
         */
    }
};
