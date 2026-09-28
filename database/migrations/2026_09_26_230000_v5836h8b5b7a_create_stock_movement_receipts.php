<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_movement_receipts')) {
            Schema::create('stock_movement_receipts', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('stock_movement_id')
                    ->constrained('stock_movements')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('company_id')->index();

                $table->unsignedBigInteger('received_by')
                    ->nullable()
                    ->index();

                $table->timestamp('received_at')->nullable()->index();

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(
                    ['company_id', 'stock_movement_id'],
                    'sm_receipts_company_movement_idx'
                );
            });
        }

        if (! Schema::hasTable('stock_movement_receipt_lines')) {
            Schema::create('stock_movement_receipt_lines', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('stock_movement_receipt_id')
                    ->constrained('stock_movement_receipts')
                    ->cascadeOnDelete();

                $table->foreignId('stock_movement_line_id')
                    ->constrained('stock_movement_lines')
                    ->cascadeOnDelete();

                $table->decimal('quantity', 18, 6);

                $table->timestamps();

                $table->index(
                    'stock_movement_line_id',
                    'sm_receipt_lines_movement_line_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_receipt_lines');
        Schema::dropIfExists('stock_movement_receipts');
    }
};
