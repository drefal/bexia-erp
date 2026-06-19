<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('repair_orders')) {
            Schema::table('repair_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('repair_orders', 'parts_required')) {
                    $table->text('parts_required')->nullable();
                }

                if (! Schema::hasColumn('repair_orders', 'parts_cost_estimate')) {
                    $table->decimal('parts_cost_estimate', 14, 2)->nullable();
                }

                if (! Schema::hasColumn('repair_orders', 'labor_cost_estimate')) {
                    $table->decimal('labor_cost_estimate', 14, 2)->nullable();
                }

                if (! Schema::hasColumn('repair_orders', 'other_cost_estimate')) {
                    $table->decimal('other_cost_estimate', 14, 2)->nullable();
                }

                if (! Schema::hasColumn('repair_orders', 'quote_total')) {
                    $table->decimal('quote_total', 14, 2)->nullable();
                }

                if (! Schema::hasColumn('repair_orders', 'requires_internal_approval')) {
                    $table->boolean('requires_internal_approval')->default(false)->index();
                }

                if (! Schema::hasColumn('repair_orders', 'requires_customer_approval')) {
                    $table->boolean('requires_customer_approval')->default(false)->index();
                }

                if (! Schema::hasColumn('repair_orders', 'quote_status')) {
                    $table->string('quote_status')->default('not_required')->index();
                }

                if (! Schema::hasColumn('repair_orders', 'quote_notes')) {
                    $table->text('quote_notes')->nullable();
                }

                if (! Schema::hasColumn('repair_orders', 'customer_approved_at')) {
                    $table->timestamp('customer_approved_at')->nullable();
                }

                if (! Schema::hasColumn('repair_orders', 'customer_rejected_at')) {
                    $table->timestamp('customer_rejected_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('repair_order_approvals')) {
            Schema::table('repair_order_approvals', function (Blueprint $table) {
                if (! Schema::hasColumn('repair_order_approvals', 'approval_type')) {
                    $table->string('approval_type')->default('general')->index();
                }

                if (! Schema::hasColumn('repair_order_approvals', 'amount')) {
                    $table->decimal('amount', 14, 2)->nullable();
                }

                if (! Schema::hasColumn('repair_order_approvals', 'requested_reason')) {
                    $table->text('requested_reason')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('repair_order_approvals')) {
            foreach ([
                'requested_reason',
                'amount',
                'approval_type',
            ] as $column) {
                if (Schema::hasColumn('repair_order_approvals', $column)) {
                    Schema::table('repair_order_approvals', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (Schema::hasTable('repair_orders')) {
            foreach ([
                'customer_rejected_at',
                'customer_approved_at',
                'quote_notes',
                'quote_status',
                'requires_customer_approval',
                'requires_internal_approval',
                'quote_total',
                'other_cost_estimate',
                'labor_cost_estimate',
                'parts_cost_estimate',
                'parts_required',
            ] as $column) {
                if (Schema::hasColumn('repair_orders', $column)) {
                    Schema::table('repair_orders', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
