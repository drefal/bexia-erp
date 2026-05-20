<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_order_lines')) {
            return;
        }

        Schema::table('pos_order_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('pos_order_lines', 'stock_lot_id')) {
                $table->unsignedBigInteger('stock_lot_id')->nullable()->index()->after('stock_serial_number_id');
            }

            if (! Schema::hasColumn('pos_order_lines', 'lot_tracking_metadata')) {
                $table->json('lot_tracking_metadata')->nullable()->after('serial_tracking_metadata');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_order_lines')) {
            return;
        }

        Schema::table('pos_order_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('pos_order_lines', 'lot_tracking_metadata')) {
                $table->dropColumn('lot_tracking_metadata');
            }

            if (Schema::hasColumn('pos_order_lines', 'stock_lot_id')) {
                $table->dropColumn('stock_lot_id');
            }
        });
    }
};
