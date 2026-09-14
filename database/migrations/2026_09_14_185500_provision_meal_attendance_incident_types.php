<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = [
            [
                'name' => 'Comida no registrada',
                'code' => 'COMIDA_NO_REGISTRADA',
            ],
            [
                'name' => 'Regreso de comida no registrado',
                'code' => 'REGRESO_COMIDA_NO_REGISTRADO',
            ],
        ];

        $companyIds = DB::table('companies')
            ->orderBy('id')
            ->pluck('id');

        foreach ($companyIds as $companyId) {
            foreach ($definitions as $definition) {
                $byCode = DB::table('hr_incident_types')
                    ->where('company_id', $companyId)
                    ->where('code', $definition['code'])
                    ->orderBy('id')
                    ->get();

                if ($byCode->count() > 1) {
                    throw new \RuntimeException(
                        'Tipos duplicados para empresa '
                        . $companyId
                        . ' y codigo '
                        . $definition['code']
                    );
                }

                if ($byCode->count() === 1) {
                    $existing = $byCode->first();

                    $nameConflict = DB::table('hr_incident_types')
                        ->where('company_id', $companyId)
                        ->where('name', $definition['name'])
                        ->where('id', '<>', $existing->id)
                        ->exists();

                    if ($nameConflict) {
                        throw new \RuntimeException(
                            'Conflicto de nombre para empresa '
                            . $companyId
                            . ': '
                            . $definition['name']
                        );
                    }

                    DB::table('hr_incident_types')
                        ->where('id', $existing->id)
                        ->update([
                            'name' => $definition['name'],
                            'effect' => 'informational',
                            'requires_approval' => true,
                            'affects_payroll' => false,
                            'is_active' => true,
                            'updated_at' => now(),
                        ]);

                    continue;
                }

                $byName = DB::table('hr_incident_types')
                    ->where('company_id', $companyId)
                    ->where('name', $definition['name'])
                    ->first();

                if ($byName) {
                    $existingCode = trim(
                        (string) ($byName->code ?? '')
                    );

                    if (
                        $existingCode !== ''
                        && $existingCode !== $definition['code']
                    ) {
                        throw new \RuntimeException(
                            'Conflicto de codigo para empresa '
                            . $companyId
                            . ': '
                            . $definition['name']
                        );
                    }

                    DB::table('hr_incident_types')
                        ->where('id', $byName->id)
                        ->update([
                            'code' => $definition['code'],
                            'effect' => 'informational',
                            'requires_approval' => true,
                            'affects_payroll' => false,
                            'is_active' => true,
                            'updated_at' => now(),
                        ]);

                    continue;
                }

                DB::table('hr_incident_types')->insert([
                    'company_id' => $companyId,
                    'name' => $definition['name'],
                    'code' => $definition['code'],
                    'effect' => 'informational',
                    'requires_approval' => true,
                    'affects_payroll' => false,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        /*
         * Rollback no destructivo.
         *
         * Una vez que estos tipos hayan sido usados por incidencias
         * historicas, eliminarlos automaticamente podria romper
         * referencias. El rollback operativo de DEV conserva una lista
         * exacta de los IDs creados por c12b.
         */
    }
};
