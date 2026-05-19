<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $pgsqlIndexName = 'products_company_internal_reference_unique_v5550';
    protected string $mysqlIndexName = 'products_company_internal_reference_unique_v5550';

    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'internal_reference')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("
                CREATE UNIQUE INDEX IF NOT EXISTS {$this->pgsqlIndexName}
                ON products (
                    COALESCE(company_id, 0),
                    LOWER(TRIM(internal_reference))
                )
                WHERE internal_reference IS NOT NULL
                  AND TRIM(internal_reference) <> ''
            ");

            return;
        }

        if ($driver === 'mysql') {
            $exists = collect(DB::select("SHOW INDEX FROM products WHERE Key_name = ?", [$this->mysqlIndexName]))->isNotEmpty();

            if (! $exists) {
                DB::statement("
                    CREATE UNIQUE INDEX {$this->mysqlIndexName}
                    ON products (company_id, internal_reference)
                ");
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS {$this->pgsqlIndexName}");
            return;
        }

        if ($driver === 'mysql') {
            $exists = collect(DB::select("SHOW INDEX FROM products WHERE Key_name = ?", [$this->mysqlIndexName]))->isNotEmpty();

            if ($exists) {
                DB::statement("DROP INDEX {$this->mysqlIndexName} ON products");
            }
        }
    }
};
