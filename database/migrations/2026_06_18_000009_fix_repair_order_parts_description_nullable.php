<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repair_order_parts')) {
            return;
        }

        if (Schema::hasColumn('repair_order_parts', 'description')) {
            DB::statement('ALTER TABLE repair_order_parts ALTER COLUMN description DROP NOT NULL');
        }
    }

    public function down(): void
    {
        // No se revierte a NOT NULL para evitar romper líneas manuales existentes.
    }
};
