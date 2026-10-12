<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('payroll_concepts')
            && ! Schema::hasColumn(
                'payroll_concepts',
                'reduces_taxable_base'
            )
        ) {
            Schema::table(
                'payroll_concepts',
                function (Blueprint $table): void {
                    $table
                        ->boolean('reduces_taxable_base')
                        ->default(false);
                }
            );
        }

        if (
            Schema::hasTable('payroll_run_line_concepts')
            && ! Schema::hasColumn(
                'payroll_run_line_concepts',
                'reduces_taxable_base'
            )
        ) {
            /*
             * Nullable intencionalmente:
             * null = histórico anterior al snapshot fiscal.
             * true/false = decisión congelada al calcular.
             */
            Schema::table(
                'payroll_run_line_concepts',
                function (Blueprint $table): void {
                    $table
                        ->boolean('reduces_taxable_base')
                        ->nullable();
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('payroll_run_line_concepts')
            && Schema::hasColumn(
                'payroll_run_line_concepts',
                'reduces_taxable_base'
            )
        ) {
            Schema::table(
                'payroll_run_line_concepts',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'reduces_taxable_base'
                    );
                }
            );
        }

        if (
            Schema::hasTable('payroll_concepts')
            && Schema::hasColumn(
                'payroll_concepts',
                'reduces_taxable_base'
            )
        ) {
            Schema::table(
                'payroll_concepts',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'reduces_taxable_base'
                    );
                }
            );
        }
    }
};
