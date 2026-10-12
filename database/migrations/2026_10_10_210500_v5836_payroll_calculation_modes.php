<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('payroll_groups')
            && ! Schema::hasColumn(
                'payroll_groups',
                'calculation_mode'
            )
        ) {
            Schema::table(
                'payroll_groups',
                function (Blueprint $table): void {
                    $table
                        ->string('calculation_mode', 30)
                        ->default('operational')
                        ->index()
                        ->after('payroll_periodicity_id');
                }
            );
        }

        if (
            Schema::hasTable('payroll_runs')
            && ! Schema::hasColumn(
                'payroll_runs',
                'calculation_mode_snapshot'
            )
        ) {
            Schema::table(
                'payroll_runs',
                function (Blueprint $table): void {
                    /*
                     * Se guarda como snapshot para que cambiar
                     * posteriormente el grupo no altere una corrida
                     * histórica.
                     *
                     * Corridas existentes quedan operational.
                     */
                    $table
                        ->string(
                            'calculation_mode_snapshot',
                            30
                        )
                        ->default('operational')
                        ->index()
                        ->after('payroll_group_id');
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('payroll_runs')
            && Schema::hasColumn(
                'payroll_runs',
                'calculation_mode_snapshot'
            )
        ) {
            Schema::table(
                'payroll_runs',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'calculation_mode_snapshot'
                    );
                }
            );
        }

        if (
            Schema::hasTable('payroll_groups')
            && Schema::hasColumn(
                'payroll_groups',
                'calculation_mode'
            )
        ) {
            Schema::table(
                'payroll_groups',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'calculation_mode'
                    );
                }
            );
        }
    }
};
