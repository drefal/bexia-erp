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
            if (! Schema::hasColumn('products', 'preferred_supplier_id')) {
                $table->unsignedBigInteger('preferred_supplier_id')->nullable()->index();
            }

            if (! Schema::hasColumn('products', 'sale_tax_rate')) {
                $table->decimal('sale_tax_rate', 8, 4)->nullable()->default(16);
            }

            if (! Schema::hasColumn('products', 'average_cost_without_tax')) {
                $table->decimal('average_cost_without_tax', 18, 6)->nullable();
            }

            if (
                ! Schema::hasColumn('products', 'sale_price_without_tax')
                && ! Schema::hasColumn('products', 'sale_price')
                && ! Schema::hasColumn('products', 'price')
                && ! Schema::hasColumn('products', 'list_price')
            ) {
                $table->decimal('sale_price_without_tax', 18, 6)->nullable();
            }
        });

        if (Schema::hasColumn('products', 'sale_tax_rate')) {
            DB::table('products')
                ->whereNull('sale_tax_rate')
                ->update(['sale_tax_rate' => 16]);
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

        if (
            Schema::hasColumn('products', 'sale_price_without_tax')
            && Schema::hasColumn('products', 'sale_price')
        ) {
            DB::table('products')
                ->whereNull('sale_price_without_tax')
                ->whereNotNull('sale_price')
                ->update(['sale_price_without_tax' => DB::raw('sale_price')]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            foreach ([
                'preferred_supplier_id',
                'sale_tax_rate',
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
