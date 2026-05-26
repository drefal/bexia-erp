<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_payroll_deductions')) {
            Schema::create('employee_payroll_deductions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('payroll_concept_id')->nullable()->constrained('payroll_concepts')->nullOnDelete();

                $table->string('type', 40)->default('loan')->index();
                $table->string('code', 80);
                $table->string('name');
                $table->decimal('original_amount', 14, 2)->default(0);
                $table->decimal('outstanding_amount', 14, 2)->default(0);
                $table->decimal('period_amount', 14, 2)->default(0);

                $table->date('start_date')->nullable()->index();
                $table->date('end_date')->nullable()->index();
                $table->unsignedInteger('max_periods')->nullable();
                $table->unsignedInteger('applied_periods')->default(0);

                $table->string('status', 40)->default('active')->index();
                $table->text('notes')->nullable();

                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->index(['company_id', 'employee_id']);
                $table->index(['company_id', 'status']);
                $table->index(['employee_id', 'status']);
                $table->index(['type', 'status']);
            });
        }

        if (! Schema::hasTable('employee_payroll_deduction_applications')) {
            Schema::create('employee_payroll_deduction_applications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_payroll_deduction_id')->constrained('employee_payroll_deductions')->cascadeOnDelete();
                $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
                $table->foreignId('payroll_run_line_id')->constrained('payroll_run_lines')->cascadeOnDelete();
                $table->foreignId('payroll_run_line_concept_id')->nullable()->constrained('payroll_run_line_concepts')->nullOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

                $table->decimal('amount', 14, 2)->default(0);
                $table->decimal('balance_before', 14, 2)->default(0);
                $table->decimal('balance_after', 14, 2)->default(0);
                $table->timestamp('applied_at')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->unique(['employee_payroll_deduction_id', 'payroll_run_id'], 'employee_deduction_run_unique');
                $table->index(['company_id', 'payroll_run_id']);
                $table->index(['employee_id', 'payroll_run_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_deduction_applications');
        Schema::dropIfExists('employee_payroll_deductions');
    }
};
