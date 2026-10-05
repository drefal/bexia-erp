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
            Schema::hasTable('treasury_accounts')
            && ! Schema::hasColumn(
                'treasury_accounts',
                'allow_negative_balance'
            )
        ) {
            Schema::table('treasury_accounts', function (Blueprint $table): void {
                $table->boolean('allow_negative_balance')
                    ->default(false)
                    ->after('requires_approval');
            });
        }

        /*
         * Configuración inicial:
         * solamente la caja general/concentradora de CEDIS GRUPO L7.
         *
         * El resto conserva protección de saldo.
         */
        if (
            Schema::hasTable('treasury_accounts')
            && Schema::hasTable('companies')
        ) {
            $companyId = DB::table('companies')
                ->where('name', 'CEDIS GRUPO L7')
                ->value('id');

            if ($companyId) {
                DB::table('treasury_accounts')
                    ->where('company_id', $companyId)
                    ->where('cash_scope', 'general_cash')
                    ->where('name', 'Caja general empresa GRUPOL7')
                    ->update([
                        'allow_negative_balance' => true,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('treasury_accounts')
            && Schema::hasColumn(
                'treasury_accounts',
                'allow_negative_balance'
            )
        ) {
            Schema::table('treasury_accounts', function (Blueprint $table): void {
                $table->dropColumn('allow_negative_balance');
            });
        }
    }
};
