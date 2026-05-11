<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_orders')) {
            return;
        }

        Schema::table('sales_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_orders', 'approval_snapshot_hash')) {
                $table->string('approval_snapshot_hash', 80)->nullable()->after('margin_rejection_reason');
            }

            if (! Schema::hasColumn('sales_orders', 'approval_snapshot_at')) {
                $table->timestamp('approval_snapshot_at')->nullable()->after('approval_snapshot_hash');
            }

            if (! Schema::hasColumn('sales_orders', 'approval_changed_after_approval')) {
                $table->boolean('approval_changed_after_approval')->default(false)->after('approval_snapshot_at');
            }
        });
    }

    public function down(): void
    {
        //
    }
};
