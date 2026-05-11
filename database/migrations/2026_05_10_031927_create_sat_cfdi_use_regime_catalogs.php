<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sat_tax_regimes')) {
            Schema::create('sat_tax_regimes', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('name');
                $table->string('person_type', 20)->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sat_cfdi_uses')) {
            Schema::create('sat_cfdi_uses', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('name');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sat_cfdi_use_tax_regime')) {
            Schema::create('sat_cfdi_use_tax_regime', function (Blueprint $table): void {
                $table->id();
                $table->string('tax_regime_code', 10)->index();
                $table->string('cfdi_use_code', 10)->index();
                $table->boolean('active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['tax_regime_code', 'cfdi_use_code'], 'sat_cfdi_regime_use_unique');
            });
        }

        $regimes = [
            ['601', 'General de Ley Personas Morales', 'moral'],
            ['603', 'Personas Morales con Fines no Lucrativos', 'moral'],
            ['605', 'Sueldos y Salarios e Ingresos Asimilados a Salarios', 'fisica'],
            ['606', 'Arrendamiento', 'fisica'],
            ['607', 'Régimen de Enajenación o Adquisición de Bienes', 'fisica'],
            ['608', 'Demás ingresos', 'fisica'],
            ['610', 'Residentes en el Extranjero sin Establecimiento Permanente en México', 'ambos'],
            ['611', 'Ingresos por Dividendos', 'fisica'],
            ['612', 'Personas Físicas con Actividades Empresariales y Profesionales', 'fisica'],
            ['614', 'Ingresos por intereses', 'fisica'],
            ['615', 'Régimen de los ingresos por obtención de premios', 'fisica'],
            ['616', 'Sin obligaciones fiscales', 'ambos'],
            ['620', 'Sociedades Cooperativas de Producción que optan por diferir sus ingresos', 'moral'],
            ['621', 'Incorporación Fiscal', 'fisica'],
            ['622', 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras', 'ambos'],
            ['623', 'Opcional para Grupos de Sociedades', 'moral'],
            ['624', 'Coordinados', 'moral'],
            ['625', 'Actividades Empresariales con ingresos a través de Plataformas Tecnológicas', 'fisica'],
            ['626', 'Régimen Simplificado de Confianza', 'ambos'],
        ];

        foreach ($regimes as [$code, $name, $personType]) {
            DB::table('sat_tax_regimes')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'person_type' => $personType,
                    'active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $uses = [
            ['G01', 'Adquisición de mercancías'],
            ['G02', 'Devoluciones, descuentos o bonificaciones'],
            ['G03', 'Gastos en general'],
            ['I01', 'Construcciones'],
            ['I02', 'Mobiliario y equipo de oficina por inversiones'],
            ['I03', 'Equipo de transporte'],
            ['I04', 'Equipo de cómputo y accesorios'],
            ['I05', 'Dados, troqueles, moldes, matrices y herramental'],
            ['I06', 'Comunicaciones telefónicas'],
            ['I07', 'Comunicaciones satelitales'],
            ['I08', 'Otra maquinaria y equipo'],
            ['D01', 'Honorarios médicos, dentales y gastos hospitalarios'],
            ['D02', 'Gastos médicos por incapacidad o discapacidad'],
            ['D03', 'Gastos funerales'],
            ['D04', 'Donativos'],
            ['D05', 'Intereses reales efectivamente pagados por créditos hipotecarios'],
            ['D06', 'Aportaciones voluntarias al SAR'],
            ['D07', 'Primas por seguros de gastos médicos'],
            ['D08', 'Gastos de transportación escolar obligatoria'],
            ['D09', 'Depósitos en cuentas para el ahorro / planes de pensiones'],
            ['D10', 'Pagos por servicios educativos'],
            ['S01', 'Sin efectos fiscales'],
            ['CP01', 'Pagos'],
            ['CN01', 'Nómina'],
        ];

        foreach ($uses as [$code, $name]) {
            DB::table('sat_cfdi_uses')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $g = ['G01', 'G02', 'G03'];
        $i = ['I01', 'I02', 'I03', 'I04', 'I05', 'I06', 'I07', 'I08'];
        $d = ['D01', 'D02', 'D03', 'D04', 'D05', 'D06', 'D07', 'D08', 'D09', 'D10'];
        $base = ['S01', 'CP01'];

        $map = [
            '601' => array_merge($g, $i, $base),
            '603' => array_merge($g, $i, $base),
            '605' => array_merge($d, $base, ['CN01']),
            '606' => array_merge($g, $i, $d, $base),
            '607' => array_merge($d, $base),
            '608' => array_merge($d, $base),
            '610' => $base,
            '611' => array_merge($d, $base),
            '612' => array_merge($g, $i, $d, $base),
            '614' => array_merge($d, $base),
            '615' => array_merge($d, $base),
            '616' => $base,
            '620' => array_merge($g, $i, $base),
            '621' => array_merge($g, $i, $base),
            '622' => array_merge($g, $i, $base),
            '623' => array_merge($g, $i, $base),
            '624' => array_merge($g, $i, $base),
            '625' => array_merge($g, $i, $d, $base),
            '626' => array_merge($g, $i, $base),
        ];

        foreach ($map as $regime => $cfdiUses) {
            foreach (array_unique($cfdiUses) as $use) {
                DB::table('sat_cfdi_use_tax_regime')->updateOrInsert(
                    [
                        'tax_regime_code' => $regime,
                        'cfdi_use_code' => $use,
                    ],
                    [
                        'active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sat_cfdi_use_tax_regime');
        Schema::dropIfExists('sat_cfdi_uses');
        Schema::dropIfExists('sat_tax_regimes');
    }
};
