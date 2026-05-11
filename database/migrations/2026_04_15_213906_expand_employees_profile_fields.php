<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'avatar_path')) {
                $table->string('avatar_path')->nullable()->after('company_id');
            }

            if (! Schema::hasColumn('employees', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('avatar_path');
            }

            if (! Schema::hasColumn('employees', 'manager_employee_id')) {
                $table->unsignedBigInteger('manager_employee_id')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('employees', 'coach_employee_id')) {
                $table->unsignedBigInteger('coach_employee_id')->nullable()->after('manager_employee_id');
            }

            if (! Schema::hasColumn('employees', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('coach_employee_id');
            }

            if (! Schema::hasColumn('employees', 'department')) {
                $table->string('department')->nullable()->after('position');
            }

            if (! Schema::hasColumn('employees', 'work_mobile')) {
                $table->string('work_mobile')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('employees', 'ssn')) {
                $table->string('ssn')->nullable()->after('department');
            }

            if (! Schema::hasColumn('employees', 'curp')) {
                $table->string('curp')->nullable()->after('ssn');
            }

            if (! Schema::hasColumn('employees', 'rfc')) {
                $table->string('rfc')->nullable()->after('curp');
            }

            if (! Schema::hasColumn('employees', 'employee_type')) {
                $table->string('employee_type')->nullable()->after('rfc');
            }

            if (! Schema::hasColumn('employees', 'work_address')) {
                $table->text('work_address')->nullable()->after('employee_type');
            }

            if (! Schema::hasColumn('employees', 'work_timezone')) {
                $table->string('work_timezone')->nullable()->after('work_address');
            }

            if (! Schema::hasColumn('employees', 'working_schedule')) {
                $table->string('working_schedule')->nullable()->after('work_timezone');
            }

            if (! Schema::hasColumn('employees', 'flexible_hours')) {
                $table->boolean('flexible_hours')->default(false)->after('working_schedule');
            }

            if (! Schema::hasColumn('employees', 'pin_code')) {
                $table->string('pin_code')->nullable()->after('flexible_hours');
            }

            if (! Schema::hasColumn('employees', 'badge_id')) {
                $table->string('badge_id')->nullable()->after('pin_code');
            }

            if (! Schema::hasColumn('employees', 'hourly_cost')) {
                $table->decimal('hourly_cost', 12, 2)->nullable()->after('badge_id');
            }

            if (! Schema::hasColumn('employees', 'fleet_card')) {
                $table->string('fleet_card')->nullable()->after('hourly_cost');
            }

            if (! Schema::hasColumn('employees', 'private_address')) {
                $table->text('private_address')->nullable()->after('fleet_card');
            }

            if (! Schema::hasColumn('employees', 'private_email')) {
                $table->string('private_email')->nullable()->after('private_address');
            }

            if (! Schema::hasColumn('employees', 'private_phone')) {
                $table->string('private_phone')->nullable()->after('private_email');
            }

            if (! Schema::hasColumn('employees', 'bank_account')) {
                $table->string('bank_account')->nullable()->after('private_phone');
            }

            if (! Schema::hasColumn('employees', 'language')) {
                $table->string('language')->nullable()->after('bank_account');
            }

            if (! Schema::hasColumn('employees', 'distance_home_work')) {
                $table->decimal('distance_home_work', 10, 2)->nullable()->after('language');
            }

            if (! Schema::hasColumn('employees', 'marital_status')) {
                $table->string('marital_status')->nullable()->after('distance_home_work');
            }

            if (! Schema::hasColumn('employees', 'dependent_children')) {
                $table->unsignedInteger('dependent_children')->default(0)->after('marital_status');
            }

            if (! Schema::hasColumn('employees', 'emergency_contact_name')) {
                $table->string('emergency_contact_name')->nullable()->after('dependent_children');
            }

            if (! Schema::hasColumn('employees', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            }

            if (! Schema::hasColumn('employees', 'nationality')) {
                $table->string('nationality')->nullable()->after('emergency_contact_phone');
            }

            if (! Schema::hasColumn('employees', 'identification_number')) {
                $table->string('identification_number')->nullable()->after('nationality');
            }

            if (! Schema::hasColumn('employees', 'passport_number')) {
                $table->string('passport_number')->nullable()->after('identification_number');
            }

            if (! Schema::hasColumn('employees', 'gender')) {
                $table->string('gender')->nullable()->after('passport_number');
            }

            if (! Schema::hasColumn('employees', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('gender');
            }

            if (! Schema::hasColumn('employees', 'birth_place')) {
                $table->string('birth_place')->nullable()->after('birth_date');
            }

            if (! Schema::hasColumn('employees', 'birth_country')) {
                $table->string('birth_country')->nullable()->after('birth_place');
            }

            if (! Schema::hasColumn('employees', 'certificate_level')) {
                $table->string('certificate_level')->nullable()->after('birth_country');
            }

            if (! Schema::hasColumn('employees', 'study_field')) {
                $table->string('study_field')->nullable()->after('certificate_level');
            }

            if (! Schema::hasColumn('employees', 'school')) {
                $table->string('school')->nullable()->after('study_field');
            }

            if (! Schema::hasColumn('employees', 'visa_number')) {
                $table->string('visa_number')->nullable()->after('school');
            }

            if (! Schema::hasColumn('employees', 'work_permit_number')) {
                $table->string('work_permit_number')->nullable()->after('visa_number');
            }

            if (! Schema::hasColumn('employees', 'visa_expiration_date')) {
                $table->date('visa_expiration_date')->nullable()->after('work_permit_number');
            }

            if (! Schema::hasColumn('employees', 'work_permit_expiration_date')) {
                $table->date('work_permit_expiration_date')->nullable()->after('visa_expiration_date');
            }

            if (! Schema::hasColumn('employees', 'work_permit_file')) {
                $table->string('work_permit_file')->nullable()->after('work_permit_expiration_date');
            }
        });
    }

    public function down(): void
    {
        // No-op para evitar borrar datos por accidente.
    }
};
