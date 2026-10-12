<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('payroll_concepts')
            && ! Schema::hasColumn(
                'payroll_concepts',
                'cfdi_key'
            )
        ) {
            Schema::table(
                'payroll_concepts',
                function (Blueprint $table): void {
                    $table
                        ->string('cfdi_key', 15)
                        ->nullable()
                        ->after('sat_key');
                }
            );
        }

        if (! Schema::hasTable('payroll_concepts')) {
            return;
        }

        /*
         * BEXIA_V5836_PAYROLL_CONCEPT_CFDI_KEY_SEED
         *
         * Clave = identificador interno del patrón SAT Nómina.
         * No sustituye code ni sat_key.
         *
         * Se asigna por code a todas las empresas existentes.
         */
        $map = [
            'APOYO_TRANSPORTE' =>
                'APOYO_TRANS',

            'BONO_PRODUCTIVIDAD' =>
                'BONO_PROD',

            'DESCUENTO_RECURRENTE' =>
                'DESC_REC',

            'INCIDENCIAS_DEDUCCION' =>
                'INCID_DED',

            'INCIDENCIAS_PERCEPCION' =>
                'INCID_PER',

            'POLITICA_RETARDO' =>
                'POL_RET',

            'POLITICA_SALIDA_TEMPRANA' =>
                'POL_SAL_TEMP',

            'PRESTAMO_EMPLEADO' =>
                'PREST_EMP',

            'INCAPACIDAD_PERCEPCION' =>
                'INCAP_PER',

            'INCAPACIDAD_DEDUCCION' =>
                'INCAP_DED',
        ];

        foreach ($map as $code => $cfdiKey) {
            DB::table('payroll_concepts')
                ->where('code', $code)
                ->update([
                    'cfdi_key' => $cfdiKey,
                ]);
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('payroll_concepts')
            && Schema::hasColumn(
                'payroll_concepts',
                'cfdi_key'
            )
        ) {
            Schema::table(
                'payroll_concepts',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'cfdi_key'
                    );
                }
            );
        }
    }
};
