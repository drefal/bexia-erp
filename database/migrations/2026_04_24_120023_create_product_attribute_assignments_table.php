<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_attribute_assignments')) {
            return;
        }

        Schema::create('product_attribute_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('product_attribute_id')
                ->constrained('product_attributes')
                ->cascadeOnDelete();

            $table->foreignId('product_attribute_value_id')
                ->nullable()
                ->constrained('product_attribute_values')
                ->nullOnDelete();

            $table->string('custom_value')->nullable();

            $table->timestamps();

            $table->unique(['product_id', 'product_attribute_id'], 'product_attr_assignment_unique');
            $table->index(['company_id', 'product_id']);
            $table->index(['company_id', 'product_attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_assignments');
    }
};
