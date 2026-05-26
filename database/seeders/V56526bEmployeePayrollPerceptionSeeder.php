<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class V56526bEmployeePayrollPerceptionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'nomina.percepciones.ver',
            'nomina.percepciones.crear',
            'nomina.percepciones.editar',
            'nomina.percepciones.eliminar',
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

        $concepts = [
            [
                'code' => 'BONO_PRODUCTIVIDAD',
                'name' => 'Bono productividad',
                'type' => 'perception',
                'category' => 'manual',
                'source' => 'manual',
                'unit' => 'amount',
                'sort_order' => 40,
            ],
            [
                'code' => 'COMISION',
                'name' => 'Comisión',
                'type' => 'perception',
                'category' => 'manual',
                'source' => 'manual',
                'unit' => 'amount',
                'sort_order' => 50,
            ],
            [
                'code' => 'GRATIFICACION',
                'name' => 'Gratificación',
                'type' => 'perception',
                'category' => 'manual',
                'source' => 'manual',
                'unit' => 'amount',
                'sort_order' => 60,
            ],
            [
                'code' => 'APOYO_TRANSPORTE',
                'name' => 'Apoyo transporte',
                'type' => 'perception',
                'category' => 'manual',
                'source' => 'manual',
                'unit' => 'amount',
                'sort_order' => 70,
            ],
            [
                'code' => 'APOYO_COMIDA',
                'name' => 'Apoyo comida',
                'type' => 'perception',
                'category' => 'manual',
                'source' => 'manual',
                'unit' => 'amount',
                'sort_order' => 80,
            ],
        ];

        $companies = DB::table('companies')->select('id')->orderBy('id')->get();

        foreach ($companies as $company) {
            foreach ($concepts as $concept) {
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

                DB::table('payroll_concepts')->insert(array_merge([
                    'company_id' => $company->id,
                    'code' => $concept['code'],
                    'created_at' => now(),
                ], $payload));
            }
        }
    }
}
