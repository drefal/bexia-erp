<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('stock_movement_lines')
            && ! Schema::hasColumn('stock_movement_lines', 'serial_tracking_metadata')
        ) {
            Schema::table('stock_movement_lines', function (Blueprint $table) {
                $table->json('serial_tracking_metadata')->nullable()->after('source_line_id');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('stock_movement_lines')
            && Schema::hasColumn('stock_movement_lines', 'serial_tracking_metadata')
        ) {
            Schema::table('stock_movement_lines', function (Blueprint $table) {
                $table->dropColumn('serial_tracking_metadata');
            });
        }
    }
};
