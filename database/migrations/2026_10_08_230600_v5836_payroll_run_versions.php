<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_run_versions')) {
            Schema::create('payroll_run_versions', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->cascadeOnDelete();

                $table->foreignId('payroll_run_id')
                    ->constrained('payroll_runs')
                    ->cascadeOnDelete();

                $table->unsignedInteger('version_number');

                $table->foreignId('payroll_group_id')
                    ->nullable()
                    ->constrained('payroll_groups')
                    ->nullOnDelete();

                $table->string('snapshot_reason', 40)->default('calculation');

                $table->string('name');
                $table->string('period_type', 40)->nullable();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->date('payment_date')->nullable();

                $table->string('status', 40)->nullable();
                $table->string('currency', 3)->default('MXN');

                $table->unsignedInteger('employees_count')->default(0);

                $table->decimal('base_total', 14, 2)->default(0);
                $table->decimal('overtime_total', 14, 2)->default(0);
                $table->decimal('perceptions_total', 14, 2)->default(0);
                $table->decimal('gross_total', 14, 2)->default(0);
                $table->decimal('deductions_total', 14, 2)->default(0);
                $table->decimal('net_total', 14, 2)->default(0);

                $table->json('summary')->nullable();

                $table->foreignId('calculated_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('calculated_at')->nullable();

                $table->foreignId('snapshot_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('snapshot_at');

                $table->timestamps();

                $table->unique(
                    ['payroll_run_id', 'version_number'],
                    'payroll_run_versions_run_version_unique'
                );

                $table->index(
                    ['company_id', 'payroll_run_id'],
                    'payroll_run_versions_company_run_index'
                );
            });
        }

        if (! Schema::hasTable('payroll_run_version_lines')) {
            Schema::create('payroll_run_version_lines', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->cascadeOnDelete();

                $table->foreignId('payroll_run_version_id')
                    ->constrained('payroll_run_versions')
                    ->cascadeOnDelete();

                /*
                 * Referencia informativa a la línea original.
                 * No FK porque payroll_run_lines se reemplaza al recalcular.
                 */
                $table->unsignedBigInteger('original_payroll_run_line_id')
                    ->nullable();

                $table->foreignId('employee_id')
                    ->nullable()
                    ->constrained('employees')
                    ->nullOnDelete();

                $table->foreignId('employee_contract_id')
                    ->nullable()
                    ->constrained('employee_contracts')
                    ->nullOnDelete();

                $table->string('employee_name_snapshot');
                $table->string('contract_number_snapshot')->nullable();

                $table->string('status', 40)->nullable();

                $table->decimal('base_salary', 14, 2)->default(0);
                $table->string('salary_type', 40)->nullable();
                $table->decimal('daily_salary', 14, 2)->default(0);
                $table->decimal('hourly_rate', 14, 4)->default(0);

                $table->decimal('period_days', 8, 2)->default(0);
                $table->decimal('payable_days', 8, 2)->default(0);

                $table->unsignedInteger('attendance_records')->default(0);
                $table->unsignedInteger('worked_minutes')->default(0);
                $table->decimal('worked_hours', 10, 2)->default(0);

                $table->unsignedInteger('late_minutes')->default(0);
                $table->unsignedInteger('early_leave_minutes')->default(0);
                $table->unsignedInteger('overtime_minutes')->default(0);
                $table->decimal('overtime_hours', 10, 2)->default(0);

                $table->decimal('absence_days', 8, 2)->default(0);
                $table->decimal('rest_day_worked_days', 8, 2)->default(0);

                $table->unsignedInteger('approved_incidents_count')->default(0);
                $table->decimal('approved_incident_deduction_days', 8, 2)->default(0);
                $table->unsignedInteger('approved_incident_deduction_minutes')->default(0);

                $table->decimal('base_amount', 14, 2)->default(0);
                $table->decimal('overtime_amount', 14, 2)->default(0);
                $table->decimal('incident_perceptions', 14, 2)->default(0);
                $table->decimal('incident_deductions', 14, 2)->default(0);

                $table->decimal('gross_amount', 14, 2)->default(0);
                $table->decimal('deductions_amount', 14, 2)->default(0);
                $table->decimal('net_amount', 14, 2)->default(0);

                $table->json('details')->nullable();
                $table->json('concepts_snapshot')->nullable();
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(
                    ['payroll_run_version_id', 'employee_id'],
                    'payroll_run_version_lines_version_employee_index'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_version_lines');
        Schema::dropIfExists('payroll_run_versions');
    }
};
