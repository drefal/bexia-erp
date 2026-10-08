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
                    'reimbursement_transfer_request_id'
                )
                    ->nullable()
                    ->constrained(
                        'treasury_cash_transfer_requests'
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'reimbursement_approval_request_id'
                )
                    ->nullable()
                    ->constrained('approval_requests')
                    ->nullOnDelete();

                $table->foreignId(
                    'reimbursement_treasury_movement_id'
                )
                    ->nullable()
                    ->constrained('treasury_movements')
                    ->nullOnDelete();

                $table->foreignId(
                    'reimbursement_petty_cash_movement_id'
                )
                    ->nullable()
                    ->constrained(
                        'petty_cash_fund_movements'
                    )
                    ->nullOnDelete();

                $table->timestamp(
                    'reimbursed_at'
                )->nullable();

                $table->foreignId(
                    'reimbursed_by_user_id'
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
                    'reimbursement_transfer_request_id',
                ]);

                $table->dropForeign([
                    'reimbursement_approval_request_id',
                ]);

                $table->dropForeign([
                    'reimbursement_treasury_movement_id',
                ]);

                $table->dropForeign([
                    'reimbursement_petty_cash_movement_id',
                ]);

                $table->dropForeign([
                    'reimbursed_by_user_id',
                ]);

                $table->dropColumn([
                    'reimbursement_transfer_request_id',
                    'reimbursement_approval_request_id',
                    'reimbursement_treasury_movement_id',
                    'reimbursement_petty_cash_movement_id',
                    'reimbursed_at',
                    'reimbursed_by_user_id',
                ]);
            }
        );
    }
};
