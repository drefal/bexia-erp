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
            if (! Schema::hasColumn('repair_orders', 'workflow_stage')) {
                $table->string('workflow_stage')->default('quote_draft')->index();
            }

            if (! Schema::hasColumn('repair_orders', 'quote_submitted_at')) {
                $table->timestamp('quote_submitted_at')->nullable();
            }

            if (! Schema::hasColumn('repair_orders', 'quote_approved_at')) {
                $table->timestamp('quote_approved_at')->nullable();
            }

            if (! Schema::hasColumn('repair_orders', 'repair_started_at')) {
                $table->timestamp('repair_started_at')->nullable();
            }

            if (! Schema::hasColumn('repair_orders', 'repair_finished_at')) {
                $table->timestamp('repair_finished_at')->nullable();
            }

            if (! Schema::hasColumn('repair_orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('repair_orders')) {
            return;
        }

        foreach ([
            'delivered_at',
            'repair_finished_at',
            'repair_started_at',
            'quote_approved_at',
            'quote_submitted_at',
            'workflow_stage',
        ] as $column) {
            if (Schema::hasColumn('repair_orders', $column)) {
                Schema::table('repair_orders', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
