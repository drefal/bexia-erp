<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_points')) {
            return;
        }

        if (! Schema::hasColumn('pos_points', 'session_close_format')) {
            Schema::table('pos_points', function (Blueprint $table) {
                $table->string('session_close_format', 40)->default('generic');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_points')) {
            return;
        }

        if (Schema::hasColumn('pos_points', 'session_close_format')) {
            Schema::table('pos_points', function (Blueprint $table) {
                $table->dropColumn('session_close_format');
            });
        }
    }
};
