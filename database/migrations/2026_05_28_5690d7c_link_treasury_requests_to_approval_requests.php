<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('treasury_cash_transfer_requests')) {
            return;
        }

        Schema::table('treasury_cash_transfer_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('treasury_cash_transfer_requests', 'approval_request_id')) {
                $table->unsignedBigInteger('approval_request_id')->nullable()->index();
            }

            if (! Schema::hasColumn('treasury_cash_transfer_requests', 'approval_status')) {
                $table->string('approval_status', 40)->nullable()->index();
            }

            if (! Schema::hasColumn('treasury_cash_transfer_requests', 'approval_requested_at')) {
                $table->timestamp('approval_requested_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('treasury_cash_transfer_requests')) {
            return;
        }

        Schema::table('treasury_cash_transfer_requests', function (Blueprint $table): void {
            foreach (['approval_requested_at', 'approval_status', 'approval_request_id'] as $column) {
                if (Schema::hasColumn('treasury_cash_transfer_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
