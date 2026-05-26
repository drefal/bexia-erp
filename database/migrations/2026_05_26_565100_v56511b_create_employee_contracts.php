<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_contracts')) {
            Schema::create('employee_contracts', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

                $table->string('contract_number')->nullable()->index();
                $table->string('contract_type', 60)->default('indefinite')->index();
                $table->string('status', 30)->default('draft')->index();

                $table->date('start_date')->index();
                $table->date('end_date')->nullable()->index();
                $table->date('signed_at')->nullable();
                $table->date('probation_end_date')->nullable();

                $table->foreignId('hr_department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
                $table->foreignId('hr_job_position_id')->nullable()->constrained('hr_job_positions')->nullOnDelete();
                $table->foreignId('hr_work_schedule_id')->nullable()->constrained('hr_work_schedules')->nullOnDelete();
                $table->foreignId('payroll_employer_registration_id')->nullable()->constrained('payroll_employer_registrations')->nullOnDelete();
                $table->foreignId('payroll_periodicity_id')->nullable()->constrained('payroll_periodicities')->nullOnDelete();

                $table->decimal('base_salary', 14, 2)->nullable();
                $table->string('salary_type', 40)->nullable();
                $table->string('currency', 3)->default('MXN');
                $table->decimal('hours_per_week', 8, 2)->nullable();

                $table->boolean('is_current')->default(false)->index();

                $table->string('file_path')->nullable();
                $table->string('file_original_name')->nullable();

                $table->text('notes')->nullable();

                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->index(['company_id', 'employee_id']);
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'contract_type']);
                $table->index(['company_id', 'end_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};
