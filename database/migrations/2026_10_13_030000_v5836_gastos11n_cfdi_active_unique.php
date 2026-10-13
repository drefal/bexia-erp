<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $oldIndex = DB::selectOne("
            SELECT indexdef
            FROM pg_indexes
            WHERE schemaname = 'bexia'
              AND tablename = 'expense_report_lines'
              AND indexname = 'expense_report_lines_cfdi_uuid_unique'
        ");

        if (
            ! $oldIndex ||
            ! str_contains(
                (string) $oldIndex->indexdef,
                'WHERE (cfdi_uuid IS NOT NULL)'
            )
        ) {
            throw new RuntimeException(
                'El indice original no coincide con lo auditado.'
            );
        }

        DB::statement("
            ALTER TABLE bexia.expense_report_lines
            ADD COLUMN cfdi_uuid_active BOOLEAN NOT NULL DEFAULT TRUE
        ");

        DB::statement("
            UPDATE bexia.expense_report_lines AS l
            SET cfdi_uuid_active = FALSE
            FROM bexia.expense_reports AS r
            WHERE r.id = l.expense_report_id
              AND r.status = 'cancelled'
        ");

        /*
         * Mantener sincronizadas las lineas cuando el reporte
         * cambia de estado por cualquier flujo autorizado.
         */
        DB::unprepared(<<<'SQL'
CREATE FUNCTION bexia.expense_report_sync_cfdi_uuid_active()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.status IS DISTINCT FROM NEW.status THEN
        UPDATE bexia.expense_report_lines
        SET cfdi_uuid_active = (NEW.status <> 'cancelled')
        WHERE expense_report_id = NEW.id;
    END IF;

    RETURN NEW;
END;
$$
SQL);

        DB::statement("
            CREATE TRIGGER expense_report_cfdi_active_status_trg
            AFTER UPDATE OF status
            ON bexia.expense_reports
            FOR EACH ROW
            EXECUTE FUNCTION bexia.expense_report_sync_cfdi_uuid_active()
        ");

        DB::statement("
            DROP INDEX bexia.expense_report_lines_cfdi_uuid_unique
        ");

        DB::statement("
            CREATE UNIQUE INDEX expense_report_lines_cfdi_uuid_unique
            ON bexia.expense_report_lines (cfdi_uuid)
            WHERE cfdi_uuid IS NOT NULL
              AND cfdi_uuid_active = TRUE
        ");
    }

    public function down(): void
    {
        /*
         * No permitir volver al indice global si ya existen
         * UUID historicos repetidos entre comprobaciones
         * canceladas y activas.
         */
        $duplicates = DB::selectOne("
            SELECT COUNT(*) AS total
            FROM (
                SELECT cfdi_uuid
                FROM bexia.expense_report_lines
                WHERE cfdi_uuid IS NOT NULL
                GROUP BY cfdi_uuid
                HAVING COUNT(*) > 1
            ) AS duplicates
        ");

        if ((int) $duplicates->total > 0) {
            throw new RuntimeException(
                'No puede revertirse el indice global: '
                . 'existen UUID historicos repetidos.'
            );
        }

        DB::statement("
            DROP TRIGGER IF EXISTS
                expense_report_cfdi_active_status_trg
            ON bexia.expense_reports
        ");

        DB::statement("
            DROP FUNCTION IF EXISTS
                bexia.expense_report_sync_cfdi_uuid_active()
        ");

        DB::statement("
            DROP INDEX IF EXISTS
                bexia.expense_report_lines_cfdi_uuid_unique
        ");

        DB::statement("
            CREATE UNIQUE INDEX expense_report_lines_cfdi_uuid_unique
            ON bexia.expense_report_lines (cfdi_uuid)
            WHERE cfdi_uuid IS NOT NULL
        ");

        DB::statement("
            ALTER TABLE bexia.expense_report_lines
            DROP COLUMN cfdi_uuid_active
        ");
    }
};
