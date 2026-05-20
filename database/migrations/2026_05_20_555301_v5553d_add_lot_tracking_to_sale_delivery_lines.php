<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sale_delivery_lines')) {
            return;
        }

        Schema::table('sale_delivery_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('sale_delivery_lines', 'stock_lot_id')) {
                $table->unsignedBigInteger('stock_lot_id')->nullable()->index();
            }

            if (! Schema::hasColumn('sale_delivery_lines', 'lot_tracking_metadata')) {
                $table->json('lot_tracking_metadata')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sale_delivery_lines')) {
            return;
        }

        Schema::table('sale_delivery_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('sale_delivery_lines', 'lot_tracking_metadata')) {
                $table->dropColumn('lot_tracking_metadata');
            }

            if (Schema::hasColumn('sale_delivery_lines', 'stock_lot_id')) {
                $table->dropColumn('stock_lot_id');
            }
        });
    }
};
