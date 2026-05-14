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
         * BEXIA_V5525J2_PAYMENT_FORMS_SAT_DEFAULTS
         */

        if (Schema::hasTable('payment_forms')) {
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
        }

        if (! Schema::hasTable('payment_terms')) {
            return;
        }

        $companyIds = collect();

        if (Schema::hasTable('companies')) {
            $companyIds = $companyIds->merge(DB::table('companies')->pluck('id'));
        }

        if (Schema::hasTable('payment_forms') && Schema::hasColumn('payment_forms', 'company_id')) {
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
            $query = DB::table('payment_terms')
                ->where('code', 'PAGO_INMEDIATO');

            if (Schema::hasColumn('payment_terms', 'company_id')) {
                $query->where('company_id', $companyId);
            }

            $term = $query->first();

            if (! $term) {
                $insert = [
                    'code' => 'PAGO_INMEDIATO',
                    'name' => 'Pago inmediato',
                    'days' => 0,
                    'description' => 'Pago inmediato para operaciones de contado / PDV. CFDI: PUE.',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('payment_terms', 'company_id')) {
                    $insert['company_id'] = $companyId;
                }

                DB::table('payment_terms')->insert($insert);
            }
        }

        if (! Schema::hasTable('payment_forms')) {
            return;
        }

        $mapping = [
            '01' => ['sat' => '01', 'method' => 'PUE'],
            '02' => ['sat' => '02', 'method' => 'PUE'],
            '03' => ['sat' => '03', 'method' => 'PUE'],
            '04' => ['sat' => '04', 'method' => 'PUE'],
            '28' => ['sat' => '28', 'method' => 'PUE'],
            '99' => ['sat' => '99', 'method' => 'PPD'],
        ];

        foreach (DB::table('payment_forms')->orderBy('id')->get() as $form) {
            $code = trim((string) ($form->code ?? ''));

            if ($code === '') {
                continue;
            }

            $sat = $mapping[$code]['sat'] ?? $code;
            $method = $mapping[$code]['method'] ?? 'PUE';

            $termQuery = DB::table('payment_terms')->where('code', 'PAGO_INMEDIATO');

            if (Schema::hasColumn('payment_terms', 'company_id')) {
                $termQuery->where(function ($query) use ($form): void {
                    $query
                        ->where('company_id', $form->company_id ?? null)
                        ->orWhereNull('company_id');
                });
            }

            $termId = $termQuery->orderByRaw('company_id nulls last')->value('id');

            $updates = [
                'sat_payment_form_code' => $form->sat_payment_form_code ?: $sat,
                'default_payment_method_code' => $form->default_payment_method_code ?: $method,
                'updated_at' => now(),
            ];

            if ($termId && Schema::hasColumn('payment_forms', 'default_payment_term_id')) {
                $updates['default_payment_term_id'] = $form->default_payment_term_id ?: $termId;
            }

            DB::table('payment_forms')
                ->where('id', $form->id)
                ->update($updates);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_forms')) {
            return;
        }

        Schema::table('payment_forms', function (Blueprint $table) {
            if (Schema::hasColumn('payment_forms', 'default_payment_term_id')) {
                $table->dropColumn('default_payment_term_id');
            }

            if (Schema::hasColumn('payment_forms', 'default_payment_method_code')) {
                $table->dropColumn('default_payment_method_code');
            }

            if (Schema::hasColumn('payment_forms', 'sat_payment_form_code')) {
                $table->dropColumn('sat_payment_form_code');
            }
        });
    }
};
