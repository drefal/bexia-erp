<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_order_lines')) {
            return;
        }

        Schema::table('sales_order_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_order_lines', 'estimated_unit_cost_without_tax')) {
                $table->decimal('estimated_unit_cost_without_tax', 18, 6)->default(0)->after('unit_price_without_tax');
            }

            if (! Schema::hasColumn('sales_order_lines', 'gross_margin_amount')) {
                $table->decimal('gross_margin_amount', 18, 6)->default(0)->after('line_total_with_tax');
            }

            if (! Schema::hasColumn('sales_order_lines', 'gross_margin_percent')) {
                $table->decimal('gross_margin_percent', 10, 4)->default(0)->after('gross_margin_amount');
            }

            if (! Schema::hasColumn('sales_order_lines', 'margin_status')) {
                $table->string('margin_status')->nullable()->index()->after('gross_margin_percent');
            }
        });
    }

    public function down(): void
    {
        //
    }
};
