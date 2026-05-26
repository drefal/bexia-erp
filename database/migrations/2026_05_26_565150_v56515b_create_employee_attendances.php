<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_attendances')) {
            Schema::create('employee_attendances', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('hr_work_schedule_id')->nullable()->constrained('hr_work_schedules')->nullOnDelete();

                $table->date('attendance_date')->index();
                $table->string('status', 40)->default('incomplete')->index();

                $table->dateTime('expected_start_at')->nullable();
                $table->dateTime('expected_end_at')->nullable();
                $table->dateTime('clock_in_at')->nullable()->index();
                $table->dateTime('clock_out_at')->nullable()->index();

                $table->unsignedSmallInteger('break_minutes')->default(0);
                $table->decimal('expected_hours', 8, 2)->default(0);
                $table->unsignedInteger('worked_minutes')->default(0);
                $table->decimal('worked_hours', 8, 2)->default(0);

                $table->unsignedInteger('late_minutes')->default(0);
                $table->unsignedInteger('early_leave_minutes')->default(0);
                $table->unsignedInteger('overtime_minutes')->default(0);

                $table->string('source', 40)->default('manual')->index();
                $table->text('notes')->nullable();

                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->unique(['employee_id', 'attendance_date'], 'employee_attendance_unique_day');
                $table->index(['company_id', 'attendance_date']);
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'employee_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendances');
    }
};
