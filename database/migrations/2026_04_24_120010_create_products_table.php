<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            return;
        }

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->foreignId('product_category_id')
                ->nullable()
                ->constrained('product_categories')
                ->nullOnDelete();

            $table->foreignId('inventory_unit_id')
                ->nullable()
                ->constrained('inventory_units')
                ->nullOnDelete();

            $table->string('sku', 80)->nullable();
            $table->string('barcode', 120)->nullable();
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('product_type', 40)->default('stockable');
            $table->string('tracking', 30)->default('none');
            $table->string('costing_method', 30)->default('average');

            $table->decimal('standard_cost', 15, 4)->default(0);
            $table->decimal('sale_price', 15, 4)->default(0);
            $table->decimal('purchase_price', 15, 4)->default(0);

            $table->foreignId('inventory_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->foreignId('cogs_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->foreignId('sales_income_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->boolean('can_be_sold')->default(true);
            $table->boolean('can_be_purchased')->default(true);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'barcode']);
            $table->index(['company_id', 'product_type']);
            $table->index(['company_id', 'tracking']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
