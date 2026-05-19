<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_lots')) {
            Schema::create('stock_lots', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
                $table->string('lot_number', 120);
                $table->date('expiration_date')->nullable()->index();
                $table->unsignedBigInteger('supplier_contact_id')->nullable()->index();
                $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
                $table->unsignedBigInteger('purchase_receipt_id')->nullable()->index();
                $table->string('status', 40)->default('available')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
                $table->foreign('product_variant_id')->references('id')->on('products')->nullOnDelete();
            });
        }

        $this->addCheckConstraint(
            'stock_lots',
            'stock_lots_status_allowed_values_check',
            "status IN ('available', 'reserved', 'depleted', 'blocked', 'expired', 'scrapped')"
        );

        $this->addUniqueIndex(
            'stock_lots_product_lot_unique',
            "CREATE UNIQUE INDEX stock_lots_product_lot_unique ON stock_lots (company_id, product_id, COALESCE(product_variant_id, 0), lot_number)"
        );

        if (! Schema::hasTable('stock_serial_numbers')) {
            Schema::create('stock_serial_numbers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
                $table->unsignedBigInteger('lot_id')->nullable()->index();
                $table->string('serial_number', 160);
                $table->unsignedBigInteger('current_warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('current_location_id')->nullable()->index();
                $table->string('status', 40)->default('available')->index();
                $table->string('source_type', 80)->nullable()->index();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
                $table->unsignedBigInteger('purchase_receipt_id')->nullable()->index();
                $table->unsignedBigInteger('stock_movement_line_id')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
                $table->foreign('product_variant_id')->references('id')->on('products')->nullOnDelete();
                $table->foreign('lot_id')->references('id')->on('stock_lots')->nullOnDelete();
                $table->foreign('current_warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
                $table->foreign('current_location_id')->references('id')->on('stock_locations')->nullOnDelete();
            });
        }

        $this->addCheckConstraint(
            'stock_serial_numbers',
            'stock_serial_numbers_status_allowed_values_check',
            "status IN ('available', 'reserved', 'sold', 'delivered', 'consumed', 'returned', 'blocked', 'scrapped', 'lost')"
        );

        $this->addUniqueIndex(
            'stock_serial_numbers_product_serial_unique',
            "CREATE UNIQUE INDEX stock_serial_numbers_product_serial_unique ON stock_serial_numbers (company_id, product_id, COALESCE(product_variant_id, 0), serial_number)"
        );

        $this->addLotForeignIfPossible('stock_quants', 'lot_id', 'stock_quants_lot_id_foreign');
        $this->addLotForeignIfPossible('stock_movement_lines', 'lot_id', 'stock_movement_lines_lot_id_foreign');
        $this->addLotForeignIfPossible('stock_reservations', 'lot_id', 'stock_reservations_lot_id_foreign');
        $this->addLotForeignIfPossible('stock_adjustment_lines', 'lot_id', 'stock_adjustment_lines_lot_id_foreign');
    }

    public function down(): void
    {
        $this->dropConstraintIfExists('stock_adjustment_lines', 'stock_adjustment_lines_lot_id_foreign');
        $this->dropConstraintIfExists('stock_reservations', 'stock_reservations_lot_id_foreign');
        $this->dropConstraintIfExists('stock_movement_lines', 'stock_movement_lines_lot_id_foreign');
        $this->dropConstraintIfExists('stock_quants', 'stock_quants_lot_id_foreign');

        Schema::dropIfExists('stock_serial_numbers');
        Schema::dropIfExists('stock_lots');
    }

    protected function addLotForeignIfPossible(string $table, string $column, string $constraint): void
    {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $column)
            || ! Schema::hasTable('stock_lots')
            || $this->constraintExists($table, $constraint)
        ) {
            return;
        }

        DB::statement("
            UPDATE {$table}
            SET {$column} = NULL
            WHERE {$column} IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM stock_lots
                  WHERE stock_lots.id = {$table}.{$column}
              )
        ");

        DB::statement("
            ALTER TABLE {$table}
            ADD CONSTRAINT {$constraint}
            FOREIGN KEY ({$column})
            REFERENCES stock_lots(id)
            ON DELETE SET NULL
        ");
    }

    protected function addCheckConstraint(string $table, string $constraint, string $sql): void
    {
        if (! Schema::hasTable($table) || $this->constraintExists($table, $constraint)) {
            return;
        }

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$sql})");
    }

    protected function addUniqueIndex(string $index, string $sql): void
    {
        if ($this->indexExists($index)) {
            return;
        }

        DB::statement($sql);
    }

    protected function dropConstraintIfExists(string $table, string $constraint): void
    {
        if (! Schema::hasTable($table) || ! $this->constraintExists($table, $constraint)) {
            return;
        }

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
    }

    protected function constraintExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->exists();
    }

    protected function indexExists(string $index): bool
    {
        return DB::table('pg_indexes')
            ->where('indexname', $index)
            ->exists();
    }
};
