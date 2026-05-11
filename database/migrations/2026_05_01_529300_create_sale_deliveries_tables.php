<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sale_deliveries')) {
            Schema::create('sale_deliveries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('sales_order_id')->index();
                $table->string('number', 80)->nullable()->unique();
                $table->string('status', 40)->default('draft')->index();
                $table->string('delivery_type', 40)->default('partial')->index();
                $table->timestamp('planned_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('source_location_id')->nullable()->index();
                $table->unsignedBigInteger('destination_location_id')->nullable()->index();
                $table->unsignedBigInteger('stock_movement_id')->nullable()->index();
                $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
                $table->unsignedBigInteger('cancelled_by_user_id')->nullable()->index();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['sales_order_id', 'status']);
            });
        }

        if (! Schema::hasTable('sale_delivery_lines')) {
            Schema::create('sale_delivery_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sale_delivery_id')->index();
                $table->unsignedBigInteger('sales_order_id')->index();
                $table->unsignedBigInteger('sales_order_line_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
                $table->string('product_label')->nullable();
                $table->string('variant_label')->nullable();
                $table->decimal('ordered_quantity', 18, 6)->default(0);
                $table->decimal('quantity', 18, 6)->default(0);
                $table->decimal('unit_cost', 18, 6)->default(0);
                $table->unsignedBigInteger('stock_movement_line_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'product_variant_id']);
            });
        }
    }

    public function down(): void
    {
        // No eliminamos tablas para no perder trazabilidad de entregas.
    }
};
