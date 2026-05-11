<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'default_warehouse_id')) {
                    $table->unsignedBigInteger('default_warehouse_id')->nullable()->index();
                }

                if (! Schema::hasColumn('users', 'default_location_id')) {
                    $table->unsignedBigInteger('default_location_id')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_orders', 'delivery_contact_id')) {
                    $table->unsignedBigInteger('delivery_contact_id')->nullable()->index()->after('customer_contact_id');
                }
            });
        }
    }

    public function down(): void
    {
        //
    }
};
