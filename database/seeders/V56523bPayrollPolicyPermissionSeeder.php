<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class V56523bPayrollPolicyPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'nomina.politicas.ver',
            'nomina.politicas.crear',
            'nomina.politicas.editar',
            'nomina.politicas.eliminar',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Role::query()
            ->whereIn('name', ['admin', 'Admin Empresa', 'Admin Grupo'])
            ->get()
            ->each(function (Role $role) use ($permissions): void {
                foreach ($permissions as $permission) {
                    $role->givePermissionTo($permission);
                }
            });

        if (DB::getSchemaBuilder()->hasTable('payroll_policies') && DB::getSchemaBuilder()->hasTable('companies')) {
            $companies = DB::table('companies')->select('id')->orderBy('id')->get();

            foreach ($companies as $company) {
                $exists = DB::table('payroll_policies')
                    ->where('company_id', $company->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('payroll_policies')->insert([
                    'company_id' => $company->id,
                    'name' => 'Política estándar de nómina',
                    'status' => 'active',
                    'is_active' => true,
                    'overtime_multiplier' => 2,
                    'rest_day_overtime_multiplier' => 2,
                    'holiday_overtime_multiplier' => 2,
                    'late_tolerance_minutes' => 0,
                    'late_discount_mode' => 'none',
                    'late_minutes_to_absence' => 0,
                    'early_leave_discount_mode' => 'none',
                    'absence_discount_mode' => 'incident_only',
                    'rest_day_worked_mode' => 'informational',
                    'holiday_worked_mode' => 'informational',
                    'settings' => json_encode([]),
                    'notes' => 'Política base creada automáticamente. Mantiene el comportamiento previo de pre-nómina.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
