<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('repair_orders', 'actual_labor_hours')) {
                $table->decimal('actual_labor_hours', 10, 2)->nullable()->after('repair_finished_at');
            }

            if (! Schema::hasColumn('repair_orders', 'actual_labor_cost')) {
                $table->decimal('actual_labor_cost', 12, 2)->nullable()->after('actual_labor_hours');
            }

            if (! Schema::hasColumn('repair_orders', 'business_hours_per_day')) {
                $table->decimal('business_hours_per_day', 5, 2)->nullable()->default(8)->after('actual_labor_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            if (Schema::hasColumn('repair_orders', 'business_hours_per_day')) {
                $table->dropColumn('business_hours_per_day');
            }

            if (Schema::hasColumn('repair_orders', 'actual_labor_cost')) {
                $table->dropColumn('actual_labor_cost');
            }

            if (Schema::hasColumn('repair_orders', 'actual_labor_hours')) {
                $table->dropColumn('actual_labor_hours');
            }
        });
    }
};
