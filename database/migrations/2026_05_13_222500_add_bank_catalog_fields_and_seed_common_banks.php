<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * BEXIA_V5524A5_BANK_CATALOG
         * Catálogo editable de bancos por empresa.
         * code = clave bancaria sugerida.
         * name = nombre corto editable.
         * legal_name = razón social editable.
         */

        if (Schema::hasTable('banks')) {
            Schema::table('banks', function (Blueprint $table) {
                if (! Schema::hasColumn('banks', 'legal_name')) {
                    $table->string('legal_name')->nullable()->after('name');
                }

                if (! Schema::hasColumn('banks', 'catalog_source')) {
                    $table->string('catalog_source')->nullable()->after('notes');
                }
            });

            $this->seedCommonBanks();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('banks')) {
            return;
        }

        Schema::table('banks', function (Blueprint $table) {
            if (Schema::hasColumn('banks', 'catalog_source')) {
                $table->dropColumn('catalog_source');
            }

            if (Schema::hasColumn('banks', 'legal_name')) {
                $table->dropColumn('legal_name');
            }
        });
    }

    private function seedCommonBanks(): void
    {
        $companies = DB::table('companies')->pluck('id')->all();

        if ($companies === []) {
            return;
        }

        $banks = [
            ['002', 'BANAMEX', 'Banco Nacional de México, S.A., Institución de Banca Múltiple, Grupo Financiero Banamex'],
            ['006', 'BANCOMEXT', 'Banco Nacional de Comercio Exterior, Sociedad Nacional de Crédito, Institución de Banca de Desarrollo'],
            ['009', 'BANOBRAS', 'Banco Nacional de Obras y Servicios Públicos, Sociedad Nacional de Crédito, Institución de Banca de Desarrollo'],
            ['012', 'BBVA', 'BBVA México, S.A., Institución de Banca Múltiple, Grupo Financiero BBVA México'],
            ['014', 'SANTANDER', 'Banco Santander México, S.A., Institución de Banca Múltiple, Grupo Financiero Santander México'],
            ['019', 'BANJERCITO', 'Banco Nacional del Ejército, Fuerza Aérea y Armada, Sociedad Nacional de Crédito, Institución de Banca de Desarrollo'],
            ['021', 'HSBC', 'HSBC México, S.A., Institución de Banca Múltiple, Grupo Financiero HSBC'],
            ['030', 'BAJÍO', 'Banco del Bajío, S.A., Institución de Banca Múltiple'],
            ['036', 'INBURSA', 'Banco Inbursa, S.A., Institución de Banca Múltiple, Grupo Financiero Inbursa'],
            ['042', 'MIFEL', 'Banca Mifel, S.A., Institución de Banca Múltiple, Grupo Financiero Mifel'],
            ['044', 'SCOTIABANK', 'Scotiabank Inverlat, S.A.'],
            ['058', 'BANREGIO', 'Banco Regional, S.A., Institución de Banca Múltiple, Banregio Grupo Financiero'],
            ['059', 'INVEX', 'Banco Invex, S.A., Institución de Banca Múltiple, Invex Grupo Financiero'],
            ['060', 'BANSI', 'Bansí, S.A., Institución de Banca Múltiple'],
            ['062', 'AFIRME', 'Banca Afirme, S.A., Institución de Banca Múltiple'],
            ['072', 'BANORTE', 'Banco Mercantil del Norte, S.A., Institución de Banca Múltiple, Grupo Financiero Banorte'],
            ['103', 'AMERICAN EXPRESS', 'American Express Bank México, S.A., Institución de Banca Múltiple'],
            ['106', 'BANK OF AMERICA', 'Bank of America México, S.A., Institución de Banca Múltiple, Grupo Financiero Bank of America'],
            ['110', 'JP MORGAN', 'Banco J.P. Morgan, S.A., Institución de Banca Múltiple, J.P. Morgan Grupo Financiero'],
            ['112', 'MONEX', 'Banco Monex, S.A., Institución de Banca Múltiple'],
            ['113', 'VE POR MÁS', 'Banco Ve por Más, S.A., Institución de Banca Múltiple'],
            ['116', 'ING', 'ING Bank México, S.A., Institución de Banca Múltiple, ING Grupo Financiero'],
            ['124', 'DEUTSCHE', 'Deutsche Bank México, S.A., Institución de Banca Múltiple'],
            ['126', 'CREDIT SUISSE', 'Banco Credit Suisse México, S.A., Institución de Banca Múltiple, Grupo Financiero Credit Suisse México'],
            ['127', 'AZTECA', 'Banco Azteca, S.A., Institución de Banca Múltiple'],
            ['128', 'AUTOFIN', 'Banco Autofin México, S.A., Institución de Banca Múltiple'],
            ['129', 'BARCLAYS', 'Barclays Bank México, S.A., Institución de Banca Múltiple, Grupo Financiero Barclays México'],
            ['130', 'COMPARTAMOS', 'Banco Compartamos, S.A., Institución de Banca Múltiple'],
            ['131', 'BANCO FAMSA', 'Banco Ahorro Famsa, S.A., Institución de Banca Múltiple'],
            ['132', 'BMULTIVA', 'Banco Multiva, S.A., Institución de Banca Múltiple, Multivalores Grupo Financiero'],
            ['133', 'ACTINVER', 'Banco Actinver, S.A., Institución de Banca Múltiple, Grupo Financiero Actinver'],
            ['134', 'WAL-MART', 'Banco Wal-Mart de México Adelante, S.A., Institución de Banca Múltiple'],
            ['135', 'NAFIN', 'Nacional Financiera, Sociedad Nacional de Crédito, Institución de Banca de Desarrollo'],
            ['136', 'INTERBANCO', 'Inter Banco, S.A., Institución de Banca Múltiple'],
            ['137', 'BANCOPPEL', 'BanCoppel, S.A., Institución de Banca Múltiple'],
            ['138', 'ABC CAPITAL', 'ABC Capital, S.A., Institución de Banca Múltiple'],
            ['139', 'UBS BANK', 'UBS Bank México, S.A., Institución de Banca Múltiple, UBS Grupo Financiero'],
            ['140', 'CONSUBANCO', 'Consubanco, S.A., Institución de Banca Múltiple'],
            ['141', 'VOLKSWAGEN', 'Volkswagen Bank, S.A., Institución de Banca Múltiple'],
            ['143', 'CIBANCO', 'CIBanco, S.A.'],
            ['145', 'BANCO BASE', 'Banco Base, S.A., Institución de Banca Múltiple'],
            ['166', 'BANSEFI', 'Banco del Ahorro Nacional y Servicios Financieros, Sociedad Nacional de Crédito, Institución de Banca de Desarrollo'],
            ['168', 'HIPOTECARIA FEDERAL', 'Sociedad Hipotecaria Federal, Sociedad Nacional de Crédito, Institución de Banca de Desarrollo'],
            ['600', 'MONEXCB', 'Monex Casa de Bolsa, S.A. de C.V., Monex Grupo Financiero'],
            ['601', 'GBM', 'GBM Grupo Bursátil Mexicano, S.A. de C.V., Casa de Bolsa'],
            ['602', 'MASARI', 'Masari Casa de Bolsa, S.A.'],
            ['605', 'VALUE', 'Value, S.A. de C.V., Casa de Bolsa'],
            ['608', 'VECTOR', 'Vector Casa de Bolsa, S.A. de C.V.'],
            ['614', 'ACCIVAL', 'Acciones y Valores Banamex, S.A. de C.V., Casa de Bolsa'],
            ['616', 'FINAMEX', 'Casa de Bolsa Finamex, S.A. de C.V.'],
            ['617', 'VALMEX', 'Valores Mexicanos Casa de Bolsa, S.A. de C.V.'],
            ['621', 'CB ACTINVER', 'Actinver Casa de Bolsa, S.A. de C.V.'],
            ['630', 'CB INTERCAM', 'Intercam Casa de Bolsa, S.A. de C.V.'],
            ['631', 'CI BOLSA', 'CI Casa de Bolsa, S.A. de C.V.'],
            ['638', 'AKALA', 'Akala, S.A. de C.V., Sociedad Financiera Popular'],
            ['646', 'STP', 'Sistema de Transferencias y Pagos STP, S.A. de C.V., SOFOM ENR'],
            ['647', 'TELECOMM', 'Telecomunicaciones de México'],
            ['670', 'LIBERTAD', 'Libertad Servicios Financieros, S.A. de C.V.'],
            ['901', 'CLS', 'Cls Bank International'],
            ['902', 'INDEVAL', 'S.D. Indeval, S.A. de C.V.'],
            ['999', 'N/A', 'No aplica'],
        ];

        foreach ($companies as $companyId) {
            foreach ($banks as [$code, $name, $legalName]) {
                $existing = DB::table('banks')
                    ->where('company_id', $companyId)
                    ->where('code', $code)
                    ->first();

                if ($existing) {
                    DB::table('banks')
                        ->where('id', $existing->id)
                        ->update([
                            'name' => $existing->name ?: $name,
                            'legal_name' => $existing->legal_name ?: $legalName,
                            'catalog_source' => $existing->catalog_source ?: 'catalogo_bancos_pdf_web',
                            'updated_at' => now(),
                        ]);

                    continue;
                }

                DB::table('banks')->insert([
                    'company_id' => $companyId,
                    'code' => $code,
                    'name' => $name,
                    'legal_name' => $legalName,
                    'is_active' => true,
                    'notes' => null,
                    'catalog_source' => 'catalogo_bancos_pdf_web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
