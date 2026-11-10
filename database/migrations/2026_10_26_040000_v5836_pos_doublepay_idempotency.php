<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_orders', function (Blueprint $table) {
            $table->uuid('creation_request_uuid')
                ->nullable()
                ->unique('pos_orders_creation_request_uuid_unique');
        });

        Schema::table('pos_order_payments', function (Blueprint $table) {
            $table->uuid('payment_request_uuid')
                ->nullable()
                ->index('pos_order_payments_request_uuid_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pos_order_payments', function (Blueprint $table) {
            $table->dropIndex('pos_order_payments_request_uuid_idx');
            $table->dropColumn('payment_request_uuid');
        });

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropUnique('pos_orders_creation_request_uuid_unique');
            $table->dropColumn('creation_request_uuid');
        });
    }
};
