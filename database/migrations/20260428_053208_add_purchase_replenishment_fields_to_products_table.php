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
            if (! Schema::hasColumn('products', 'purchase_pack_units')) {
                $table->decimal('purchase_pack_units', 16, 4)->nullable();
            }

            if (! Schema::hasColumn('products', 'purchase_min_quantity')) {
                $table->decimal('purchase_min_quantity', 16, 4)->nullable();
            }

            if (! Schema::hasColumn('products', 'purchase_multiple_quantity')) {
                $table->decimal('purchase_multiple_quantity', 16, 4)->nullable();
            }
        });

        if (Schema::hasColumn('products', 'purchase_pack_units')) {
            DB::table('products')
                ->whereNull('purchase_pack_units')
                ->update(['purchase_pack_units' => 1]);
        }

        if (Schema::hasColumn('products', 'purchase_multiple_quantity')) {
            DB::table('products')
                ->whereNull('purchase_multiple_quantity')
                ->update(['purchase_multiple_quantity' => 1]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            foreach ([
                'purchase_pack_units',
                'purchase_min_quantity',
                'purchase_multiple_quantity',
            ] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
