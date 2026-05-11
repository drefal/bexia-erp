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
            if (! Schema::hasColumn('products', 'parent_product_id')) {
                $table->unsignedBigInteger('parent_product_id')->nullable()->after('product_template_id');
            }

            if (! Schema::hasColumn('products', 'has_variants')) {
                $table->boolean('has_variants')->default(false)->after('is_variant');
            }

            if (! Schema::hasColumn('products', 'variant_group')) {
                $table->string('variant_group')->nullable()->after('variant_name');
            }

            if (! Schema::hasColumn('products', 'variant_value')) {
                $table->string('variant_value')->nullable()->after('variant_group');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            try {
                $table->index(['company_id', 'parent_product_id'], 'products_company_parent_product_idx');
            } catch (Throwable $e) {
                //
            }

            try {
                $table->index(['company_id', 'is_variant'], 'products_company_is_variant_idx');
            } catch (Throwable $e) {
                //
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            foreach ([
                'variant_value',
                'variant_group',
                'has_variants',
                'parent_product_id',
            ] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
