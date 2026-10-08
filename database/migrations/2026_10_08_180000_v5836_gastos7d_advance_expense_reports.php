<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('expense_reports')
            && ! Schema::hasColumn(
                'expense_reports',
                'expense_advance_id'
            )
        ) {
            Schema::table(
                'expense_reports',
                function (Blueprint $table): void {
                    $table->foreignId('expense_advance_id')
                        ->nullable()
                        ->after('petty_cash_fund_id')
                        ->constrained('expense_advances')
                        ->nullOnDelete();

                    $table->index(
                        [
                            'expense_advance_id',
                            'status',
                        ],
                        'expense_reports_advance_status_idx'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('expense_reports')
            && Schema::hasColumn(
                'expense_reports',
                'expense_advance_id'
            )
        ) {
            Schema::table(
                'expense_reports',
                function (Blueprint $table): void {
                    $table->dropIndex(
                        'expense_reports_advance_status_idx'
                    );

                    $table->dropForeign([
                        'expense_advance_id',
                    ]);

                    $table->dropColumn(
                        'expense_advance_id'
                    );
                }
            );
        }
    }
};
