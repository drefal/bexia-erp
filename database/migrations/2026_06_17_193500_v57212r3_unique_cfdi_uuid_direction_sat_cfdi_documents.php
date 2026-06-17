<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sat_cfdi_documents')) {
            return;
        }

        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS sat_cfdi_documents_company_uuid_direction_unique
            ON sat_cfdi_documents (company_id, upper(uuid), coalesce(direction, ''))
            WHERE uuid IS NOT NULL AND btrim(uuid) <> ''
        ");
    }

    public function down(): void
    {
        DB::statement("
            DROP INDEX IF EXISTS sat_cfdi_documents_company_uuid_direction_unique
        ");
    }
};
