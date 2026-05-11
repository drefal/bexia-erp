<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_price_lists')) {
            Schema::create('sales_price_lists', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();

                $table->string('code')->nullable();
                $table->string('name');
                $table->string('currency', 8)->default('MXN');

                $table->string('price_type')->default('fixed')->index(); // fixed / discount
                $table->boolean('is_default')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();

                $table->date('valid_from')->nullable();
                $table->date('valid_to')->nullable();

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'sales_price_lists_company_code_unique');
            });
        }

        if (! Schema::hasTable('sales_price_list_items')) {
            Schema::create('sales_price_list_items', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('sales_price_list_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();

                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();

                $table->decimal('min_quantity', 18, 6)->default(1);
                $table->decimal('price_without_tax', 18, 6)->default(0);
                $table->decimal('tax_rate', 8, 4)->default(16);
                $table->decimal('price_with_tax', 18, 6)->default(0);

                $table->decimal('discount_percent', 8, 4)->default(0);

                $table->date('valid_from')->nullable();
                $table->date('valid_to')->nullable();

                $table->boolean('is_active')->default(true)->index();
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(['product_id', 'product_variant_id'], 'sales_price_list_items_product_variant_idx');
            });
        }

        if (Schema::hasTable('sales_orders') && ! Schema::hasColumn('sales_orders', 'price_list_id')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('price_list_id')->nullable()->index()->after('customer_name');
            });
        }
    }

    public function down(): void
    {
        // No eliminamos para no perder configuración comercial.
    }
};
