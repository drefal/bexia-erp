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
            if (! Schema::hasColumn('pos_order_lines', 'product_variant_id')) {
                $table->unsignedBigInteger('product_variant_id')
                    ->nullable()
                    ->after('product_id')
                    ->index();
            }

            if (! Schema::hasColumn('pos_order_lines', 'stock_serial_number_id')) {
                $table->unsignedBigInteger('stock_serial_number_id')
                    ->nullable()
                    ->after('product_variant_id')
                    ->index();
            }

            if (! Schema::hasColumn('pos_order_lines', 'serial_tracking_metadata')) {
                $table->json('serial_tracking_metadata')
                    ->nullable()
                    ->after('stock_serial_number_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_order_lines')) {
            return;
        }

        Schema::table('pos_order_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('pos_order_lines', 'serial_tracking_metadata')) {
                $table->dropColumn('serial_tracking_metadata');
            }

            if (Schema::hasColumn('pos_order_lines', 'stock_serial_number_id')) {
                $table->dropColumn('stock_serial_number_id');
            }

            if (Schema::hasColumn('pos_order_lines', 'product_variant_id')) {
                $table->dropColumn('product_variant_id');
            }
        });
    }
};
