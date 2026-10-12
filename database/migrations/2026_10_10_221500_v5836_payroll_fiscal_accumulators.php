<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('payroll_runs')
            && ! Schema::hasColumn(
                'payroll_runs',
                'run_type'
            )
        ) {
            Schema::table(
                'payroll_runs',
                function (Blueprint $table): void {
                    $table
                        ->string('run_type', 30)
                        ->default('ordinary')
                        ->index()
                        ->after('calculation_mode_snapshot');
                }
            );
        }

        if (! Schema::hasTable('payroll_fiscal_run_results')) {
            Schema::create(
                'payroll_fiscal_run_results',
                function (Blueprint $table): void {
                    $table->id();

                    $table
                        ->foreignId('company_id')
                        ->constrained('companies');

                    $table
                        ->foreignId('payroll_run_id')
                        ->constrained('payroll_runs')
                        ->cascadeOnDelete();

                    $table
                        ->foreignId('employee_id')
                        ->constrained('employees');

                    $table
                        ->foreignId('payroll_run_line_id')
                        ->nullable()
                        ->constrained('payroll_run_lines')
                        ->nullOnDelete();

                    $table->string('run_type', 30);

                    $table->date('payment_date');

                    $table
                        ->unsignedSmallInteger('fiscal_year');

                    $table
                        ->unsignedTinyInteger('fiscal_month');

                    $table->decimal(
                        'taxable_income',
                        18,
                        6
                    )->default(0);

                    $table->decimal(
                        'exempt_income',
                        18,
                        6
                    )->default(0);

                    $table->decimal(
                        'isr_before_subsidy',
                        18,
                        6
                    )->default(0);

                    $table->decimal(
                        'subsidy_applied',
                        18,
                        6
                    )->default(0);

                    $table->decimal(
                        'isr_withheld',
                        18,
                        6
                    )->default(0);

                    $table->string(
                        'isr_tariff_code',
                        80
                    )->nullable();

                    $table->string(
                        'subsidy_rule_code',
                        80
                    )->nullable();

                    $table->jsonb(
                        'calculation_snapshot'
                    )->nullable();

                    $table->timestamps();

                    $table->unique(
                        [
                            'payroll_run_id',
                            'employee_id',
                        ],
                        'payroll_fiscal_run_employee_uq'
                    );

                    $table->index(
                        [
                            'company_id',
                            'employee_id',
                            'fiscal_year',
                            'fiscal_month',
                        ],
                        'payroll_fiscal_result_month_idx'
                    );
                }
            );
        }

        if (! Schema::hasTable('payroll_fiscal_employee_months')) {
            Schema::create(
                'payroll_fiscal_employee_months',
                function (Blueprint $table): void {
                    $table->id();

                    $table
                        ->foreignId('company_id')
                        ->constrained('companies');

                    $table
                        ->foreignId('employee_id')
                        ->constrained('employees');

                    $table
                        ->unsignedSmallInteger('fiscal_year');

                    $table
                        ->unsignedTinyInteger('fiscal_month');

                    $table->decimal(
                        'taxable_income',
                        18,
                        6
                    )->default(0);

                    $table->decimal(
                        'exempt_income',
                        18,
                        6
                    )->default(0);

                    $table->decimal(
                        'isr_before_subsidy',
                        18,
                        6
                    )->default(0);

                    $table->decimal(
                        'subsidy_applied',
                        18,
                        6
                    )->default(0);

                    $table->decimal(
                        'isr_withheld',
                        18,
                        6
                    )->default(0);

                    $table
                        ->unsignedInteger(
                            'ordinary_runs_count'
                        )
                        ->default(0);

                    $table
                        ->unsignedInteger(
                            'extraordinary_runs_count'
                        )
                        ->default(0);

                    $table
                        ->foreignId('last_payroll_run_id')
                        ->nullable()
                        ->constrained('payroll_runs')
                        ->nullOnDelete();

                    $table
                        ->timestamp('calculated_at')
                        ->nullable();

                    $table->timestamps();

                    $table->unique(
                        [
                            'company_id',
                            'employee_id',
                            'fiscal_year',
                            'fiscal_month',
                        ],
                        'payroll_fiscal_employee_month_uq'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'payroll_fiscal_employee_months'
        );

        Schema::dropIfExists(
            'payroll_fiscal_run_results'
        );

        if (
            Schema::hasTable('payroll_runs')
            && Schema::hasColumn(
                'payroll_runs',
                'run_type'
            )
        ) {
            Schema::table(
                'payroll_runs',
                function (Blueprint $table): void {
                    $table->dropColumn('run_type');
                }
            );
        }
    }
};
