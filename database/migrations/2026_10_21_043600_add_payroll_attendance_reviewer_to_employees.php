<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId(
                'payroll_attendance_reviewer_user_id'
            )->nullable();

            $table->index(
                ['company_id', 'payroll_attendance_reviewer_user_id'],
                'employees_payroll_att_reviewer_company_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(
                'employees_payroll_att_reviewer_company_idx'
            );

            $table->dropColumn(
                'payroll_attendance_reviewer_user_id'
            );
        });
    }
};
