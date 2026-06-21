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

        Schema::table('repair_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('repair_orders', 'public_tracking_token')) {
                $table->string('public_tracking_token', 80)->nullable()->unique()->after('metadata');
            }

            if (! Schema::hasColumn('repair_orders', 'public_tracking_enabled')) {
                $table->boolean('public_tracking_enabled')->default(true)->after('public_tracking_token');
            }

            if (! Schema::hasColumn('repair_orders', 'public_tracking_token_created_at')) {
                $table->timestamp('public_tracking_token_created_at')->nullable()->after('public_tracking_enabled');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('repair_orders')) {
            return;
        }

        Schema::table('repair_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('repair_orders', 'public_tracking_token_created_at')) {
                $table->dropColumn('public_tracking_token_created_at');
            }

            if (Schema::hasColumn('repair_orders', 'public_tracking_enabled')) {
                $table->dropColumn('public_tracking_enabled');
            }

            if (Schema::hasColumn('repair_orders', 'public_tracking_token')) {
                $table->dropColumn('public_tracking_token');
            }
        });
    }
};
