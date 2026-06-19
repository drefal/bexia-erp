<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('repair_orders', 'ready_for_delivery_at')) {
                $table->dateTime('ready_for_delivery_at')->nullable()->after('repair_finished_at');
            }

            if (! Schema::hasColumn('repair_orders', 'delivered_at')) {
                $table->dateTime('delivered_at')->nullable()->after('ready_for_delivery_at');
            }

            if (! Schema::hasColumn('repair_orders', 'delivered_to')) {
                $table->string('delivered_to')->nullable()->after('delivered_at');
            }

            if (! Schema::hasColumn('repair_orders', 'delivery_notes')) {
                $table->text('delivery_notes')->nullable()->after('delivered_to');
            }
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            if (Schema::hasColumn('repair_orders', 'delivery_notes')) {
                $table->dropColumn('delivery_notes');
            }

            if (Schema::hasColumn('repair_orders', 'delivered_to')) {
                $table->dropColumn('delivered_to');
            }

            if (Schema::hasColumn('repair_orders', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }

            if (Schema::hasColumn('repair_orders', 'ready_for_delivery_at')) {
                $table->dropColumn('ready_for_delivery_at');
            }
        });
    }
};
