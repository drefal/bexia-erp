<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_locations')) {
            Schema::create('inventory_locations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('parent_location_id')->nullable()->index();
                $table->bigInteger('odoo_location_id')->nullable()->unique();
                $table->string('code')->nullable()->index();
                $table->string('name');
                $table->string('complete_name')->nullable();
                $table->string('usage')->nullable()->index();
                $table->string('location_type')->default('internal')->index();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('stock_quant_rows')->default(0);
                $table->decimal('stock_total_quantity', 20, 4)->default(0);
                $table->decimal('stock_reserved_quantity', 20, 4)->default(0);
                $table->decimal('stock_available_quantity', 20, 4)->default(0);
                $table->text('notes')->nullable();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['warehouse_id', 'code']);
                $table->index(['company_id', 'warehouse_id']);
            });
        } else {
            Schema::table('inventory_locations', function (Blueprint $table) {
                if (! Schema::hasColumn('inventory_locations', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                }
                if (! Schema::hasColumn('inventory_locations', 'warehouse_id')) {
                    $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                }
                if (! Schema::hasColumn('inventory_locations', 'parent_location_id')) {
                    $table->unsignedBigInteger('parent_location_id')->nullable()->index();
                }
                if (! Schema::hasColumn('inventory_locations', 'odoo_location_id')) {
                    $table->bigInteger('odoo_location_id')->nullable()->unique();
                }
                if (! Schema::hasColumn('inventory_locations', 'code')) {
                    $table->string('code')->nullable()->index();
                }
                if (! Schema::hasColumn('inventory_locations', 'name')) {
                    $table->string('name')->nullable();
                }
                if (! Schema::hasColumn('inventory_locations', 'complete_name')) {
                    $table->string('complete_name')->nullable();
                }
                if (! Schema::hasColumn('inventory_locations', 'usage')) {
                    $table->string('usage')->nullable()->index();
                }
                if (! Schema::hasColumn('inventory_locations', 'location_type')) {
                    $table->string('location_type')->default('internal')->index();
                }
                if (! Schema::hasColumn('inventory_locations', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (! Schema::hasColumn('inventory_locations', 'stock_quant_rows')) {
                    $table->unsignedInteger('stock_quant_rows')->default(0);
                }
                if (! Schema::hasColumn('inventory_locations', 'stock_total_quantity')) {
                    $table->decimal('stock_total_quantity', 20, 4)->default(0);
                }
                if (! Schema::hasColumn('inventory_locations', 'stock_reserved_quantity')) {
                    $table->decimal('stock_reserved_quantity', 20, 4)->default(0);
                }
                if (! Schema::hasColumn('inventory_locations', 'stock_available_quantity')) {
                    $table->decimal('stock_available_quantity', 20, 4)->default(0);
                }
                if (! Schema::hasColumn('inventory_locations', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (! Schema::hasColumn('inventory_locations', 'raw_json')) {
                    $table->json('raw_json')->nullable();
                }
            });
        }

        if (Schema::hasTable('odoo_location_maps')) {
            Schema::table('odoo_location_maps', function (Blueprint $table) {
                if (! Schema::hasColumn('odoo_location_maps', 'bexia_location_id')) {
                    $table->unsignedBigInteger('bexia_location_id')->nullable()->index();
                }
                if (! Schema::hasColumn('odoo_location_maps', 'bexia_location_name')) {
                    $table->string('bexia_location_name')->nullable();
                }
                if (! Schema::hasColumn('odoo_location_maps', 'bexia_warehouse_id')) {
                    $table->unsignedBigInteger('bexia_warehouse_id')->nullable()->index();
                }
                if (! Schema::hasColumn('odoo_location_maps', 'bexia_warehouse_name')) {
                    $table->string('bexia_warehouse_name')->nullable();
                }
                if (! Schema::hasColumn('odoo_location_maps', 'bexia_warehouse_code')) {
                    $table->string('bexia_warehouse_code')->nullable();
                }
                if (! Schema::hasColumn('odoo_location_maps', 'match_method')) {
                    $table->string('match_method')->nullable()->index();
                }
                if (! Schema::hasColumn('odoo_location_maps', 'confidence')) {
                    $table->unsignedInteger('confidence')->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_locations');
    }
};
