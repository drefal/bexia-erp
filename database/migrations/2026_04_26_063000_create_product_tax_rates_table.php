<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_tax_rates')) {
            return;
        }

        Schema::create('product_tax_rates', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->nullable()
                ->index();

            $table->foreignId('product_id')
                ->index();

            $table->foreignId('tax_rate_id')
                ->index();

            $table->string('usage_type', 30)->default('sale');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['product_id', 'tax_rate_id', 'usage_type'], 'product_tax_rates_unique');
        });

        if (Schema::hasTable('companies')) {
            Schema::table('product_tax_rates', function (Blueprint $table): void {
                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('product_tax_rates', function (Blueprint $table): void {
                $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('tax_rates')) {
            Schema::table('product_tax_rates', function (Blueprint $table): void {
                $table->foreign('tax_rate_id')
                    ->references('id')
                    ->on('tax_rates')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tax_rates');
    }
};
