<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'expense_advances',
            function (Blueprint $table): void {
                $table->foreignId(
                    'return_treasury_movement_id'
                )
                    ->nullable()
                    ->constrained('treasury_movements')
                    ->nullOnDelete();

                $table->foreignId(
                    'return_petty_cash_fund_movement_id'
                )
                    ->nullable()
                    ->constrained(
                        'petty_cash_fund_movements'
                    )
                    ->nullOnDelete();

                $table->timestamp(
                    'returned_at'
                )->nullable();

                $table->foreignId(
                    'returned_by_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'expense_advances',
            function (Blueprint $table): void {
                $table->dropForeign([
                    'return_treasury_movement_id',
                ]);

                $table->dropForeign([
                    'return_petty_cash_fund_movement_id',
                ]);

                $table->dropForeign([
                    'returned_by_user_id',
                ]);

                $table->dropColumn([
                    'return_treasury_movement_id',
                    'return_petty_cash_fund_movement_id',
                    'returned_at',
                    'returned_by_user_id',
                ]);
            }
        );
    }
};
