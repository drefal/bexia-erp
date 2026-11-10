<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $index = DB::selectOne("
            SELECT indexdef
            FROM pg_indexes
            WHERE schemaname = 'bexia'
              AND tablename = 'expense_report_lines'
              AND indexname = 'expense_report_lines_cfdi_uuid_unique'
        ");

        if (
            ! $index
            || ! str_contains(
                (string) $index->indexdef,
                'cfdi_uuid_active'
            )
        ) {
            throw new RuntimeException(
                'GASTOS11N debe estar aplicada primero.'
            );
        }

        $existing = DB::selectOne("
            SELECT COUNT(*) AS total
            FROM pg_trigger
            WHERE tgrelid = 'bexia.expense_report_lines'::regclass
              AND tgname = 'expense_report_line_cfdi_active_trg'
              AND NOT tgisinternal
        ");

        if ((int) $existing->total !== 0) {
            throw new RuntimeException(
                'El trigger de lineas ya existe.'
            );
        }

        DB::unprepared(<<<'SQL'
CREATE FUNCTION bexia.expense_report_line_set_cfdi_active()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    parent_status text;
BEGIN
    /*
     * Bloquea la fila padre mientras se determina su estado.
     * Esto evita guardar una linea usando un estado obsoleto
     * durante una cancelacion concurrente.
     */
    SELECT r.status
    INTO parent_status
    FROM bexia.expense_reports AS r
    WHERE r.id = NEW.expense_report_id
    FOR SHARE;

    IF NOT FOUND THEN
        RAISE EXCEPTION
            'No existe la comprobacion %',
            NEW.expense_report_id;
    END IF;

    NEW.cfdi_uuid_active :=
        (parent_status <> 'cancelled');

    RETURN NEW;
END;
$$
SQL);

        DB::statement("
            CREATE TRIGGER expense_report_line_cfdi_active_trg
            BEFORE INSERT OR UPDATE
            ON bexia.expense_report_lines
            FOR EACH ROW
            EXECUTE FUNCTION
                bexia.expense_report_line_set_cfdi_active()
        ");
    }

    public function down(): void
    {
        DB::statement("
            DROP TRIGGER IF EXISTS
                expense_report_line_cfdi_active_trg
            ON bexia.expense_report_lines
        ");

        DB::statement("
            DROP FUNCTION IF EXISTS
                bexia.expense_report_line_set_cfdi_active()
        ");
    }
};
