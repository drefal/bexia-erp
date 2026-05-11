<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('purchase_requests')
            && ! Schema::hasColumn('purchase_requests', 'duplicated_from_purchase_request_id')
        ) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('duplicated_from_purchase_request_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('purchase_requests')
            && Schema::hasColumn('purchase_requests', 'duplicated_from_purchase_request_id')
        ) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->dropColumn('duplicated_from_purchase_request_id');
            });
        }
    }
};
