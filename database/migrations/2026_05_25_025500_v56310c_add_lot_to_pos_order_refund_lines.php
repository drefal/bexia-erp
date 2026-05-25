<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_order_refund_lines')) {
            return;
        }

        if (! Schema::hasColumn('pos_order_refund_lines', 'stock_lot_id')) {
            Schema::table('pos_order_refund_lines', function (Blueprint $table): void {
                $table->unsignedBigInteger('stock_lot_id')->nullable()->after('stock_serial_number_id')->index();
            });
        }

        if (! Schema::hasColumn('pos_order_refund_lines', 'lot_tracking_metadata')) {
            Schema::table('pos_order_refund_lines', function (Blueprint $table): void {
                $table->json('lot_tracking_metadata')->nullable()->after('stock_lot_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_order_refund_lines')) {
            return;
        }

        Schema::table('pos_order_refund_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('pos_order_refund_lines', 'lot_tracking_metadata')) {
                $table->dropColumn('lot_tracking_metadata');
            }

            if (Schema::hasColumn('pos_order_refund_lines', 'stock_lot_id')) {
                $table->dropColumn('stock_lot_id');
            }
        });
    }
};
