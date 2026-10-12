<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_contracts', 'sat_work_state_code')) {
                $table
                    ->string('sat_work_state_code', 3)
                    ->nullable()
                    ->after('sat_risk_position_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table): void {
            if (Schema::hasColumn('employee_contracts', 'sat_work_state_code')) {
                $table->dropColumn('sat_work_state_code');
            }
        });
    }
};
