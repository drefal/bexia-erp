<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warehouse_locations')) {
            return;
        }

        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('warehouse_locations')
                ->nullOnDelete();

            $table->foreignId('inventory_location_type_id')
                ->nullable()
                ->constrained('inventory_location_types')
                ->nullOnDelete();

            $table->string('code', 80);
            $table->string('name');
            $table->string('barcode', 120)->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'code']);
            $table->index(['company_id', 'warehouse_id']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_locations');
    }
};
