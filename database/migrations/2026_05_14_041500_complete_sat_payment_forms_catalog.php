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
         * BEXIA_V5525J6_COMPLETE_SAT_PAYMENT_FORMS
         * Catálogo completo c_FormaPago SAT para CFDI 4.0.
         */

        if (! Schema::hasTable('payment_forms')) {
            return;
        }

        Schema::table('payment_forms', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_forms', 'sat_payment_form_code')) {
                $table->string('sat_payment_form_code', 20)->nullable()->index()->after('code');
            }

            if (! Schema::hasColumn('payment_forms', 'default_payment_method_code')) {
                $table->string('default_payment_method_code', 10)->nullable()->after('sat_payment_form_code');
            }

            if (! Schema::hasColumn('payment_forms', 'default_payment_term_id')) {
                $table->unsignedBigInteger('default_payment_term_id')->nullable()->index()->after('default_payment_method_code');
            }
        });

        $catalog = [
            ['01', 'Efectivo'],
            ['02', 'Cheque nominativo'],
            ['03', 'Transferencia electrónica de fondos'],
            ['04', 'Tarjeta de crédito'],
            ['05', 'Monedero electrónico'],
            ['06', 'Dinero electrónico'],
            ['08', 'Vales de despensa'],
            ['12', 'Dación en pago'],
            ['13', 'Pago por subrogación'],
            ['14', 'Pago por consignación'],
            ['15', 'Condonación'],
            ['17', 'Compensación'],
            ['23', 'Novación'],
            ['24', 'Confusión'],
            ['25', 'Remisión de deuda'],
            ['26', 'Prescripción o caducidad'],
            ['27', 'A satisfacción del acreedor'],
            ['28', 'Tarjeta de débito'],
            ['29', 'Tarjeta de servicios'],
            ['30', 'Aplicación de anticipos'],
            ['31', 'Intermediario pagos'],
            ['99', 'Por definir'],
        ];

        $companyIds = collect();

        if (Schema::hasTable('companies')) {
            $companyIds = $companyIds->merge(DB::table('companies')->pluck('id'));
        }

        if (Schema::hasColumn('payment_forms', 'company_id')) {
            $companyIds = $companyIds->merge(DB::table('payment_forms')->whereNotNull('company_id')->pluck('company_id'));
        }

        $companyIds = $companyIds
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($companyIds->isEmpty()) {
            $companyIds = collect([null]);
        }

        foreach ($companyIds as $companyId) {
            $immediateTermId = $this->ensureImmediatePaymentTerm($companyId);

            foreach ($catalog as $index => [$code, $name]) {
                $existing = $this->findExistingPaymentForm($companyId, $code, $name);

                $defaultMethod = $code === '99' ? 'PPD' : 'PUE';

                $data = [
                    'code' => $code,
                    'name' => $name,
                    'description' => 'Catálogo SAT c_FormaPago CFDI 4.0',
                    'sat_payment_form_code' => $code,
                    'default_payment_method_code' => $defaultMethod,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'is_cash' => $code === '01',
                    'is_credit' => $code === '99',
                    'requires_reference' => in_array($code, ['02', '03', '04', '28', '29', '31'], true),
                    'requires_bank' => in_array($code, ['02', '03', '04', '28', '29'], true),
                    'updated_at' => now(),
                ];

                if ($code !== '99' && $immediateTermId && Schema::hasColumn('payment_forms', 'default_payment_term_id')) {
                    $data['default_payment_term_id'] = $immediateTermId;
                }

                $data = $this->filterColumns('payment_forms', $data);

                if ($existing) {
                    DB::table('payment_forms')
                        ->where('id', $existing->id)
                        ->update($data);
                } else {
                    $insert = $data + [
                        'created_at' => now(),
                    ];

                    if (Schema::hasColumn('payment_forms', 'company_id')) {
                        $insert['company_id'] = $companyId;
                    }

                    DB::table('payment_forms')->insert($this->filterColumns('payment_forms', $insert));
                }
            }
        }
    }

    public function down(): void
    {
        // Data migration: no se eliminan formas de pago para evitar pérdida de configuración.
    }

    private function ensureImmediatePaymentTerm(?int $companyId): ?int
    {
        if (! Schema::hasTable('payment_terms')) {
            return null;
        }

        $query = DB::table('payment_terms')->where('code', 'PAGO_INMEDIATO');

        if (Schema::hasColumn('payment_terms', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $existing = $query->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $data = [
            'code' => 'PAGO_INMEDIATO',
            'name' => 'Pago inmediato',
            'days' => 0,
            'description' => 'Pago inmediato para operaciones de contado / PDV. CFDI: PUE.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('payment_terms', 'company_id')) {
            $data['company_id'] = $companyId;
        }

        return (int) DB::table('payment_terms')->insertGetId($this->filterColumns('payment_terms', $data));
    }

    private function findExistingPaymentForm(?int $companyId, string $code, string $name): ?object
    {
        $query = DB::table('payment_forms');

        if (Schema::hasColumn('payment_forms', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $existing = (clone $query)
            ->where(function ($query) use ($code): void {
                $query
                    ->where('code', $code)
                    ->orWhere('sat_payment_form_code', $code);
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        $normalized = mb_strtolower($name);

        $aliases = [
            '01' => ['efectivo', 'cash'],
            '02' => ['cheque'],
            '03' => ['transferencia', 'spei'],
            '04' => ['tarjeta de crédito', 'tarjeta credito', 'crédito', 'credito'],
            '28' => ['tarjeta de débito', 'tarjeta debito', 'débito', 'debito'],
            '29' => ['tarjeta de servicios'],
            '99' => ['por definir'],
        ];

        foreach ($aliases[$code] ?? [$normalized] as $alias) {
            $found = (clone $query)
                ->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', ['%'.mb_strtolower($alias).'%'])
                ->first();

            if ($found) {
                return $found;
            }
        }

        return null;
    }

    private function filterColumns(string $table, array $data): array
    {
        return collect($data)
            ->filter(fn ($value, $key) => Schema::hasColumn($table, $key))
            ->all();
    }
};
