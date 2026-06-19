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
            if (! Schema::hasColumn('repair_orders', 'supervisor_review_requested_at')) {
                $table->timestamp('supervisor_review_requested_at')->nullable();
            }

            if (! Schema::hasColumn('repair_orders', 'supervisor_reviewed_at')) {
                $table->timestamp('supervisor_reviewed_at')->nullable();
            }

            if (! Schema::hasColumn('repair_orders', 'ready_for_delivery_at')) {
                $table->timestamp('ready_for_delivery_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('repair_orders')) {
            return;
        }

        foreach ([
            'ready_for_delivery_at',
            'supervisor_reviewed_at',
            'supervisor_review_requested_at',
        ] as $column) {
            if (Schema::hasColumn('repair_orders', $column)) {
                Schema::table('repair_orders', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
