<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * BEXIA_V5527D5_RECEIPT_SELLER_DISPLAY_MODE
     * Solo afecta tickets impresos, no pantalla PDV.
     */
    public function up(): void
    {
        if (! Schema::hasTable('pos_points')) {
            return;
        }

        Schema::table('pos_points', function (Blueprint $table): void {
            if (! Schema::hasColumn('pos_points', 'receipt_seller_display_mode')) {
                $table->string('receipt_seller_display_mode', 40)
                    ->nullable()
                    ->default('staff_name')
                    ->after('show_order_reference_on_ticket');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_points')) {
            return;
        }

        Schema::table('pos_points', function (Blueprint $table): void {
            if (Schema::hasColumn('pos_points', 'receipt_seller_display_mode')) {
                $table->dropColumn('receipt_seller_display_mode');
            }
        });
    }
};
