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
            if (! Schema::hasColumn('repair_orders', 'labor_hours_estimate')) {
                $table->decimal('labor_hours_estimate', 14, 2)->nullable();
            }

            if (! Schema::hasColumn('repair_orders', 'labor_hour_rate')) {
                $table->decimal('labor_hour_rate', 14, 2)->nullable();
            }

            if (! Schema::hasColumn('repair_orders', 'labor_rate_source')) {
                $table->string('labor_rate_source')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('repair_orders')) {
            return;
        }

        foreach ([
            'labor_rate_source',
            'labor_hour_rate',
            'labor_hours_estimate',
        ] as $column) {
            if (Schema::hasColumn('repair_orders', $column)) {
                Schema::table('repair_orders', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
