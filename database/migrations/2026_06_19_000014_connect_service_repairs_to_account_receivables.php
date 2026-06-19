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
            if (! Schema::hasColumn('repair_orders', 'account_receivable_id')) {
                $table->unsignedBigInteger('account_receivable_id')->nullable()->after('total_amount');
            }

            if (! Schema::hasColumn('repair_orders', 'receivable_created_at')) {
                $table->timestamp('receivable_created_at')->nullable()->after('account_receivable_id');
            }

            if (! Schema::hasColumn('repair_orders', 'receivable_created_by')) {
                $table->unsignedBigInteger('receivable_created_by')->nullable()->after('receivable_created_at');
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
                'account_receivable_id',
                'receivable_created_at',
                'receivable_created_by',
            ] as $column) {
                if (Schema::hasColumn('repair_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
