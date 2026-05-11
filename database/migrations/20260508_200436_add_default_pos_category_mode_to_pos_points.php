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

        Schema::table('pos_points', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_points', 'default_pos_category_mode')) {
                $table->string('default_pos_category_mode', 30)
                    ->nullable()
                    ->after('initial_category_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_points')) {
            return;
        }

        Schema::table('pos_points', function (Blueprint $table) {
            if (Schema::hasColumn('pos_points', 'default_pos_category_mode')) {
                $table->dropColumn('default_pos_category_mode');
            }
        });
    }
};
