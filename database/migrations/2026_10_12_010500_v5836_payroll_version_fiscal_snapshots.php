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
                        'issuer_snapshot'
                    )) {
                        $table
                            ->json('issuer_snapshot')
                            ->nullable();
                    }
                }
            );
        }

        if (Schema::hasTable('payroll_run_version_lines')) {
            Schema::table(
                'payroll_run_version_lines',
                function (Blueprint $table): void {
                    if (! Schema::hasColumn(
                        'payroll_run_version_lines',
                        'employee_snapshot'
                    )) {
                        $table
                            ->json('employee_snapshot')
                            ->nullable();
                    }

                    if (! Schema::hasColumn(
                        'payroll_run_version_lines',
                        'contract_snapshot'
                    )) {
                        $table
                            ->json('contract_snapshot')
                            ->nullable();
                    }
                }
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_run_version_lines')) {
            Schema::table(
                'payroll_run_version_lines',
                function (Blueprint $table): void {
                    if (Schema::hasColumn(
                        'payroll_run_version_lines',
                        'contract_snapshot'
                    )) {
                        $table->dropColumn(
                            'contract_snapshot'
                        );
                    }

                    if (Schema::hasColumn(
                        'payroll_run_version_lines',
                        'employee_snapshot'
                    )) {
                        $table->dropColumn(
                            'employee_snapshot'
                        );
                    }
                }
            );
        }

        if (
            Schema::hasTable('payroll_run_versions')
            && Schema::hasColumn(
                'payroll_run_versions',
                'issuer_snapshot'
            )
        ) {
            Schema::table(
                'payroll_run_versions',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'issuer_snapshot'
                    );
                }
            );
        }
    }
};
