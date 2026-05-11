<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'source_snapshot_hash')) {
                $table->string('source_snapshot_hash', 128)->nullable()->index();
            }

            if (! Schema::hasColumn('purchase_orders', 'current_hash')) {
                $table->string('current_hash', 128)->nullable()->index();
            }

            if (! Schema::hasColumn('purchase_orders', 'approval_hash')) {
                $table->string('approval_hash', 128)->nullable()->index();
            }

            if (! Schema::hasColumn('purchase_orders', 'differs_from_request')) {
                $table->boolean('differs_from_request')->default(false);
            }

            if (! Schema::hasColumn('purchase_orders', 'approval_required_reason')) {
                $table->string('approval_required_reason', 255)->nullable();
            }

            if (! Schema::hasColumn('purchase_orders', 'submitted_for_approval_at')) {
                $table->dateTime('submitted_for_approval_at')->nullable();
            }

            if (! Schema::hasColumn('purchase_orders', 'confirmed_at')) {
                $table->dateTime('confirmed_at')->nullable();
            }

            if (! Schema::hasColumn('purchase_orders', 'confirmed_by_user_id')) {
                $table->unsignedBigInteger('confirmed_by_user_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach ([
                'source_snapshot_hash',
                'current_hash',
                'approval_hash',
                'differs_from_request',
                'approval_required_reason',
                'submitted_for_approval_at',
                'confirmed_at',
                'confirmed_by_user_id',
            ] as $column) {
                if (Schema::hasColumn('purchase_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
