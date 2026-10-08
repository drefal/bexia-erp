<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_advances', function (Blueprint $table): void {
            $table->decimal(
                'reconciled_amount',
                18,
                6
            )
                ->default(0)
                ->after('amount');

            $table->decimal(
                'return_due_amount',
                18,
                6
            )
                ->default(0)
                ->after('reconciled_amount');

            $table->decimal(
                'reimbursement_due_amount',
                18,
                6
            )
                ->default(0)
                ->after('return_due_amount');

            $table->timestamp(
                'reconciliation_finalized_at'
            )
                ->nullable()
                ->after('closed_at');

            $table->foreignId(
                'reconciliation_finalized_by_user_id'
            )
                ->nullable()
                ->after('reconciliation_finalized_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expense_advances', function (Blueprint $table): void {
            $table->dropForeign([
                'reconciliation_finalized_by_user_id',
            ]);

            $table->dropColumn([
                'reconciled_amount',
                'return_due_amount',
                'reimbursement_due_amount',
                'reconciliation_finalized_at',
                'reconciliation_finalized_by_user_id',
            ]);
        });
    }
};
