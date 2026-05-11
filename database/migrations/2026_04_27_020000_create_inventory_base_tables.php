<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warehouses')) {
            Schema::create('warehouses', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('code', 50);
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code']);
            });
        }

        if (! Schema::hasTable('stock_location_types')) {
            Schema::create('stock_location_types', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('code', 50);
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->boolean('is_internal')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code']);
            });
        }

        if (! Schema::hasTable('stock_locations')) {
            Schema::create('stock_locations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->unsignedBigInteger('stock_location_type_id')->nullable()->index();

                $table->string('code', 80);
                $table->string('name', 180);
                $table->string('barcode', 120)->nullable();
                $table->text('description')->nullable();

                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'warehouse_id', 'code']);

                $table->foreign('warehouse_id')
                    ->references('id')
                    ->on('warehouses')
                    ->nullOnDelete();

                $table->foreign('parent_id')
                    ->references('id')
                    ->on('stock_locations')
                    ->nullOnDelete();

                $table->foreign('stock_location_type_id')
                    ->references('id')
                    ->on('stock_location_types')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('stock_location_types');
        Schema::dropIfExists('warehouses');
    }
};
