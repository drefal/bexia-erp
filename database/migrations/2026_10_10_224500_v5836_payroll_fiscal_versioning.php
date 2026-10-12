<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_run_versions')) {
            Schema::table(
                'payroll_run_versions',
                function (Blueprint $table): void {
                    if (! Schema::hasColumn(
                        'payroll_run_versions',
                        'calculation_mode_snapshot'
                    )) {
                        $table
                            ->string(
                                'calculation_mode_snapshot',
                                30
                            )
                            ->nullable();
                    }

                    if (! Schema::hasColumn(
                        'payroll_run_versions',
                        'run_type'
                    )) {
                        $table
                            ->string('run_type', 30)
                            ->nullable();
                    }
                }
            );
        }

        if (Schema::hasTable('payroll_fiscal_run_results')) {
            Schema::table(
                'payroll_fiscal_run_results',
                function (Blueprint $table): void {
                    if (! Schema::hasColumn(
                        'payroll_fiscal_run_results',
                        'payroll_run_version_id'
                    )) {
                        $table
                            ->foreignId(
                                'payroll_run_version_id'
                            )
                            ->nullable()
                            ->constrained(
                                'payroll_run_versions'
                            )
                            ->nullOnDelete();
                    }

                    if (! Schema::hasColumn(
                        'payroll_fiscal_run_results',
                        'calculation_mode_snapshot'
                    )) {
                        $table
                            ->string(
                                'calculation_mode_snapshot',
                                30
                            )
                            ->nullable();
                    }
                }
            );

            Schema::table(
                'payroll_fiscal_run_results',
                function (Blueprint $table): void {
                    $table->dropUnique(
                        'payroll_fiscal_run_employee_uq'
                    );

                    $table->unique(
                        [
                            'payroll_run_version_id',
                            'employee_id',
                        ],
                        'payroll_fiscal_version_employee_uq'
                    );

                    $table->index(
                        [
                            'payroll_run_id',
                            'employee_id',
                        ],
                        'payroll_fiscal_run_employee_idx'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(
            'payroll_fiscal_run_results'
        )) {
            Schema::table(
                'payroll_fiscal_run_results',
                function (Blueprint $table): void {
                    $table->dropUnique(
                        'payroll_fiscal_version_employee_uq'
                    );

                    $table->dropIndex(
                        'payroll_fiscal_run_employee_idx'
                    );
                }
            );

            Schema::table(
                'payroll_fiscal_run_results',
                function (Blueprint $table): void {
                    if (Schema::hasColumn(
                        'payroll_fiscal_run_results',
                        'payroll_run_version_id'
                    )) {
                        $table->dropConstrainedForeignId(
                            'payroll_run_version_id'
                        );
                    }

                    if (Schema::hasColumn(
                        'payroll_fiscal_run_results',
                        'calculation_mode_snapshot'
                    )) {
                        $table->dropColumn(
                            'calculation_mode_snapshot'
                        );
                    }
                }
            );
        }

        if (Schema::hasTable('payroll_run_versions')) {
            Schema::table(
                'payroll_run_versions',
                function (Blueprint $table): void {
                    if (Schema::hasColumn(
                        'payroll_run_versions',
                        'calculation_mode_snapshot'
                    )) {
                        $table->dropColumn(
                            'calculation_mode_snapshot'
                        );
                    }

                    if (Schema::hasColumn(
                        'payroll_run_versions',
                        'run_type'
                    )) {
                        $table->dropColumn('run_type');
                    }
                }
            );
        }
    }
};
