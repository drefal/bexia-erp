<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('odoo_product_maps')) {
            return;
        }

        Schema::table('odoo_product_maps', function (Blueprint $table) {
            if (! Schema::hasColumn('odoo_product_maps', 'bexia_product_id')) {
                $table->unsignedBigInteger('bexia_product_id')->nullable()->index();
            }
            if (! Schema::hasColumn('odoo_product_maps', 'bexia_product_name')) {
                $table->string('bexia_product_name')->nullable();
            }
            if (! Schema::hasColumn('odoo_product_maps', 'bexia_sku')) {
                $table->string('bexia_sku')->nullable()->index();
            }
            if (! Schema::hasColumn('odoo_product_maps', 'bexia_barcode')) {
                $table->string('bexia_barcode')->nullable()->index();
            }
            if (! Schema::hasColumn('odoo_product_maps', 'match_method')) {
                $table->string('match_method')->nullable()->index();
            }
            if (! Schema::hasColumn('odoo_product_maps', 'confidence')) {
                $table->unsignedInteger('confidence')->default(0);
            }
            if (! Schema::hasColumn('odoo_product_maps', 'physical_stock_quant_rows')) {
                $table->unsignedInteger('physical_stock_quant_rows')->default(0);
            }
            if (! Schema::hasColumn('odoo_product_maps', 'physical_stock_total_quantity')) {
                $table->decimal('physical_stock_total_quantity', 20, 4)->default(0);
            }
            if (! Schema::hasColumn('odoo_product_maps', 'physical_stock_available_quantity')) {
                $table->decimal('physical_stock_available_quantity', 20, 4)->default(0);
            }
            if (! Schema::hasColumn('odoo_product_maps', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No destructive rollback for migration staging.
    }
};
