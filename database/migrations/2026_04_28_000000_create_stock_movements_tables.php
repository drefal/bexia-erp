<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('stock_operation_type_id')->nullable()->index();

                $table->unsignedBigInteger('source_location_id')->nullable()->index();
                $table->unsignedBigInteger('destination_location_id')->nullable()->index();

                $table->string('reference', 100)->nullable()->index();
                $table->timestamp('movement_at')->nullable();
                $table->string('status', 40)->default('draft')->index();

                $table->string('origin_document', 180)->nullable();
                $table->unsignedBigInteger('contact_id')->nullable()->index();
                $table->text('notes')->nullable();

                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('confirmed_by')->nullable()->index();
                $table->timestamp('confirmed_at')->nullable();

                $table->timestamps();

                if (Schema::hasTable('warehouses')) {
                    $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
                }

                if (Schema::hasTable('stock_operation_types')) {
                    $table->foreign('stock_operation_type_id')->references('id')->on('stock_operation_types')->nullOnDelete();
                }

                if (Schema::hasTable('stock_locations')) {
                    $table->foreign('source_location_id')->references('id')->on('stock_locations')->nullOnDelete();
                    $table->foreign('destination_location_id')->references('id')->on('stock_locations')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('stock_movement_lines')) {
            Schema::create('stock_movement_lines', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('stock_movement_id')->index();

                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
                $table->unsignedBigInteger('lot_id')->nullable()->index();

                $table->decimal('requested_quantity', 18, 6)->default(0);
                $table->decimal('done_quantity', 18, 6)->default(0);
                $table->decimal('unit_cost', 18, 6)->nullable();

                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('stock_movement_id')
                    ->references('id')
                    ->on('stock_movements')
                    ->cascadeOnDelete();

                if (Schema::hasTable('products')) {
                    $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
                    $table->foreign('product_variant_id')->references('id')->on('products')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_lines');
        Schema::dropIfExists('stock_movements');
    }
};
