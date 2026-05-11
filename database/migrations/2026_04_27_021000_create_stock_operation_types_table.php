<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_operation_types')) {
            Schema::create('stock_operation_types', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();

                $table->string('code', 80);
                $table->string('name', 180);

                $table->string('operation_kind', 80)->default('internal_transfer')->index();

                $table->unsignedBigInteger('source_location_id')->nullable()->index();
                $table->unsignedBigInteger('destination_location_id')->nullable()->index();

                $table->string('reference_prefix', 30)->nullable();
                $table->unsignedInteger('next_number')->default(1);
                $table->unsignedInteger('sequence')->default(10);

                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'warehouse_id', 'code'], 'stock_operation_types_company_warehouse_code_unique');

                $table->foreign('warehouse_id')
                    ->references('id')
                    ->on('warehouses')
                    ->nullOnDelete();

                $table->foreign('source_location_id')
                    ->references('id')
                    ->on('stock_locations')
                    ->nullOnDelete();

                $table->foreign('destination_location_id')
                    ->references('id')
                    ->on('stock_locations')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_operation_types');
    }
};
