<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'tracking')) {
            return;
        }

        DB::table('products')
            ->where(function ($query): void {
                $query
                    ->whereNull('tracking')
                    ->orWhere('tracking', '')
                    ->orWhereNotIn('tracking', ['none', 'lot', 'serial']);
            })
            ->update(['tracking' => 'none']);

        try {
            DB::statement("ALTER TABLE products ALTER COLUMN tracking SET DEFAULT 'none'");
        } catch (\Throwable $e) {
            //
        }

        try {
            DB::statement("ALTER TABLE products ADD CONSTRAINT products_tracking_allowed_values_check CHECK (tracking IN ('none', 'lot', 'serial'))");
        } catch (\Throwable $e) {
            //
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_tracking_allowed_values_check');
        } catch (\Throwable $e) {
            //
        }
    }
};
