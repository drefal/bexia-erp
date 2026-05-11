<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_replenishment_rules')) {
            Schema::create('stock_replenishment_rules', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->index();
                $table->unsignedBigInteger('location_id')->index();

                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();

                $table->decimal('min_quantity', 18, 6)->default(0);
                $table->decimal('max_quantity', 18, 6)->default(0);

                $table->boolean('is_active')->default(true)->index();
                $table->text('notes')->nullable();

                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();

                $table->timestamps();

                $table->index([
                    'company_id',
                    'warehouse_id',
                    'location_id',
                    'product_id',
                    'product_variant_id',
                ], 'stock_replenishment_rules_lookup_idx');

                if (Schema::hasTable('warehouses')) {
                    $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
                }

                if (Schema::hasTable('stock_locations')) {
                    $table->foreign('location_id')->references('id')->on('stock_locations')->cascadeOnDelete();
                }

                if (Schema::hasTable('products')) {
                    $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                    $table->foreign('product_variant_id')->references('id')->on('products')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_replenishment_rules');
    }
};
