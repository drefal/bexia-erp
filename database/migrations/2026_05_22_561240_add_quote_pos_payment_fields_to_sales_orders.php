<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_orders')) {
            return;
        }

        Schema::table('sales_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_orders', 'quote_pos_payment_status')) {
                $table->string('quote_pos_payment_status', 40)->nullable()->index();
            }

            if (! Schema::hasColumn('sales_orders', 'quote_pos_paid_at')) {
                $table->timestamp('quote_pos_paid_at')->nullable()->index();
            }

            if (! Schema::hasColumn('sales_orders', 'quote_pos_order_id')) {
                $table->unsignedBigInteger('quote_pos_order_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_orders')) {
            return;
        }

        Schema::table('sales_orders', function (Blueprint $table) {
            foreach ([
                'quote_pos_order_id',
                'quote_pos_paid_at',
                'quote_pos_payment_status',
            ] as $column) {
                if (Schema::hasColumn('sales_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
