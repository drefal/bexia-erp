<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounting_accounts', 'sat_grouping_code')) {
                $table->string('sat_grouping_code', 30)->nullable();
            }

            if (! Schema::hasColumn('accounting_accounts', 'account_usage')) {
                $table->string('account_usage', 80)->nullable();
            }

            if (! Schema::hasColumn('accounting_accounts', 'allow_manual_entries')) {
                $table->boolean('allow_manual_entries')->default(true);
            }
        });

        Schema::table('accounting_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounting_accounts', 'sat_grouping_code')) {
                try {
                    $table->index('sat_grouping_code', 'accounting_accounts_sat_grouping_code_idx');
                } catch (Throwable $e) {
                    // Index may already exist.
                }
            }

            if (Schema::hasColumn('accounting_accounts', 'account_usage')) {
                try {
                    $table->index('account_usage', 'accounting_accounts_account_usage_idx');
                } catch (Throwable $e) {
                    // Index may already exist.
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounting_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounting_accounts', 'allow_manual_entries')) {
                $table->dropColumn('allow_manual_entries');
            }

            if (Schema::hasColumn('accounting_accounts', 'account_usage')) {
                $table->dropColumn('account_usage');
            }

            if (Schema::hasColumn('accounting_accounts', 'sat_grouping_code')) {
                $table->dropColumn('sat_grouping_code');
            }
        });
    }
};
