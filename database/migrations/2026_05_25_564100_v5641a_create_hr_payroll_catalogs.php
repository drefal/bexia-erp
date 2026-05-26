<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_departments')) {
            Schema::create('hr_departments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('hr_departments')->nullOnDelete();
                $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'name']);
                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('hr_job_positions')) {
            Schema::create('hr_job_positions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('level')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'name']);
                $table->index(['company_id', 'department_id']);
                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('hr_document_types')) {
            Schema::create('hr_document_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->boolean('requires_expiration_date')->default(false);
                $table->boolean('is_required_by_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'name']);
                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('hr_incident_types')) {
            Schema::create('hr_incident_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('effect')->default('informational');
                $table->boolean('requires_approval')->default(true);
                $table->boolean('affects_payroll')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'name']);
                $table->index(['company_id', 'effect']);
                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('hr_work_schedules')) {
            Schema::create('hr_work_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('schedule_type')->default('fixed');
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->json('work_days')->nullable();
                $table->decimal('hours_per_day', 8, 2)->nullable();
                $table->decimal('hours_per_week', 8, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'name']);
                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('payroll_periodicities')) {
            Schema::create('payroll_periodicities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('name');
                $table->string('sat_code')->nullable();
                $table->integer('days')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'name']);
                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('payroll_employer_registrations')) {
            Schema::create('payroll_employer_registrations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('name');
                $table->string('registration_number')->nullable();
                $table->string('risk_class')->nullable();
                $table->string('state')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'name']);
                $table->index(['company_id', 'registration_number']);
                $table->index(['company_id', 'is_active']);
            });
        }

        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'hr_department_id')) {
                $table->foreignId('hr_department_id')->nullable()->after('department')->constrained('hr_departments')->nullOnDelete();
            }

            if (! Schema::hasColumn('employees', 'hr_job_position_id')) {
                $table->foreignId('hr_job_position_id')->nullable()->after('position')->constrained('hr_job_positions')->nullOnDelete();
            }

            if (! Schema::hasColumn('employees', 'hr_work_schedule_id')) {
                $table->foreignId('hr_work_schedule_id')->nullable()->after('working_schedule')->constrained('hr_work_schedules')->nullOnDelete();
            }

            if (! Schema::hasColumn('employees', 'payroll_periodicity_id')) {
                $table->foreignId('payroll_periodicity_id')->nullable()->after('bank_account')->constrained('payroll_periodicities')->nullOnDelete();
            }

            if (! Schema::hasColumn('employees', 'payroll_employer_registration_id')) {
                $table->foreignId('payroll_employer_registration_id')->nullable()->after('payroll_periodicity_id')->constrained('payroll_employer_registrations')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            foreach ([
                'payroll_employer_registration_id',
                'payroll_periodicity_id',
                'hr_work_schedule_id',
                'hr_job_position_id',
                'hr_department_id',
            ] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::dropIfExists('payroll_employer_registrations');
        Schema::dropIfExists('payroll_periodicities');
        Schema::dropIfExists('hr_work_schedules');
        Schema::dropIfExists('hr_incident_types');
        Schema::dropIfExists('hr_document_types');
        Schema::dropIfExists('hr_job_positions');
        Schema::dropIfExists('hr_departments');
    }
};
