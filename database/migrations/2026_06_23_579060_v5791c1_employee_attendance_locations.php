<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_attendance_location_assignments')) {
            Schema::create('employee_attendance_location_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('hr_attendance_location_id')->constrained('hr_attendance_locations')->cascadeOnDelete();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['employee_id', 'hr_attendance_location_id'], 'emp_att_loc_unique');
                $table->index(['company_id', 'employee_id'], 'emp_att_loc_company_employee_idx');
                $table->index(['company_id', 'hr_attendance_location_id'], 'emp_att_loc_company_location_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_location_assignments');
    }
};
