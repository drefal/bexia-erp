<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_stock_balances')) {
            Schema::create('inventory_stock_balances', function (Blueprint $table) {
                $table->id();
                $table->string('balance_key')->unique();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('inventory_location_id')->index();
                $table->unsignedBigInteger('product_id')->index();

                $table->bigInteger('odoo_product_id')->nullable()->index();
                $table->bigInteger('odoo_location_id')->nullable()->index();
                $table->bigInteger('odoo_company_id')->nullable()->index();
                $table->bigInteger('odoo_lot_id')->nullable()->index();
                $table->string('lot_name')->nullable()->index();

                $table->decimal('quantity', 20, 4)->default(0);
                $table->decimal('reserved_quantity', 20, 4)->default(0);
                $table->decimal('available_quantity', 20, 4)->default(0);
                $table->unsignedInteger('stock_quant_rows')->default(0);

                $table->string('source')->default('odoo_current_stock_v5_81_0j')->index();
                $table->timestamp('snapshot_at')->nullable()->index();
                $table->json('odoo_quant_ids')->nullable();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'warehouse_id']);
                $table->index(['product_id', 'inventory_location_id']);
                $table->index(['warehouse_id', 'product_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_balances');
    }
};
