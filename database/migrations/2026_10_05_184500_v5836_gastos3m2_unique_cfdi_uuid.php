<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Un CFDI solamente puede utilizarse una vez.
         * PostgreSQL permite múltiples NULL en un índice UNIQUE.
         */

        DB::statement(
            'DROP INDEX IF EXISTS '
            . 'bexia.expense_report_lines_cfdi_uuid_idx'
        );

        DB::statement(
            'CREATE UNIQUE INDEX '
            . 'expense_report_lines_cfdi_uuid_unique '
            . 'ON expense_report_lines (cfdi_uuid) '
            . 'WHERE cfdi_uuid IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS '
            . 'bexia.expense_report_lines_cfdi_uuid_unique'
        );

        DB::statement(
            'CREATE INDEX '
            . 'expense_report_lines_cfdi_uuid_idx '
            . 'ON expense_report_lines (cfdi_uuid)'
        );
    }
};
