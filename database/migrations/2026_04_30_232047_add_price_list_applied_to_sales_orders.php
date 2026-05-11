<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_orders')) {
            return;
        }

        Schema::table('sales_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_orders', 'price_list_applied_id')) {
                $table->unsignedBigInteger('price_list_applied_id')->nullable()->after('price_list_id');
            }

            if (! Schema::hasColumn('sales_orders', 'price_list_applied_at')) {
                $table->timestamp('price_list_applied_at')->nullable()->after('price_list_applied_id');
            }
        });

        DB::table('sales_orders')
            ->whereNull('price_list_applied_id')
            ->whereNotNull('price_list_id')
            ->update([
                'price_list_applied_id' => DB::raw('price_list_id'),
                'price_list_applied_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        //
    }
};
