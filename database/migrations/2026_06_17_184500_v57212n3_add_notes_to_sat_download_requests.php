<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sat_download_requests')) {
            return;
        }

        Schema::table('sat_download_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('sat_download_requests', 'notes')) {
                $table->text('notes')->nullable()->after('request_kind');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sat_download_requests')) {
            return;
        }

        Schema::table('sat_download_requests', function (Blueprint $table) {
            if (Schema::hasColumn('sat_download_requests', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
