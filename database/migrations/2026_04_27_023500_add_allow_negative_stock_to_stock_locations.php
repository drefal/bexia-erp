<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_locations')) {
            return;
        }

        Schema::table('stock_locations', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_locations', 'allow_negative_stock')) {
                $table->boolean('allow_negative_stock')
                    ->default(false)
                    ->after('is_active');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_locations')) {
            return;
        }

        Schema::table('stock_locations', function (Blueprint $table): void {
            if (Schema::hasColumn('stock_locations', 'allow_negative_stock')) {
                $table->dropColumn('allow_negative_stock');
            }
        });
    }
};
