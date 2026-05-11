<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('purchase_orders')
            && ! Schema::hasColumn('purchase_orders', 'duplicated_from_purchase_order_id')
        ) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('duplicated_from_purchase_order_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('purchase_orders')
            && Schema::hasColumn('purchase_orders', 'duplicated_from_purchase_order_id')
        ) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('duplicated_from_purchase_order_id');
            });
        }
    }
};
