<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('expense_advances')
            && ! Schema::hasColumn(
                'expense_advances',
                'authorized_by_employee_id'
            )
        ) {
            Schema::table(
                'expense_advances',
                function (Blueprint $table): void {
                    $table->foreignId(
                        'authorized_by_employee_id'
                    )
                        ->nullable()
                        ->after('employee_id')
                        ->constrained('employees')
                        ->nullOnDelete();

                    $table->index(
                        [
                            'company_id',
                            'authorized_by_employee_id',
                        ],
                        'expense_advances_authorizer_idx'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('expense_advances')
            && Schema::hasColumn(
                'expense_advances',
                'authorized_by_employee_id'
            )
        ) {
            Schema::table(
                'expense_advances',
                function (Blueprint $table): void {
                    $table->dropIndex(
                        'expense_advances_authorizer_idx'
                    );

                    $table->dropForeign([
                        'authorized_by_employee_id',
                    ]);

                    $table->dropColumn(
                        'authorized_by_employee_id'
                    );
                }
            );
        }
    }
};
