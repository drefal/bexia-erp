<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_incidents')) {
            return;
        }

        Schema::table(
            'employee_incidents',
            function (Blueprint $table): void {
                if (! Schema::hasColumn(
                    'employee_incidents',
                    'incapacity_salary_treatment'
                )) {
                    $table->string(
                        'incapacity_salary_treatment',
                        30
                    )
                        ->nullable()
                        ->after(
                            'incapacity_payroll_effect'
                        );
                }

                if (! Schema::hasColumn(
                    'employee_incidents',
                    'incapacity_taxable_amount'
                )) {
                    $table->decimal(
                        'incapacity_taxable_amount',
                        14,
                        2
                    )
                        ->nullable()
                        ->after(
                            'incapacity_monetary_amount'
                        );
                }

                if (! Schema::hasColumn(
                    'employee_incidents',
                    'incapacity_exempt_amount'
                )) {
                    $table->decimal(
                        'incapacity_exempt_amount',
                        14,
                        2
                    )
                        ->nullable()
                        ->after(
                            'incapacity_taxable_amount'
                        );
                }
            }
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_incidents')) {
            return;
        }

        Schema::table(
            'employee_incidents',
            function (Blueprint $table): void {
                foreach (
                    [
                        'incapacity_exempt_amount',
                        'incapacity_taxable_amount',
                        'incapacity_salary_treatment',
                    ]
                    as $column
                ) {
                    if (
                        Schema::hasColumn(
                            'employee_incidents',
                            $column
                        )
                    ) {
                        $table->dropColumn($column);
                    }
                }
            }
        );
    }
};
