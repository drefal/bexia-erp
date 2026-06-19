<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('repair_order_parts')) {
            Schema::table('repair_order_parts', function (Blueprint $table) {
                if (! Schema::hasColumn('repair_order_parts', 'economic_quantity')) {
                    $table->decimal('economic_quantity', 15, 4)->nullable()->after('quantity');
                }

                if (! Schema::hasColumn('repair_order_parts', 'line_cost_total')) {
                    $table->decimal('line_cost_total', 15, 2)->nullable()->after('unit_cost');
                }

                if (! Schema::hasColumn('repair_order_parts', 'line_sale_total')) {
                    $table->decimal('line_sale_total', 15, 2)->nullable()->after('unit_price');
                }

                if (! Schema::hasColumn('repair_order_parts', 'line_profit_amount')) {
                    $table->decimal('line_profit_amount', 15, 2)->nullable()->after('line_sale_total');
                }

                if (! Schema::hasColumn('repair_order_parts', 'line_profit_percent')) {
                    $table->decimal('line_profit_percent', 10, 2)->nullable()->after('line_profit_amount');
                }
            });
        }

        if (Schema::hasTable('repair_orders')) {
            Schema::table('repair_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('repair_orders', 'economic_status')) {
                    $table->string('economic_status')->nullable()->after('workflow_stage');
                }

                if (! Schema::hasColumn('repair_orders', 'economic_closed_at')) {
                    $table->timestamp('economic_closed_at')->nullable()->after('economic_status');
                }

                if (! Schema::hasColumn('repair_orders', 'economic_closed_by')) {
                    $table->unsignedBigInteger('economic_closed_by')->nullable()->after('economic_closed_at');
                }

                if (! Schema::hasColumn('repair_orders', 'parts_cost_total')) {
                    $table->decimal('parts_cost_total', 15, 2)->nullable()->after('parts_subtotal');
                }

                if (! Schema::hasColumn('repair_orders', 'parts_sale_total')) {
                    $table->decimal('parts_sale_total', 15, 2)->nullable()->after('parts_cost_total');
                }

                if (! Schema::hasColumn('repair_orders', 'parts_profit_amount')) {
                    $table->decimal('parts_profit_amount', 15, 2)->nullable()->after('parts_sale_total');
                }

                if (! Schema::hasColumn('repair_orders', 'parts_profit_percent')) {
                    $table->decimal('parts_profit_percent', 10, 2)->nullable()->after('parts_profit_amount');
                }

                if (! Schema::hasColumn('repair_orders', 'labor_internal_hour_cost')) {
                    $table->decimal('labor_internal_hour_cost', 15, 2)->nullable()->after('labor_hour_rate');
                }

                if (! Schema::hasColumn('repair_orders', 'labor_cost_total')) {
                    $table->decimal('labor_cost_total', 15, 2)->nullable()->after('actual_labor_cost');
                }

                if (! Schema::hasColumn('repair_orders', 'labor_sale_total')) {
                    $table->decimal('labor_sale_total', 15, 2)->nullable()->after('labor_cost_total');
                }

                if (! Schema::hasColumn('repair_orders', 'labor_profit_amount')) {
                    $table->decimal('labor_profit_amount', 15, 2)->nullable()->after('labor_sale_total');
                }

                if (! Schema::hasColumn('repair_orders', 'labor_profit_percent')) {
                    $table->decimal('labor_profit_percent', 10, 2)->nullable()->after('labor_profit_amount');
                }

                if (! Schema::hasColumn('repair_orders', 'economic_subtotal')) {
                    $table->decimal('economic_subtotal', 15, 2)->nullable()->after('labor_profit_percent');
                }

                if (! Schema::hasColumn('repair_orders', 'economic_tax_rate')) {
                    $table->decimal('economic_tax_rate', 10, 4)->nullable()->default(16)->after('economic_subtotal');
                }

                if (! Schema::hasColumn('repair_orders', 'economic_tax')) {
                    $table->decimal('economic_tax', 15, 2)->nullable()->after('economic_tax_rate');
                }

                if (! Schema::hasColumn('repair_orders', 'economic_total')) {
                    $table->decimal('economic_total', 15, 2)->nullable()->after('economic_tax');
                }

                if (! Schema::hasColumn('repair_orders', 'total_profit_amount')) {
                    $table->decimal('total_profit_amount', 15, 2)->nullable()->after('economic_total');
                }

                if (! Schema::hasColumn('repair_orders', 'total_profit_percent')) {
                    $table->decimal('total_profit_percent', 10, 2)->nullable()->after('total_profit_amount');
                }

                if (! Schema::hasColumn('repair_orders', 'approved_total_snapshot')) {
                    $table->decimal('approved_total_snapshot', 15, 2)->nullable()->after('total_profit_percent');
                }

                if (! Schema::hasColumn('repair_orders', 'economic_difference_amount')) {
                    $table->decimal('economic_difference_amount', 15, 2)->nullable()->after('approved_total_snapshot');
                }

                if (! Schema::hasColumn('repair_orders', 'economic_difference_percent')) {
                    $table->decimal('economic_difference_percent', 10, 2)->nullable()->after('economic_difference_amount');
                }

                if (! Schema::hasColumn('repair_orders', 'economic_requires_approval')) {
                    $table->boolean('economic_requires_approval')->default(false)->after('economic_difference_percent');
                }

                if (! Schema::hasColumn('repair_orders', 'economic_difference_reason')) {
                    $table->text('economic_difference_reason')->nullable()->after('economic_requires_approval');
                }

                if (! Schema::hasColumn('repair_orders', 'ready_to_charge_at')) {
                    $table->timestamp('ready_to_charge_at')->nullable()->after('economic_difference_reason');
                }

                if (! Schema::hasColumn('repair_orders', 'economic_notes')) {
                    $table->text('economic_notes')->nullable()->after('ready_to_charge_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('repair_order_parts')) {
            Schema::table('repair_order_parts', function (Blueprint $table) {
                foreach ([
                    'economic_quantity',
                    'line_cost_total',
                    'line_sale_total',
                    'line_profit_amount',
                    'line_profit_percent',
                ] as $column) {
                    if (Schema::hasColumn('repair_order_parts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('repair_orders')) {
            Schema::table('repair_orders', function (Blueprint $table) {
                foreach ([
                    'economic_status',
                    'economic_closed_at',
                    'economic_closed_by',
                    'parts_cost_total',
                    'parts_sale_total',
                    'parts_profit_amount',
                    'parts_profit_percent',
                    'labor_internal_hour_cost',
                    'labor_cost_total',
                    'labor_sale_total',
                    'labor_profit_amount',
                    'labor_profit_percent',
                    'economic_subtotal',
                    'economic_tax_rate',
                    'economic_tax',
                    'economic_total',
                    'total_profit_amount',
                    'total_profit_percent',
                    'approved_total_snapshot',
                    'economic_difference_amount',
                    'economic_difference_percent',
                    'economic_requires_approval',
                    'economic_difference_reason',
                    'ready_to_charge_at',
                    'economic_notes',
                ] as $column) {
                    if (Schema::hasColumn('repair_orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
