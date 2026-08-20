<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'sales_price_lists',
            function (Blueprint $table): void {
                $table
                    ->decimal(
                        'financing_percent',
                        8,
                        4
                    )
                    ->nullable()
                    ->after(
                        'adjustment_percent'
                    );
            }
        );

        /*
         * V5.83.2
         *
         * Backfill exclusivamente para la calculadora
         * publica Mercado Pago de Grupo L7.
         *
         * adjustment_percent conserva la tasa total
         * historica de credito:
         *
         * financing = total_credito - 2.1900
         */
        $companyIds = DB::table('companies')
            ->whereRaw(
                'LOWER(slug) = ?',
                ['grupol7']
            )
            ->pluck('id')
            ->all();

        if ($companyIds === []) {
            return;
        }

        $plans = DB::table(
            'sales_price_lists'
        )
            ->whereIn(
                'company_id',
                $companyIds
            )
            ->where(
                'payment_provider',
                'mercado_pago'
            )
            ->where(
                'public_calculator',
                true
            )
            ->whereIn(
                'installment_months',
                [3, 6, 9, 12, 18, 24]
            )
            ->whereNotNull(
                'adjustment_percent'
            )
            ->get([
                'id',
                'adjustment_percent',
            ]);

        foreach ($plans as $plan) {
            $financing = round(
                max(
                    0,
                    (float)
                    $plan->adjustment_percent
                    - 2.1900
                ),
                4
            );

            DB::table(
                'sales_price_lists'
            )
                ->where(
                    'id',
                    $plan->id
                )
                ->update([
                    'financing_percent' =>
                        $financing,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table(
            'sales_price_lists',
            function (Blueprint $table): void {
                $table->dropColumn(
                    'financing_percent'
                );
            }
        );
    }
};
