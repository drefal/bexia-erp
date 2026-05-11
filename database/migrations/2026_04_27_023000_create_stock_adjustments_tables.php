<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_adjustments')) {
            Schema::create('stock_adjustments', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();

                $table->string('reference', 80)->nullable()->index();
                $table->date('adjustment_date')->nullable();
                $table->string('status', 40)->default('draft')->index();

                $table->text('reason')->nullable();
                $table->text('notes')->nullable();

                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('confirmed_by')->nullable()->index();
                $table->timestamp('confirmed_at')->nullable();

                $table->timestamps();

                $table->foreign('warehouse_id')
                    ->references('id')
                    ->on('warehouses')
                    ->nullOnDelete();

                $table->foreign('location_id')
                    ->references('id')
                    ->on('stock_locations')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('stock_adjustment_lines')) {
            Schema::create('stock_adjustment_lines', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('stock_adjustment_id')->index();

                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
                $table->unsignedBigInteger('lot_id')->nullable()->index();

                $table->decimal('current_quantity', 18, 6)->default(0);
                $table->decimal('counted_quantity', 18, 6)->default(0);
                $table->decimal('difference_quantity', 18, 6)->default(0);

                $table->decimal('unit_cost', 18, 6)->nullable();
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->foreign('stock_adjustment_id')
                    ->references('id')
                    ->on('stock_adjustments')
                    ->cascadeOnDelete();

                if (Schema::hasTable('products')) {
                    $table->foreign('product_id')
                        ->references('id')
                        ->on('products')
                        ->nullOnDelete();
                }

                if (Schema::hasTable('product_variants')) {
                    $table->foreign('product_variant_id')
                        ->references('id')
                        ->on('product_variants')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_lines');
        Schema::dropIfExists('stock_adjustments');
    }
};
