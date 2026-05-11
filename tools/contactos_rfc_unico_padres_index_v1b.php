<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! Schema::hasTable('contacts')) {
    dump('No existe contacts.');
    return;
}

if (! Schema::hasColumn('contacts', 'deleted_at')) {
    dump('No existe deleted_at. No se creó índice único.');
    return;
}

try {
    DB::statement("
        CREATE UNIQUE INDEX IF NOT EXISTS contacts_company_rfc_unique_active_main_idx
        ON contacts (COALESCE(company_id, 0), upper(trim(rfc)))
        WHERE rfc IS NOT NULL
          AND trim(rfc) <> ''
          AND parent_contact_id IS NULL
          AND address_type = 'main'
          AND deleted_at IS NULL
    ");

    dump('indice_unico_rfc_ok');
} catch (Throwable $e) {
    dump('ERROR_indice_unico_rfc', $e->getMessage());
}
