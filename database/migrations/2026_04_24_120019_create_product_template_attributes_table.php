<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_template_attributes')) {
            return;
        }

        Schema::create('product_template_attributes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('product_template_id')
                ->constrained('product_templates')
                ->cascadeOnDelete();

            $table->foreignId('product_attribute_id')
                ->constrained('product_attributes')
                ->cascadeOnDelete();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_template_id', 'product_attribute_id'], 'product_template_attr_unique');
            $table->index(['company_id', 'product_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_template_attributes');
    }
};
