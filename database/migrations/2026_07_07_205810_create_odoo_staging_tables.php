<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('odoo_connections')) {
            Schema::create('odoo_connections', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('url');
                $table->string('database');
                $table->string('username')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_success_at')->nullable();
                $table->timestamp('last_error_at')->nullable();
                $table->text('last_error_message')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['is_active']);
            });
        }

        if (! Schema::hasTable('odoo_sync_runs')) {
            Schema::create('odoo_sync_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('odoo_connection_id')->nullable()->constrained('odoo_connections')->nullOnDelete();
                $table->string('sync_type');
                $table->string('status')->default('pending');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->dateTime('period_from')->nullable();
                $table->dateTime('period_to')->nullable();
                $table->unsignedBigInteger('records_read')->default(0);
                $table->unsignedBigInteger('records_created')->default(0);
                $table->unsignedBigInteger('records_updated')->default(0);
                $table->unsignedBigInteger('records_skipped')->default(0);
                $table->unsignedBigInteger('records_failed')->default(0);
                $table->text('message')->nullable();
                $table->json('summary_json')->nullable();
                $table->json('errors_json')->nullable();
                $table->timestamps();

                $table->index(['sync_type', 'status']);
                $table->index(['period_from', 'period_to']);
            });
        }

        if (! Schema::hasTable('odoo_raw_records')) {
            Schema::create('odoo_raw_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('odoo_sync_run_id')->nullable()->constrained('odoo_sync_runs')->nullOnDelete();
                $table->string('odoo_model');
                $table->bigInteger('odoo_id')->nullable();
                $table->string('record_key')->nullable();
                $table->json('payload_json');
                $table->timestamp('odoo_write_date')->nullable();
                $table->timestamps();

                $table->index(['odoo_model', 'odoo_id']);
                $table->index(['record_key']);
            });
        }

        if (! Schema::hasTable('odoo_company_maps')) {
            Schema::create('odoo_company_maps', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_company_id')->unique();
                $table->string('odoo_company_name');
                $table->unsignedBigInteger('bexia_company_id')->nullable();
                $table->string('bexia_company_name')->nullable();
                $table->string('status')->default('pending');
                $table->text('notes')->nullable();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['status']);
                $table->index(['bexia_company_id']);
            });
        }

        if (! Schema::hasTable('odoo_location_maps')) {
            Schema::create('odoo_location_maps', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_location_id')->unique();
                $table->string('odoo_location_name')->nullable();
                $table->string('odoo_location_complete_name')->nullable();
                $table->string('odoo_usage')->nullable();
                $table->bigInteger('odoo_company_id')->nullable();
                $table->string('odoo_company_name')->nullable();
                $table->bigInteger('odoo_warehouse_id')->nullable();
                $table->string('odoo_warehouse_name')->nullable();
                $table->unsignedBigInteger('bexia_location_id')->nullable();
                $table->string('bexia_location_name')->nullable();
                $table->string('alias_operativo')->nullable();
                $table->string('status')->default('pending');
                $table->text('notes')->nullable();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['odoo_company_id']);
                $table->index(['odoo_usage']);
                $table->index(['status']);
                $table->index(['bexia_location_id']);
            });
        }

        if (! Schema::hasTable('odoo_product_maps')) {
            Schema::create('odoo_product_maps', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_product_id')->unique();
                $table->bigInteger('odoo_template_id')->nullable();
                $table->string('sku')->nullable();
                $table->string('barcode')->nullable();
                $table->text('odoo_product_name')->nullable();
                $table->string('odoo_category')->nullable();
                $table->bigInteger('odoo_category_id')->nullable();
                $table->string('tracking')->nullable();
                $table->boolean('active')->default(true);
                $table->unsignedBigInteger('bexia_product_id')->nullable();
                $table->string('bexia_product_name')->nullable();
                $table->string('status')->default('pending');
                $table->text('notes')->nullable();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['sku']);
                $table->index(['barcode']);
                $table->index(['odoo_template_id']);
                $table->index(['odoo_category_id']);
                $table->index(['status']);
                $table->index(['bexia_product_id']);
            });
        }

        if (! Schema::hasTable('odoo_partner_maps')) {
            Schema::create('odoo_partner_maps', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_partner_id')->unique();
                $table->text('odoo_partner_name')->nullable();
                $table->string('vat')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->boolean('is_company')->default(false);
                $table->boolean('active')->default(true);
                $table->unsignedBigInteger('bexia_contact_id')->nullable();
                $table->string('bexia_contact_name')->nullable();
                $table->string('status')->default('pending');
                $table->text('notes')->nullable();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['vat']);
                $table->index(['email']);
                $table->index(['status']);
                $table->index(['bexia_contact_id']);
            });
        }

        if (! Schema::hasTable('odoo_companies')) {
            Schema::create('odoo_companies', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_company_id')->unique();
                $table->string('name');
                $table->boolean('active')->default(true);
                $table->json('raw_json')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('odoo_warehouses')) {
            Schema::create('odoo_warehouses', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_warehouse_id')->unique();
                $table->string('name');
                $table->string('code')->nullable();
                $table->bigInteger('odoo_company_id')->nullable();
                $table->string('company_name')->nullable();
                $table->bigInteger('view_location_id')->nullable();
                $table->bigInteger('lot_stock_id')->nullable();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['odoo_company_id']);
                $table->index(['code']);
            });
        }

        if (! Schema::hasTable('odoo_locations')) {
            Schema::create('odoo_locations', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_location_id')->unique();
                $table->string('name')->nullable();
                $table->string('complete_name')->nullable();
                $table->string('usage')->nullable();
                $table->bigInteger('parent_id')->nullable();
                $table->bigInteger('odoo_company_id')->nullable();
                $table->string('company_name')->nullable();
                $table->boolean('active')->default(true);
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['usage']);
                $table->index(['odoo_company_id']);
                $table->index(['complete_name']);
            });
        }

        if (! Schema::hasTable('odoo_products')) {
            Schema::create('odoo_products', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_product_id')->unique();
                $table->bigInteger('odoo_template_id')->nullable();
                $table->string('sku')->nullable();
                $table->string('barcode')->nullable();
                $table->text('name')->nullable();
                $table->string('category')->nullable();
                $table->bigInteger('category_id')->nullable();
                $table->string('tracking')->nullable();
                $table->boolean('active')->default(true);
                $table->decimal('standard_price', 18, 6)->nullable();
                $table->decimal('list_price', 18, 6)->nullable();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['sku']);
                $table->index(['barcode']);
                $table->index(['category_id']);
                $table->index(['tracking']);
                $table->index(['active']);
            });
        }

        if (! Schema::hasTable('odoo_partners')) {
            Schema::create('odoo_partners', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_partner_id')->unique();
                $table->text('name')->nullable();
                $table->string('vat')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->boolean('is_company')->default(false);
                $table->boolean('active')->default(true);
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['vat']);
                $table->index(['email']);
                $table->index(['active']);
            });
        }

        if (! Schema::hasTable('odoo_lots')) {
            Schema::create('odoo_lots', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_lot_id')->unique();
                $table->string('name');
                $table->bigInteger('odoo_product_id')->nullable();
                $table->text('product_name')->nullable();
                $table->bigInteger('odoo_company_id')->nullable();
                $table->string('company_name')->nullable();
                $table->timestamp('odoo_create_date')->nullable();
                $table->timestamp('odoo_write_date')->nullable();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['name']);
                $table->index(['odoo_product_id']);
                $table->index(['odoo_company_id']);
            });
        }

        if (! Schema::hasTable('odoo_stock_quants')) {
            Schema::create('odoo_stock_quants', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_quant_id')->nullable();
                $table->bigInteger('odoo_product_id')->nullable();
                $table->bigInteger('odoo_location_id')->nullable();
                $table->bigInteger('odoo_company_id')->nullable();
                $table->bigInteger('odoo_lot_id')->nullable();
                $table->string('sku')->nullable();
                $table->text('product_name')->nullable();
                $table->string('location_name')->nullable();
                $table->string('location_complete_name')->nullable();
                $table->string('company_name')->nullable();
                $table->string('lot_name')->nullable();
                $table->decimal('quantity', 18, 6)->default(0);
                $table->decimal('reserved_quantity', 18, 6)->default(0);
                $table->decimal('available_quantity', 18, 6)->default(0);
                $table->timestamp('snapshot_at')->nullable();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['odoo_quant_id']);
                $table->index(['odoo_product_id']);
                $table->index(['odoo_location_id']);
                $table->index(['odoo_company_id']);
                $table->index(['odoo_lot_id']);
                $table->index(['snapshot_at']);
            });
        }

        if (! Schema::hasTable('odoo_pos_orders')) {
            Schema::create('odoo_pos_orders', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_pos_order_id')->unique();
                $table->string('name')->nullable();
                $table->timestamp('date_order')->nullable();
                $table->bigInteger('odoo_company_id')->nullable();
                $table->string('company_name')->nullable();
                $table->bigInteger('odoo_partner_id')->nullable();
                $table->string('partner_name')->nullable();
                $table->bigInteger('session_id')->nullable();
                $table->string('session_name')->nullable();
                $table->string('state')->nullable();
                $table->decimal('amount_total', 18, 6)->default(0);
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['date_order']);
                $table->index(['odoo_company_id']);
                $table->index(['odoo_partner_id']);
                $table->index(['session_id']);
                $table->index(['state']);
            });
        }

        if (! Schema::hasTable('odoo_sale_orders')) {
            Schema::create('odoo_sale_orders', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_sale_order_id')->unique();
                $table->string('name')->nullable();
                $table->timestamp('date_order')->nullable();
                $table->bigInteger('odoo_company_id')->nullable();
                $table->string('company_name')->nullable();
                $table->bigInteger('odoo_partner_id')->nullable();
                $table->string('partner_name')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->string('user_name')->nullable();
                $table->string('state')->nullable();
                $table->decimal('amount_total', 18, 6)->default(0);
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['date_order']);
                $table->index(['odoo_company_id']);
                $table->index(['odoo_partner_id']);
                $table->index(['state']);
            });
        }

        if (! Schema::hasTable('odoo_account_moves')) {
            Schema::create('odoo_account_moves', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_account_move_id')->unique();
                $table->string('name')->nullable();
                $table->date('date')->nullable();
                $table->date('invoice_date')->nullable();
                $table->bigInteger('odoo_company_id')->nullable();
                $table->string('company_name')->nullable();
                $table->bigInteger('odoo_partner_id')->nullable();
                $table->string('partner_name')->nullable();
                $table->string('move_type')->nullable();
                $table->string('state')->nullable();
                $table->decimal('amount_total', 18, 6)->default(0);
                $table->decimal('amount_total_signed', 18, 6)->default(0);
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['date']);
                $table->index(['invoice_date']);
                $table->index(['odoo_company_id']);
                $table->index(['odoo_partner_id']);
                $table->index(['move_type']);
                $table->index(['state']);
            });
        }

        if (! Schema::hasTable('odoo_purchase_orders')) {
            Schema::create('odoo_purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_purchase_order_id')->unique();
                $table->string('name')->nullable();
                $table->timestamp('date_order')->nullable();
                $table->bigInteger('odoo_company_id')->nullable();
                $table->string('company_name')->nullable();
                $table->bigInteger('odoo_partner_id')->nullable();
                $table->string('partner_name')->nullable();
                $table->string('state')->nullable();
                $table->decimal('amount_total', 18, 6)->default(0);
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['date_order']);
                $table->index(['odoo_company_id']);
                $table->index(['odoo_partner_id']);
                $table->index(['state']);
            });
        }

        if (! Schema::hasTable('odoo_stock_moves')) {
            Schema::create('odoo_stock_moves', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_stock_move_id')->unique();
                $table->string('name')->nullable();
                $table->timestamp('date')->nullable();
                $table->bigInteger('odoo_company_id')->nullable();
                $table->string('company_name')->nullable();
                $table->bigInteger('odoo_product_id')->nullable();
                $table->text('product_name')->nullable();
                $table->bigInteger('location_id')->nullable();
                $table->string('location_name')->nullable();
                $table->bigInteger('location_dest_id')->nullable();
                $table->string('location_dest_name')->nullable();
                $table->bigInteger('picking_type_id')->nullable();
                $table->string('picking_type_name')->nullable();
                $table->string('state')->nullable();
                $table->decimal('product_uom_qty', 18, 6)->default(0);
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['date']);
                $table->index(['odoo_company_id']);
                $table->index(['odoo_product_id']);
                $table->index(['location_id']);
                $table->index(['location_dest_id']);
                $table->index(['picking_type_id']);
                $table->index(['state']);
            });
        }

        if (! Schema::hasTable('odoo_stock_move_lines')) {
            Schema::create('odoo_stock_move_lines', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_stock_move_line_id')->unique();
                $table->timestamp('date')->nullable();
                $table->bigInteger('odoo_company_id')->nullable();
                $table->string('company_name')->nullable();
                $table->bigInteger('odoo_product_id')->nullable();
                $table->text('product_name')->nullable();
                $table->bigInteger('location_id')->nullable();
                $table->string('location_name')->nullable();
                $table->bigInteger('location_dest_id')->nullable();
                $table->string('location_dest_name')->nullable();
                $table->bigInteger('lot_id')->nullable();
                $table->string('lot_name')->nullable();
                $table->string('state')->nullable();
                $table->decimal('qty_done', 18, 6)->default(0);
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['date']);
                $table->index(['odoo_company_id']);
                $table->index(['odoo_product_id']);
                $table->index(['location_id']);
                $table->index(['location_dest_id']);
                $table->index(['lot_id']);
                $table->index(['state']);
            });
        }

        if (! Schema::hasTable('odoo_stock_valuation_layers')) {
            Schema::create('odoo_stock_valuation_layers', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_stock_valuation_layer_id')->unique();
                $table->timestamp('create_date')->nullable();
                $table->bigInteger('odoo_company_id')->nullable();
                $table->string('company_name')->nullable();
                $table->bigInteger('odoo_product_id')->nullable();
                $table->text('product_name')->nullable();
                $table->decimal('quantity', 18, 6)->default(0);
                $table->decimal('value', 18, 6)->default(0);
                $table->decimal('unit_cost', 18, 6)->nullable();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['create_date']);
                $table->index(['odoo_company_id']);
                $table->index(['odoo_product_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_stock_valuation_layers');
        Schema::dropIfExists('odoo_stock_move_lines');
        Schema::dropIfExists('odoo_stock_moves');
        Schema::dropIfExists('odoo_purchase_orders');
        Schema::dropIfExists('odoo_account_moves');
        Schema::dropIfExists('odoo_sale_orders');
        Schema::dropIfExists('odoo_pos_orders');
        Schema::dropIfExists('odoo_stock_quants');
        Schema::dropIfExists('odoo_lots');
        Schema::dropIfExists('odoo_partners');
        Schema::dropIfExists('odoo_products');
        Schema::dropIfExists('odoo_locations');
        Schema::dropIfExists('odoo_warehouses');
        Schema::dropIfExists('odoo_companies');
        Schema::dropIfExists('odoo_partner_maps');
        Schema::dropIfExists('odoo_product_maps');
        Schema::dropIfExists('odoo_location_maps');
        Schema::dropIfExists('odoo_company_maps');
        Schema::dropIfExists('odoo_raw_records');
        Schema::dropIfExists('odoo_sync_runs');
        Schema::dropIfExists('odoo_connections');
    }
};
