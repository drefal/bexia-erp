<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_incidents') && ! Schema::hasColumn('employee_incidents', 'employee_attendance_id')) {
            Schema::table('employee_incidents', function (Blueprint $table): void {
                $table->foreignId('employee_attendance_id')
                    ->nullable()
                    ->after('employee_id')
                    ->constrained('employee_attendances')
                    ->nullOnDelete();

                $table->unique(
                    ['employee_attendance_id', 'hr_incident_type_id'],
                    'employee_incident_attendance_type_unique'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_incidents') && Schema::hasColumn('employee_incidents', 'employee_attendance_id')) {
            Schema::table('employee_incidents', function (Blueprint $table): void {
                $table->dropUnique('employee_incident_attendance_type_unique');
                $table->dropConstrainedForeignId('employee_attendance_id');
            });
        }
    }
};
