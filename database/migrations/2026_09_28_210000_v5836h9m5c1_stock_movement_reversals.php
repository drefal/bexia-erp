<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('reverses_stock_movement_id')
                ->nullable()
                ->after('received_at')
                ->constrained('stock_movements')
                ->nullOnDelete();

            $table->foreignId('reversed_by_stock_movement_id')
                ->nullable()
                ->after('reverses_stock_movement_id')
                ->constrained('stock_movements')
                ->nullOnDelete();

            $table->string('reversal_reason', 500)
                ->nullable()
                ->after('reversed_by_stock_movement_id');

            $table->foreignId('cancelled_by')
                ->nullable()
                ->after('reversal_reason')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('cancelled_at')
                ->nullable()
                ->after('cancelled_by');

            $table->string('cancellation_reason', 500)
                ->nullable()
                ->after('cancelled_at');

            $table->index(
                ['company_id', 'reverses_stock_movement_id'],
                'sm_company_reverses_idx'
            );

            $table->index(
                ['company_id', 'reversed_by_stock_movement_id'],
                'sm_company_reversed_by_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('sm_company_reverses_idx');
            $table->dropIndex('sm_company_reversed_by_idx');

            $table->dropForeign(['reverses_stock_movement_id']);
            $table->dropForeign(['reversed_by_stock_movement_id']);
            $table->dropForeign(['cancelled_by']);

            $table->dropColumn([
                'reverses_stock_movement_id',
                'reversed_by_stock_movement_id',
                'reversal_reason',
                'cancelled_by',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
