<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS
    stock_quants_logical_unique
ON bexia.stock_quants (
    company_id,
    warehouse_id,
    location_id,
    product_id,
    COALESCE(product_variant_id, 0),
    COALESCE(lot_id, 0)
)
SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS bexia.stock_quants_logical_unique'
        );
    }
};
