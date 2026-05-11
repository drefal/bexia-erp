<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_points')) {
            return;
        }

        Schema::table('pos_points', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_points', 'allow_partial_payment')) {
                $table->boolean('allow_partial_payment')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'partial_payment_product_id')) {
                $table->unsignedBigInteger('partial_payment_product_id')->nullable()->index();
            }

            if (! Schema::hasColumn('pos_points', 'is_bar_restaurant')) {
                $table->boolean('is_bar_restaurant')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'multiple_cashiers_per_session')) {
                $table->boolean('multiple_cashiers_per_session')->default(true);
            }

            if (! Schema::hasColumn('pos_points', 'initial_category_id')) {
                $table->unsignedBigInteger('initial_category_id')->nullable()->index();
            }

            if (! Schema::hasColumn('pos_points', 'restrict_categories')) {
                $table->boolean('restrict_categories')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'allowed_category_ids')) {
                $table->json('allowed_category_ids')->nullable();
            }

            if (! Schema::hasColumn('pos_points', 'default_customer_id')) {
                $table->unsignedBigInteger('default_customer_id')->nullable()->index();
            }

            if (! Schema::hasColumn('pos_points', 'show_product_info')) {
                $table->boolean('show_product_info')->default(true);
            }

            if (! Schema::hasColumn('pos_points', 'hide_cost')) {
                $table->boolean('hide_cost')->default(true);
            }

            if (! Schema::hasColumn('pos_points', 'hide_margin')) {
                $table->boolean('hide_margin')->default(true);
            }

            if (! Schema::hasColumn('pos_points', 'show_stock')) {
                $table->boolean('show_stock')->default(true);
            }

            if (! Schema::hasColumn('pos_points', 'stock_display_type')) {
                $table->string('stock_display_type')->default('on_hand');
            }

            if (! Schema::hasColumn('pos_points', 'allow_out_of_stock_sales')) {
                $table->boolean('allow_out_of_stock_sales')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'deny_sale_below_qty')) {
                $table->decimal('deny_sale_below_qty', 16, 6)->default(0);
            }

            if (! Schema::hasColumn('pos_points', 'stock_scope')) {
                $table->string('stock_scope')->default('current_warehouse');
            }

            if (! Schema::hasColumn('pos_points', 'stock_source_location_id')) {
                $table->unsignedBigInteger('stock_source_location_id')->nullable()->index();
            }

            if (! Schema::hasColumn('pos_points', 'show_pos_orders')) {
                $table->boolean('show_pos_orders')->default(true);
            }

            if (! Schema::hasColumn('pos_points', 'session_limit_mode')) {
                $table->string('session_limit_mode')->default('current_day');
            }

            if (! Schema::hasColumn('pos_points', 'show_draft_orders')) {
                $table->boolean('show_draft_orders')->default(true);
            }

            if (! Schema::hasColumn('pos_points', 'show_published_orders')) {
                $table->boolean('show_published_orders')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'show_barcode_on_ticket')) {
                $table->boolean('show_barcode_on_ticket')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'default_tax_id')) {
                $table->unsignedBigInteger('default_tax_id')->nullable()->index();
            }

            if (! Schema::hasColumn('pos_points', 'temporary_account_id')) {
                $table->unsignedBigInteger('temporary_account_id')->nullable()->index();
            }

            if (! Schema::hasColumn('pos_points', 'sales_team_id')) {
                $table->unsignedBigInteger('sales_team_id')->nullable()->index();
            }

            if (! Schema::hasColumn('pos_points', 'flexible_taxes')) {
                $table->boolean('flexible_taxes')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'price_mode')) {
                $table->string('price_mode')->default('tax_included');
            }

            if (! Schema::hasColumn('pos_points', 'available_price_list_ids')) {
                $table->json('available_price_list_ids')->nullable();
            }

            if (! Schema::hasColumn('pos_points', 'default_price_list_id')) {
                $table->unsignedBigInteger('default_price_list_id')->nullable()->index();
            }

            if (! Schema::hasColumn('pos_points', 'price_control')) {
                $table->boolean('price_control')->default(true);
            }

            if (! Schema::hasColumn('pos_points', 'line_discounts')) {
                $table->boolean('line_discounts')->default(true);
            }

            if (! Schema::hasColumn('pos_points', 'global_discounts')) {
                $table->boolean('global_discounts')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'promotions_enabled')) {
                $table->boolean('promotions_enabled')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'custom_receipt_header_footer')) {
                $table->boolean('custom_receipt_header_footer')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'receipt_header')) {
                $table->text('receipt_header')->nullable();
            }

            if (! Schema::hasColumn('pos_points', 'receipt_footer')) {
                $table->text('receipt_footer')->nullable();
            }

            if (! Schema::hasColumn('pos_points', 'auto_print_receipt')) {
                $table->boolean('auto_print_receipt')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'skip_receipt_preview')) {
                $table->boolean('skip_receipt_preview')->default(true);
            }

            if (! Schema::hasColumn('pos_points', 'use_qr_on_receipt')) {
                $table->boolean('use_qr_on_receipt')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'payment_method_names')) {
                $table->json('payment_method_names')->nullable();
            }

            if (! Schema::hasColumn('pos_points', 'cash_rounding')) {
                $table->boolean('cash_rounding')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'cash_denominations')) {
                $table->json('cash_denominations')->nullable();
            }

            if (! Schema::hasColumn('pos_points', 'tips_enabled')) {
                $table->boolean('tips_enabled')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'printer_enabled')) {
                $table->boolean('printer_enabled')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'printer_ip')) {
                $table->string('printer_ip')->nullable();
            }

            if (! Schema::hasColumn('pos_points', 'customer_display_enabled')) {
                $table->boolean('customer_display_enabled')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'iot_box_enabled')) {
                $table->boolean('iot_box_enabled')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'inventory_update_mode')) {
                $table->string('inventory_update_mode')->default('real_time');
            }

            if (! Schema::hasColumn('pos_points', 'operation_type_id')) {
                $table->unsignedBigInteger('operation_type_id')->nullable()->index();
            }

            if (! Schema::hasColumn('pos_points', 'send_later')) {
                $table->boolean('send_later')->default(false);
            }

            if (! Schema::hasColumn('pos_points', 'send_later_warehouse_id')) {
                $table->unsignedBigInteger('send_later_warehouse_id')->nullable()->index();
            }

            if (! Schema::hasColumn('pos_points', 'send_later_route_id')) {
                $table->unsignedBigInteger('send_later_route_id')->nullable()->index();
            }

            if (! Schema::hasColumn('pos_points', 'barcode_nomenclature')) {
                $table->string('barcode_nomenclature')->nullable();
            }

            if (! Schema::hasColumn('pos_points', 'limited_products_loading')) {
                $table->boolean('limited_products_loading')->default(true);
            }

            if (! Schema::hasColumn('pos_points', 'loaded_products_limit')) {
                $table->unsignedInteger('loaded_products_limit')->default(500);
            }

            if (! Schema::hasColumn('pos_points', 'load_products_in_background')) {
                $table->boolean('load_products_in_background')->default(true);
            }

            if (! Schema::hasColumn('pos_points', 'limited_customers_loading')) {
                $table->boolean('limited_customers_loading')->default(true);
            }

            if (! Schema::hasColumn('pos_points', 'loaded_customers_limit')) {
                $table->unsignedInteger('loaded_customers_limit')->default(200);
            }

            if (! Schema::hasColumn('pos_points', 'load_customers_in_background')) {
                $table->boolean('load_customers_in_background')->default(true);
            }
        });

        if (Schema::hasTable('pos_cashiers')) {
            Schema::table('pos_cashiers', function (Blueprint $table) {
                if (! Schema::hasColumn('pos_cashiers', 'can_discount')) {
                    $table->boolean('can_discount')->default(true);
                }

                if (! Schema::hasColumn('pos_cashiers', 'can_cancel')) {
                    $table->boolean('can_cancel')->default(true);
                }

                if (! Schema::hasColumn('pos_cashiers', 'can_open_cash_drawer')) {
                    $table->boolean('can_open_cash_drawer')->default(false);
                }

                if (! Schema::hasColumn('pos_cashiers', 'max_discount_percent')) {
                    $table->decimal('max_discount_percent', 8, 2)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        // No se eliminan columnas para evitar perdida de configuracion.
    }
};
