<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('employee_contracts')
            && ! Schema::hasColumn(
                'employee_contracts',
                'payment_scheme'
            )
        ) {
            Schema::table(
                'employee_contracts',
                function (Blueprint $table): void {
                    $table
                        ->string('payment_scheme', 30)
                        ->default('fixed')
                        ->after('salary_type');
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('employee_contracts')
            && Schema::hasColumn(
                'employee_contracts',
                'payment_scheme'
            )
        ) {
            Schema::table(
                'employee_contracts',
                function (Blueprint $table): void {
                    $table->dropColumn('payment_scheme');
                }
            );
        }
    }
};
