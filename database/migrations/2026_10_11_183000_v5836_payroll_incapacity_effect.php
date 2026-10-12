<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_incidents')) {
            return;
        }

        Schema::table(
            'employee_incidents',
            function (Blueprint $table): void {
                if (! Schema::hasColumn(
                    'employee_incidents',
                    'incapacity_payroll_effect'
                )) {
                    $table->string(
                        'incapacity_payroll_effect',
                        20
                    )
                        ->nullable()
                        ->after(
                            'sat_incapacity_type_code'
                        );
                }
            }
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_incidents')) {
            return;
        }

        Schema::table(
            'employee_incidents',
            function (Blueprint $table): void {
                if (Schema::hasColumn(
                    'employee_incidents',
                    'incapacity_payroll_effect'
                )) {
                    $table->dropColumn(
                        'incapacity_payroll_effect'
                    );
                }
            }
        );
    }
};
