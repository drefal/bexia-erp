<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_orders')) {
            Schema::create('sales_orders', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id')->index();

                $table->string('number')->nullable()->index();
                $table->string('status')->default('draft')->index();

                $table->unsignedBigInteger('customer_contact_id')->nullable()->index();
                $table->string('customer_name')->nullable();

                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();

                $table->timestamp('order_date')->nullable();
                $table->date('expected_delivery_date')->nullable();

                $table->string('currency', 8)->default('MXN');

                // Preparado para CRM / PDV / ecommerce / venta manual.
                $table->string('source_type')->default('manual')->index();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->string('source_reference')->nullable()->index();

                $table->decimal('total_without_tax', 18, 6)->default(0);
                $table->decimal('total_tax', 18, 6)->default(0);
                $table->decimal('total_with_tax', 18, 6)->default(0);

                $table->decimal('delivered_total_quantity', 18, 6)->default(0);

                $table->timestamp('confirmed_at')->nullable();
                $table->unsignedBigInteger('confirmed_by_user_id')->nullable()->index();

                $table->unsignedBigInteger('created_by_user_id')->nullable()->index();

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'number'], 'sales_orders_company_number_unique');
            });
        }

        if (! Schema::hasTable('sales_order_lines')) {
            Schema::create('sales_order_lines', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('sales_order_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();

                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();

                $table->string('product_label')->nullable();
                $table->string('variant_label')->nullable();

                $table->decimal('quantity', 18, 6)->default(0);
                $table->decimal('delivered_quantity', 18, 6)->default(0);

                $table->decimal('unit_price_without_tax', 18, 6)->default(0);
                $table->decimal('tax_rate', 8, 4)->default(16);
                $table->decimal('unit_price_with_tax', 18, 6)->default(0);

                $table->decimal('line_total_without_tax', 18, 6)->default(0);
                $table->decimal('line_tax', 18, 6)->default(0);
                $table->decimal('line_total_with_tax', 18, 6)->default(0);

                $table->string('delivery_status')->default('pending')->index();

                $table->text('notes')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // No eliminamos tablas para no perder documentos si ya se capturaron ventas.
    }
};
