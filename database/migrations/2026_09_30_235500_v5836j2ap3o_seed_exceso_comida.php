<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Empresas Grupo L7 con workflow RRHH configurado.
     */
    private const COMPANY_IDS = [
        1,
        11,
        12,
        13,
        14,
        15,
        16,
        17,
        18,
        19,
        20,
        21,
    ];

    public function up(): void
    {
        foreach (self::COMPANY_IDS as $companyId) {
            $existing = DB::table('hr_incident_types')
                ->where('company_id', $companyId)
                ->where(function ($query) {
                    $query
                        ->where('code', 'EXCESO_COMIDA')
                        ->orWhere('name', 'Exceso de comida');
                })
                ->first();

            if ($existing) {
                /*
                 * Idempotente:
                 * si el catálogo ya fue creado manualmente en DEV/PROD,
                 * normalizamos la configuración sin generar duplicados.
                 */
                DB::table('hr_incident_types')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => 'Exceso de comida',
                        'code' => 'EXCESO_COMIDA',
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
                'name' => 'Exceso de comida',
                'code' => 'EXCESO_COMIDA',
                'effect' => 'informational',
                'requires_approval' => true,
                'affects_payroll' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        /*
         * Eliminamos únicamente el catálogo específico creado/configurado
         * por esta migración para las empresas objetivo.
         *
         * No toca incidencias históricas.
         */
        DB::table('hr_incident_types')
            ->whereIn('company_id', self::COMPANY_IDS)
            ->where('code', 'EXCESO_COMIDA')
            ->where('name', 'Exceso de comida')
            ->where('effect', 'informational')
            ->where('affects_payroll', false)
            ->delete();
    }
};
