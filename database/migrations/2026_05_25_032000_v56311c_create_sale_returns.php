<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sale_returns')) {
            Schema::create('sale_returns', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('sale_delivery_id')->nullable()->index();
                $table->unsignedBigInteger('sales_order_id')->nullable()->index();
                $table->string('number')->nullable()->index();
                $table->string('status')->default('done')->index();
                $table->string('return_type')->default('total')->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('source_location_id')->nullable()->index();
                $table->unsignedBigInteger('destination_location_id')->nullable()->index();
                $table->unsignedBigInteger('stock_movement_id')->nullable()->index();
                $table->text('reason')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
                $table->timestamp('returned_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sale_return_lines')) {
            Schema::create('sale_return_lines', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('sale_return_id')->index();
                $table->unsignedBigInteger('sale_delivery_id')->nullable()->index();
                $table->unsignedBigInteger('sale_delivery_line_id')->nullable()->index();
                $table->unsignedBigInteger('sales_order_id')->nullable()->index();
                $table->unsignedBigInteger('sales_order_line_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
                $table->string('product_label')->nullable();
                $table->string('variant_label')->nullable();
                $table->decimal('quantity', 18, 6)->default(0);
                $table->decimal('unit_cost', 18, 6)->nullable();
                $table->decimal('total_cost', 18, 6)->nullable();
                $table->unsignedBigInteger('stock_lot_id')->nullable()->index();
                $table->json('lot_tracking_metadata')->nullable();
                $table->unsignedBigInteger('stock_serial_number_id')->nullable()->index();
                $table->json('serial_tracking_metadata')->nullable();
                $table->unsignedBigInteger('stock_movement_line_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_lines');
        Schema::dropIfExists('sale_returns');
    }
};
