<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Sólo puede existir un precio ACTUAL por:
         *
         * empresa + tamaño
         *
         * Los precios históricos continúan permitidos porque
         * tendrán valid_to distinto de NULL.
         */
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX
                cb_prices_one_current_uq
            ON custom_balloon_prices (
                company_id,
                size_id
            )
            WHERE valid_to IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS cb_prices_one_current_uq'
        );
    }
};
