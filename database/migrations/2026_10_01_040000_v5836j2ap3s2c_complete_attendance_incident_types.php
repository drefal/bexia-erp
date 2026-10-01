<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $companyIds = [
        1, 11, 12, 13, 14, 15,
        16, 17, 18, 19, 20, 21,
    ];

    private array $types = [
        'RETARDO' => [
            'name' => 'Retardo',
            'effect' => 'deduction',
            'requires_approval' => true,
            'affects_payroll' => true,
        ],
        'FALTA' => [
            'name' => 'Falta',
            'effect' => 'deduction',
            'requires_approval' => true,
            'affects_payroll' => true,
        ],
        'SALIDA_TEMPRANA' => [
            'name' => 'Salida temprana',
            'effect' => 'deduction',
            'requires_approval' => true,
            'affects_payroll' => true,
        ],
        'JORNADA_INCOMPLETA' => [
            'name' => 'Jornada incompleta',
            'effect' => 'informational',
            'requires_approval' => true,
            'affects_payroll' => false,
        ],
        'EXCESO_COMIDA' => [
            'name' => 'Exceso de comida',
            'effect' => 'informational',
            'requires_approval' => true,
            'affects_payroll' => false,
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('hr_incident_types')) {
            return;
        }

        $now = now();

        foreach ($this->companyIds as $companyId) {
            foreach ($this->types as $code => $config) {
                /*
                 * Primero localizar por codigo.
                 * Si no existe, localizar por nombre para evitar
                 * duplicar un catalogo creado manualmente.
                 */
                $existing = DB::table('hr_incident_types')
                    ->where('company_id', $companyId)
                    ->where('code', $code)
                    ->first();

                if (! $existing) {
                    $existing = DB::table('hr_incident_types')
                        ->where('company_id', $companyId)
                        ->where('name', $config['name'])
                        ->first();
                }

                $payload = [
                    'name' => $config['name'],
                    'code' => $code,
                    'effect' => $config['effect'],
                    'requires_approval' => $config['requires_approval'],
                    'affects_payroll' => $config['affects_payroll'],
                    'is_active' => true,
                    'updated_at' => $now,
                ];

                if ($existing) {
                    DB::table('hr_incident_types')
                        ->where('id', $existing->id)
                        ->update($payload);

                    continue;
                }

                DB::table('hr_incident_types')->insert(
                    array_merge(
                        [
                            'company_id' => $companyId,
                            'created_at' => $now,
                        ],
                        $payload
                    )
                );
            }
        }
    }

    public function down(): void
    {
        /*
         * Intencionalmente NO eliminamos catalogos.
         *
         * Una vez que existan employee_incidents ligados a estos
         * tipos, borrar los catalogos no es un rollback seguro.
         *
         * El rollback operativo debe restaurar el snapshot previo
         * solamente si se ejecuta antes de uso real.
         */
    }
};
