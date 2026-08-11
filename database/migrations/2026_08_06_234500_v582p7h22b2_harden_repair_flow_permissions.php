<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $companies = [1, 11, 14, 15, 17];

    private array $grants = [
        'service.repairs.quote.submit' => [
            'Servicio - Encargado de Técnicos',
            'Servicio - Supervisor',
        ],
        'service.repairs.quote.approve' => [
            'Servicio - Supervisor',
        ],
        'service.repairs.work' => [
            'Servicio - Técnico',
            'Servicio - Encargado de Técnicos',
            'Servicio - Supervisor',
        ],
        'service.repairs.supervisor_review.approve' => [
            'Servicio - Supervisor',
        ],
        'service.repairs.delivery' => [
            'Servicio - Cajero Reparaciones',
            'Servicio - Supervisor',
        ],
        'service.repairs.economic' => [
            'Servicio - Cajero Reparaciones',
            'Servicio - Supervisor',
        ],
    ];

    public function up(): void
    {
        if (
            ! Schema::hasTable('permissions')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('role_has_permissions')
        ) {
            return;
        }

        foreach ($this->grants as $permissionName => $roleNames) {
            $permissionId = DB::table('permissions')
                ->where('name', $permissionName)
                ->where('guard_name', 'web')
                ->value('id');

            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $roleIds = DB::table('roles')
                ->whereIn('company_id', $this->companies)
                ->whereIn('name', $roleNames)
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $this->remove(
            'Servicio - Recepción',
            'service.repairs.create'
        );
        $this->remove(
            'Servicio - Técnico',
            'service.repairs.public_tracking.disable'
        );
        $this->remove(
            'Servicio - Técnico',
            'service.repairs.public_tracking.regenerate'
        );
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('permissions')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('role_has_permissions')
        ) {
            return;
        }

        $this->restore(
            'Servicio - Recepción',
            'service.repairs.create'
        );
        $this->restore(
            'Servicio - Técnico',
            'service.repairs.public_tracking.disable'
        );
        $this->restore(
            'Servicio - Técnico',
            'service.repairs.public_tracking.regenerate'
        );

        foreach (array_keys($this->grants) as $permissionName) {
            $permissionId = DB::table('permissions')
                ->where('name', $permissionName)
                ->where('guard_name', 'web')
                ->value('id');

            if (! $permissionId) {
                continue;
            }

            DB::table('role_has_permissions')
                ->where('permission_id', $permissionId)
                ->delete();

            DB::table('permissions')
                ->where('id', $permissionId)
                ->delete();
        }
    }

    private function remove(
        string $roleName,
        string $permissionName
    ): void {
        $permissionId = DB::table('permissions')
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('company_id', $this->companies)
            ->where('name', $roleName)
            ->pluck('id');

        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $roleIds)
            ->delete();
    }

    private function restore(
        string $roleName,
        string $permissionName
    ): void {
        $permissionId = DB::table('permissions')
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('company_id', $this->companies)
            ->where('name', $roleName)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }
};
