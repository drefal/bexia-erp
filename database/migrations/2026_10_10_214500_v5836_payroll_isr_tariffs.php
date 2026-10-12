<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_isr_tariffs')) {
            Schema::create('payroll_isr_tariffs', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('name', 255);
                $table->unsignedSmallInteger('fiscal_year');
                $table->string('period_type', 30);
                $table->unsignedInteger('period_days')->nullable();
                $table->date('valid_from');
                $table->date('valid_to')->nullable();
                $table->string('source_reference', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index([
                    'fiscal_year',
                    'period_type',
                    'is_active',
                ], 'payroll_isr_tariffs_lookup_idx');
            });
        }

        if (! Schema::hasTable('payroll_isr_tariff_brackets')) {
            Schema::create('payroll_isr_tariff_brackets', function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('payroll_isr_tariff_id')
                    ->constrained('payroll_isr_tariffs')
                    ->cascadeOnDelete();

                $table->unsignedSmallInteger('sort_order');

                $table->decimal('lower_limit', 18, 6);
                $table->decimal('upper_limit', 18, 6)->nullable();
                $table->decimal('fixed_fee', 18, 6);
                $table->decimal('marginal_rate', 10, 6);

                $table->timestamps();

                $table->unique(
                    [
                        'payroll_isr_tariff_id',
                        'sort_order',
                    ],
                    'payroll_isr_bracket_order_uq'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_isr_tariff_brackets');
        Schema::dropIfExists('payroll_isr_tariffs');
    }
};
