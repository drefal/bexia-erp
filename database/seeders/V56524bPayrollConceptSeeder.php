<?php

namespace Database\Seeders;

use App\Models\PayrollConcept;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class V56524bPayrollConceptSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'nomina.conceptos.ver',
            'nomina.conceptos.crear',
            'nomina.conceptos.editar',
            'nomina.conceptos.eliminar',
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

        if (! DB::getSchemaBuilder()->hasTable('payroll_concepts') || ! DB::getSchemaBuilder()->hasTable('companies')) {
            return;
        }

        $companies = DB::table('companies')->select('id')->orderBy('id')->get();

        foreach ($companies as $company) {
            foreach (PayrollConcept::defaults() as $concept) {
                $existing = DB::table('payroll_concepts')
                    ->where('company_id', $company->id)
                    ->where('code', $concept['code'])
                    ->first();

                $payload = [
                    'name' => $concept['name'],
                    'type' => $concept['type'],
                    'category' => $concept['category'],
                    'source' => $concept['source'],
                    'unit' => $concept['unit'],
                    'is_active' => true,
                    'sort_order' => $concept['sort_order'],
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('payroll_concepts')
                        ->where('id', $existing->id)
                        ->update($payload);

                    continue;
                }

                DB::table('payroll_concepts')->insert([
                    'company_id' => $company->id,
                    'code' => $concept['code'],
                    ...$payload,
                    'created_at' => now(),
                ]);
            }
        }
    }
}
