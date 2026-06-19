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
            if (! Schema::hasColumn('repair_orders', 'economic_paid_at')) {
                $table->timestamp('economic_paid_at')->nullable()->after('ready_to_charge_at');
            }

            if (! Schema::hasColumn('repair_orders', 'economic_payment_synced_at')) {
                $table->timestamp('economic_payment_synced_at')->nullable()->after('economic_paid_at');
            }

            if (! Schema::hasColumn('repair_orders', 'economic_payment_status')) {
                $table->string('economic_payment_status', 40)->nullable()->after('economic_payment_synced_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('repair_orders')) {
            return;
        }

        Schema::table('repair_orders', function (Blueprint $table) {
            foreach ([
                'economic_payment_status',
                'economic_payment_synced_at',
                'economic_paid_at',
            ] as $column) {
                if (Schema::hasColumn('repair_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
