<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('odoo_history_raw_records')) {
            return;
        }

        DB::statement('ALTER TABLE odoo_history_raw_records ALTER COLUMN record_name TYPE text');
    }

    public function down(): void
    {
        // No destructive rollback.
    }
};
