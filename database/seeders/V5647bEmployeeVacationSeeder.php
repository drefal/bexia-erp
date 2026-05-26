<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class V5647bEmployeeVacationSeeder extends Seeder
{
    public function run(): void
    {
        $this->permissions();
        $this->vacationIncidentType();
    }

    private function permissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $guard = 'web';

        $permissions = [
            'rrhh.vacaciones.ver',
            'rrhh.vacaciones.crear',
            'rrhh.vacaciones.editar',
            'rrhh.vacaciones.eliminar',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        Role::query()
            ->whereIn('name', ['admin', 'Admin Empresa', 'Admin Grupo', 'Administrador'])
            ->where('guard_name', $guard)
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function vacationIncidentType(): void
    {
        if (! Schema::hasTable('hr_incident_types') || ! Schema::hasTable('companies')) {
            return;
        }

        $companies = DB::table('companies')->select('id')->get();

        foreach ($companies as $company) {
            $existing = DB::table('hr_incident_types')
                ->where('company_id', $company->id)
                ->where('code', 'VACACIONES')
                ->first();

            $payload = [
                'company_id' => $company->id,
                'name' => 'Vacaciones',
                'code' => 'VACACIONES',
                'effect' => 'neutral',
                'requires_approval' => true,
                'affects_payroll' => false,
                'is_active' => true,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('hr_incident_types')->where('id', $existing->id)->update($payload);
            } else {
                $payload['created_at'] = now();
                DB::table('hr_incident_types')->insert($payload);
            }
        }
    }
}
