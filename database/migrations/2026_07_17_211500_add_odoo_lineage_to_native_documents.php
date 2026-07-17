<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $targets = [
        'sales_orders' => ['source_id', true],
        'sales_order_lines' => ['source_line_id', true],
        'purchase_orders' => ['source_id', true],
        'purchase_order_lines' => ['source_line_id', true],
        'invoices' => ['source_id', true],
        'invoice_lines' => ['source_line_id', true],
        'pos_orders' => ['source_id', true],
        'pos_order_lines' => ['source_line_id', false],
        'pos_order_payments' => ['source_id', false],
        'account_receivable_payments' => ['source_id', true],
        'account_payable_payments' => ['source_id', true],
    ];

    public function up(): void
    {
        foreach (
            $this->targets
            as $tableName => [$identityColumn, $hasCompanyId]
        ) {
            $qualifiedTable = 'bexia.'.$tableName;

            DB::statement(
                "ALTER TABLE {$qualifiedTable}
                 ADD COLUMN IF NOT EXISTS
                 {$identityColumn} bigint NULL"
            );

            DB::statement(
                "ALTER TABLE {$qualifiedTable}
                 ADD COLUMN IF NOT EXISTS
                 source_system varchar(50) NULL"
            );

            DB::statement(
                "ALTER TABLE {$qualifiedTable}
                 ADD COLUMN IF NOT EXISTS
                 source_model varchar(120) NULL"
            );

            DB::statement(
                "ALTER TABLE {$qualifiedTable}
                 ADD COLUMN IF NOT EXISTS
                 source_reference varchar(255) NULL"
            );

            DB::statement(
                "ALTER TABLE {$qualifiedTable}
                 ADD COLUMN IF NOT EXISTS
                 legacy_company_id bigint NULL"
            );

            DB::statement(
                "ALTER TABLE {$qualifiedTable}
                 ADD COLUMN IF NOT EXISTS
                 migration_batch_id varchar(120) NULL"
            );

            DB::statement(
                "ALTER TABLE {$qualifiedTable}
                 ADD COLUMN IF NOT EXISTS
                 migrated_at timestamp(0)
                 without time zone NULL"
            );

            DB::statement(
                "ALTER TABLE {$qualifiedTable}
                 ADD COLUMN IF NOT EXISTS
                 is_legacy boolean
                 NOT NULL DEFAULT false"
            );

            DB::statement(
                "ALTER TABLE {$qualifiedTable}
                 ADD COLUMN IF NOT EXISTS
                 locked boolean
                 NOT NULL DEFAULT false"
            );

            DB::statement(
                "CREATE UNIQUE INDEX IF NOT EXISTS
                 {$tableName}_odoo_lineage_uidx
                 ON {$qualifiedTable}
                 (
                     source_system,
                     source_model,
                     {$identityColumn}
                 )
                 WHERE source_system IS NOT NULL
                   AND source_model IS NOT NULL
                   AND {$identityColumn} IS NOT NULL"
            );

            DB::statement(
                "CREATE INDEX IF NOT EXISTS
                 {$tableName}_migration_batch_idx
                 ON {$qualifiedTable}
                 (migration_batch_id)
                 WHERE migration_batch_id IS NOT NULL"
            );

            if ($hasCompanyId) {
                DB::statement(
                    "CREATE INDEX IF NOT EXISTS
                     {$tableName}_company_legacy_idx
                     ON {$qualifiedTable}
                     (company_id, is_legacy)
                     WHERE is_legacy = true"
                );
            }
        }
    }

    public function down(): void
    {
        foreach (
            $this->targets
            as $tableName => [$identityColumn, $hasCompanyId]
        ) {
            DB::statement(
                "DROP INDEX IF EXISTS
                 bexia.{$tableName}_odoo_lineage_uidx"
            );

            DB::statement(
                "DROP INDEX IF EXISTS
                 bexia.{$tableName}_migration_batch_idx"
            );

            if ($hasCompanyId) {
                DB::statement(
                    "DROP INDEX IF EXISTS
                     bexia.{$tableName}_company_legacy_idx"
                );
            }
        }

        /*
         * Las columnas se conservan para evitar borrar
         * linaje y auditoría de documentos históricos.
         */
    }
};
