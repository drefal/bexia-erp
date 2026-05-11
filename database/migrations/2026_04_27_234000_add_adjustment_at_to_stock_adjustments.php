<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_adjustments')) {
            return;
        }

        Schema::table('stock_adjustments', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_adjustments', 'adjustment_at')) {
                $table->timestamp('adjustment_at')
                    ->nullable()
                    ->after('adjustment_date');
            }
        });

        if (
            Schema::hasColumn('stock_adjustments', 'adjustment_at')
            && Schema::hasColumn('stock_adjustments', 'adjustment_date')
        ) {
            DB::statement("
                UPDATE stock_adjustments
                SET adjustment_at = adjustment_date::timestamp
                WHERE adjustment_at IS NULL
                  AND adjustment_date IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_adjustments')) {
            return;
        }

        Schema::table('stock_adjustments', function (Blueprint $table): void {
            if (Schema::hasColumn('stock_adjustments', 'adjustment_at')) {
                $table->dropColumn('adjustment_at');
            }
        });
    }
};
