<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('payroll_fiscal_run_results')
            && ! Schema::hasColumn(
                'payroll_fiscal_run_results',
                'subsidy_caused'
            )
        ) {
            Schema::table(
                'payroll_fiscal_run_results',
                function (Blueprint $table): void {
                    /*
                     * Nullable intencionalmente.
                     *
                     * NULL = resultado histórico que todavía
                     * no tenía esta dimensión fiscal.
                     *
                     * 0 = cálculo actual donde efectivamente
                     * no hubo subsidio causado.
                     */
                    $table
                        ->decimal(
                            'subsidy_caused',
                            18,
                            6
                        )
                        ->nullable()
                        ->after('isr_before_subsidy');
                }
            );
        }

        if (
            Schema::hasTable('payroll_fiscal_employee_months')
            && ! Schema::hasColumn(
                'payroll_fiscal_employee_months',
                'subsidy_caused'
            )
        ) {
            Schema::table(
                'payroll_fiscal_employee_months',
                function (Blueprint $table): void {
                    $table
                        ->decimal(
                            'subsidy_caused',
                            18,
                            6
                        )
                        ->nullable()
                        ->after('isr_before_subsidy');
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('payroll_fiscal_employee_months')
            && Schema::hasColumn(
                'payroll_fiscal_employee_months',
                'subsidy_caused'
            )
        ) {
            Schema::table(
                'payroll_fiscal_employee_months',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'subsidy_caused'
                    );
                }
            );
        }

        if (
            Schema::hasTable('payroll_fiscal_run_results')
            && Schema::hasColumn(
                'payroll_fiscal_run_results',
                'subsidy_caused'
            )
        ) {
            Schema::table(
                'payroll_fiscal_run_results',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'subsidy_caused'
                    );
                }
            );
        }
    }
};
