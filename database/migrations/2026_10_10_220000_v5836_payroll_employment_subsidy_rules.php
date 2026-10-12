<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_employment_subsidy_rules')) {
            Schema::create(
                'payroll_employment_subsidy_rules',
                function (Blueprint $table): void {
                    $table->id();

                    $table->string('code', 80)->unique();
                    $table->string('name', 255);

                    $table->unsignedSmallInteger('fiscal_year');

                    $table->date('valid_from');
                    $table->date('valid_to');

                    $table->decimal(
                        'monthly_income_limit',
                        18,
                        6
                    );

                    $table->decimal(
                        'uma_monthly',
                        18,
                        6
                    );

                    $table->decimal(
                        'subsidy_percentage',
                        10,
                        6
                    );

                    $table->decimal(
                        'month_days_factor',
                        10,
                        6
                    )->default(30.4);

                    $table->string(
                        'source_reference',
                        255
                    )->nullable();

                    $table->boolean('is_active')
                        ->default(true);

                    $table->timestamps();

                    $table->index(
                        [
                            'fiscal_year',
                            'valid_from',
                            'valid_to',
                            'is_active',
                        ],
                        'payroll_subsidy_rule_lookup_idx'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'payroll_employment_subsidy_rules'
        );
    }
};
