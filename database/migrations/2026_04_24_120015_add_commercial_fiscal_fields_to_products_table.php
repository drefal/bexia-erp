<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'internal_reference')) {
                $table->string('internal_reference', 120)->nullable()->after('sku');
            }

            if (! Schema::hasColumn('products', 'image_path')) {
                $table->string('image_path')->nullable()->after('description');
            }

            if (! Schema::hasColumn('products', 'invoice_policy')) {
                $table->string('invoice_policy', 40)->default('ordered_quantities')->after('product_type');
            }

            if (! Schema::hasColumn('products', 'available_in_pos')) {
                $table->boolean('available_in_pos')->default(false)->after('can_be_purchased');
            }

            if (! Schema::hasColumn('products', 'weigh_with_scale')) {
                $table->boolean('weigh_with_scale')->default(false)->after('available_in_pos');
            }

            if (! Schema::hasColumn('products', 'include_in_global_invoice')) {
                $table->boolean('include_in_global_invoice')->default(true)->after('weigh_with_scale');
            }

            if (! Schema::hasColumn('products', 'allow_out_of_stock_sales')) {
                $table->boolean('allow_out_of_stock_sales')->default(false)->after('include_in_global_invoice');
            }

            if (! Schema::hasColumn('products', 'model')) {
                $table->string('model', 120)->nullable()->after('barcode');
            }

            if (! Schema::hasColumn('products', 'brand')) {
                $table->string('brand', 120)->nullable()->after('model');
            }

            if (! Schema::hasColumn('products', 'material')) {
                $table->string('material', 120)->nullable()->after('brand');
            }

            if (! Schema::hasColumn('products', 'color')) {
                $table->string('color', 120)->nullable()->after('material');
            }

            if (! Schema::hasColumn('products', 'product_line')) {
                $table->string('product_line', 120)->nullable()->after('color');
            }

            if (! Schema::hasColumn('products', 'condition')) {
                $table->string('condition', 120)->nullable()->after('product_line');
            }

            if (! Schema::hasColumn('products', 'catalog')) {
                $table->string('catalog', 120)->nullable()->after('condition');
            }

            if (! Schema::hasColumn('products', 'responsible_user_id')) {
                $table->foreignId('responsible_user_id')
                    ->nullable()
                    ->after('catalog')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('products', 'weight')) {
                $table->decimal('weight', 15, 4)->default(0)->after('purchase_price');
            }

            if (! Schema::hasColumn('products', 'volume')) {
                $table->decimal('volume', 15, 4)->default(0)->after('weight');
            }

            if (! Schema::hasColumn('products', 'purchase_lead_time_days')) {
                $table->decimal('purchase_lead_time_days', 8, 2)->default(0)->after('volume');
            }

            if (! Schema::hasColumn('products', 'customer_lead_time_days')) {
                $table->decimal('customer_lead_time_days', 8, 2)->default(0)->after('purchase_lead_time_days');
            }

            if (! Schema::hasColumn('products', 'manufacturing_lead_time_days')) {
                $table->decimal('manufacturing_lead_time_days', 8, 2)->default(0)->after('customer_lead_time_days');
            }

            if (! Schema::hasColumn('products', 'country_of_origin')) {
                $table->string('country_of_origin', 3)->nullable()->after('manufacturing_lead_time_days');
            }

            if (! Schema::hasColumn('products', 'hs_code')) {
                $table->string('hs_code', 60)->nullable()->after('country_of_origin');
            }

            if (! Schema::hasColumn('products', 'sat_product_service_code')) {
                $table->string('sat_product_service_code', 20)->nullable()->after('hs_code');
            }

            if (! Schema::hasColumn('products', 'sat_unit_code')) {
                $table->string('sat_unit_code', 20)->nullable()->after('sat_product_service_code');
            }

            if (! Schema::hasColumn('products', 'hazardous_material_code')) {
                $table->string('hazardous_material_code', 60)->nullable()->after('sat_unit_code');
            }

            if (! Schema::hasColumn('products', 'hazardous_packaging_code')) {
                $table->string('hazardous_packaging_code', 60)->nullable()->after('hazardous_material_code');
            }

            if (! Schema::hasColumn('products', 'tariff_fraction')) {
                $table->string('tariff_fraction', 60)->nullable()->after('hazardous_packaging_code');
            }

            if (! Schema::hasColumn('products', 'customs_unit_code')) {
                $table->string('customs_unit_code', 60)->nullable()->after('tariff_fraction');
            }

            if (! Schema::hasColumn('products', 'sale_description')) {
                $table->text('sale_description')->nullable()->after('customs_unit_code');
            }

            if (! Schema::hasColumn('products', 'purchase_description')) {
                $table->text('purchase_description')->nullable()->after('sale_description');
            }

            if (! Schema::hasColumn('products', 'last_purchase_cost')) {
                $table->decimal('last_purchase_cost', 15, 4)->default(0)->after('purchase_description');
            }

            if (! Schema::hasColumn('products', 'last_supplier_name')) {
                $table->string('last_supplier_name')->nullable()->after('last_purchase_cost');
            }

            if (! Schema::hasColumn('products', 'last_purchase_at')) {
                $table->timestamp('last_purchase_at')->nullable()->after('last_supplier_name');
            }

            if (! Schema::hasColumn('products', 'extra_attributes')) {
                $table->json('extra_attributes')->nullable()->after('last_purchase_at');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            try {
                $table->index(['company_id', 'sat_product_service_code'], 'products_company_sat_product_idx');
            } catch (Throwable $e) {
                //
            }

            try {
                $table->index(['company_id', 'available_in_pos'], 'products_company_pos_idx');
            } catch (Throwable $e) {
                //
            }

            try {
                $table->index(['company_id', 'brand'], 'products_company_brand_idx');
            } catch (Throwable $e) {
                //
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach ([
                'extra_attributes',
                'last_purchase_at',
                'last_supplier_name',
                'last_purchase_cost',
                'purchase_description',
                'sale_description',
                'customs_unit_code',
                'tariff_fraction',
                'hazardous_packaging_code',
                'hazardous_material_code',
                'sat_unit_code',
                'sat_product_service_code',
                'hs_code',
                'country_of_origin',
                'manufacturing_lead_time_days',
                'customer_lead_time_days',
                'purchase_lead_time_days',
                'volume',
                'weight',
                'responsible_user_id',
                'catalog',
                'condition',
                'product_line',
                'color',
                'material',
                'brand',
                'model',
                'allow_out_of_stock_sales',
                'include_in_global_invoice',
                'weigh_with_scale',
                'available_in_pos',
                'invoice_policy',
                'image_path',
                'internal_reference',
            ] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
