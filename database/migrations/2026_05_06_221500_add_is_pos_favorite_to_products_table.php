<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        if (! Schema::hasColumn('products', 'is_pos_favorite')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('is_pos_favorite')
                    ->default(false)
                    ->after('available_in_pos');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        if (Schema::hasColumn('products', 'is_pos_favorite')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('is_pos_favorite');
            });
        }
    }
};
