<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_serial_special_movements')) {
            Schema::create('stock_serial_special_movements', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('stock_serial_number_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
                $table->unsignedBigInteger('lot_id')->nullable()->index();

                $table->string('movement_type')->index();
                $table->string('status')->default('draft')->index();

                $table->string('serial_number_before')->nullable();
                $table->string('serial_number_after')->nullable();

                $table->unsignedBigInteger('source_warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('source_location_id')->nullable()->index();
                $table->unsignedBigInteger('destination_warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('destination_location_id')->nullable()->index();

                $table->text('reason');
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();

                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('confirmed_by')->nullable()->index();
                $table->timestamp('confirmed_at')->nullable();

                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'movement_type']);
                $table->index(['company_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_serial_special_movements');
    }
};
