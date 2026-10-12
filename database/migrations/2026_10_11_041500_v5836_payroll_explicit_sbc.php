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
                'social_security_base_salary'
            )
        ) {
            Schema::table(
                'employee_contracts',
                function (Blueprint $table): void {
                    /*
                     * SBC diario real usado para cuotas y
                     * aportaciones de seguridad social.
                     *
                     * No se deriva automáticamente del salario
                     * diario ni del SDI.
                     */
                    $table
                        ->decimal(
                            'social_security_base_salary',
                            14,
                            4
                        )
                        ->nullable()
                        ->after(
                            'integrated_daily_salary'
                        );
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
                'social_security_base_salary'
            )
        ) {
            Schema::table(
                'employee_contracts',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'social_security_base_salary'
                    );
                }
            );
        }
    }
};
