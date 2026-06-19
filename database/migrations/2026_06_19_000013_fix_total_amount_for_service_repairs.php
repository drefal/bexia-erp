<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repair_orders')) {
            return;
        }

        Schema::table('repair_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('repair_orders', 'total_amount')) {
                if (Schema::hasColumn('repair_orders', 'economic_total')) {
                    $table->decimal('total_amount', 15, 2)->nullable()->after('economic_total');
                } else {
                    $table->decimal('total_amount', 15, 2)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('repair_orders')) {
            return;
        }

        Schema::table('repair_orders', function (Blueprint $table) {
            if (Schema::hasColumn('repair_orders', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
        });
    }
};
