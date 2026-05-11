<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_quants')) {
            return;
        }

        $hasWarehouses = Schema::hasTable('warehouses');
        $hasLocations = Schema::hasTable('stock_locations');
        $hasProducts = Schema::hasTable('products');
        $hasProductVariants = Schema::hasTable('product_variants');

        Schema::create('stock_quants', function (Blueprint $table) use ($hasWarehouses, $hasLocations, $hasProducts, $hasProductVariants): void {
            $table->id();

            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('warehouse_id')->nullable()->index();
            $table->unsignedBigInteger('location_id')->nullable()->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('product_variant_id')->nullable()->index();

            // Preparado para fase futura de lotes / series.
            $table->unsignedBigInteger('lot_id')->nullable()->index();

            $table->decimal('quantity', 18, 6)->default(0);
            $table->decimal('reserved_quantity', 18, 6)->default(0);

            // Preparado para costeo promedio/futuro.
            $table->decimal('average_cost', 18, 6)->nullable();

            $table->timestamps();

            $table->index(
                ['company_id', 'warehouse_id', 'location_id', 'product_id', 'product_variant_id'],
                'stock_quants_main_lookup_idx'
            );

            if ($hasWarehouses) {
                $table->foreign('warehouse_id')
                    ->references('id')
                    ->on('warehouses')
                    ->nullOnDelete();
            }

            if ($hasLocations) {
                $table->foreign('location_id')
                    ->references('id')
                    ->on('stock_locations')
                    ->nullOnDelete();
            }

            if ($hasProducts) {
                $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->nullOnDelete();
            }

            if ($hasProductVariants) {
                $table->foreign('product_variant_id')
                    ->references('id')
                    ->on('product_variants')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_quants');
    }
};
