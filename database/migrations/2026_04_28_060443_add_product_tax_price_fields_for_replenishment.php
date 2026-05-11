<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'sale_tax_rate')) {
                $table->decimal('sale_tax_rate', 8, 4)->nullable()->default(16);
            }

            if (! Schema::hasColumn('products', 'purchase_tax_rate')) {
                $table->decimal('purchase_tax_rate', 8, 4)->nullable()->default(16);
            }

            if (! Schema::hasColumn('products', 'average_cost_without_tax')) {
                $table->decimal('average_cost_without_tax', 18, 6)->nullable();
            }

            if (
                ! Schema::hasColumn('products', 'sale_price_without_tax')
                && ! Schema::hasColumn('products', 'sale_price')
                && ! Schema::hasColumn('products', 'list_price')
                && ! Schema::hasColumn('products', 'price')
            ) {
                $table->decimal('sale_price_without_tax', 18, 6)->nullable();
            }
        });

        if (Schema::hasColumn('products', 'sale_tax_rate')) {
            DB::table('products')
                ->whereNull('sale_tax_rate')
                ->update(['sale_tax_rate' => 16]);
        }

        if (Schema::hasColumn('products', 'purchase_tax_rate')) {
            DB::table('products')
                ->whereNull('purchase_tax_rate')
                ->update(['purchase_tax_rate' => 16]);
        }

        if (
            Schema::hasColumn('products', 'average_cost_without_tax')
            && Schema::hasColumn('products', 'cost')
        ) {
            DB::table('products')
                ->whereNull('average_cost_without_tax')
                ->whereNotNull('cost')
                ->update(['average_cost_without_tax' => DB::raw('cost')]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            foreach ([
                'sale_tax_rate',
                'purchase_tax_rate',
                'average_cost_without_tax',
                'sale_price_without_tax',
            ] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
