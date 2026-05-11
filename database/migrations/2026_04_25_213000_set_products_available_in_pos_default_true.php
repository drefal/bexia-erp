<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'available_in_pos')) {
            return;
        }

        DB::statement('ALTER TABLE products ALTER COLUMN available_in_pos SET DEFAULT true');
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'available_in_pos')) {
            return;
        }

        DB::statement('ALTER TABLE products ALTER COLUMN available_in_pos SET DEFAULT false');
    }
};
