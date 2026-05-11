<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_replenishment_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_replenishment_rules', 'preferred_supplier_id')) {
                $table->unsignedBigInteger('preferred_supplier_id')->nullable()->index();
            }

            if (! Schema::hasColumn('stock_replenishment_rules', 'lead_time_days')) {
                $table->unsignedInteger('lead_time_days')->nullable();
            }

            if (! Schema::hasColumn('stock_replenishment_rules', 'priority')) {
                $table->string('priority', 20)->default('normal')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_replenishment_rules', function (Blueprint $table): void {
            foreach ([
                'preferred_supplier_id',
                'lead_time_days',
                'priority',
            ] as $column) {
                if (Schema::hasColumn('stock_replenishment_rules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
