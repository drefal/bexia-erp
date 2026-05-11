<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_price_lists')) {
            return;
        }

        Schema::table('sales_price_lists', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_price_lists', 'calculation_type')) {
                $table->string('calculation_type')->default('items')->index();
            }

            if (! Schema::hasColumn('sales_price_lists', 'base_price_list_id')) {
                $table->unsignedBigInteger('base_price_list_id')->nullable()->index();
            }

            if (! Schema::hasColumn('sales_price_lists', 'adjustment_percent')) {
                $table->decimal('adjustment_percent', 10, 4)->default(0);
            }
        });

        DB::table('sales_price_lists')
            ->whereNull('calculation_type')
            ->update(['calculation_type' => 'items']);

        DB::table('sales_price_lists')
            ->where('calculation_type', '')
            ->update(['calculation_type' => 'items']);
    }

    public function down(): void
    {
        // No eliminamos columnas para no romper listas existentes.
    }
};
