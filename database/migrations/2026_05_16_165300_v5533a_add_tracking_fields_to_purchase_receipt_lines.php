<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_receipt_lines')) {
            return;
        }

        Schema::table('purchase_receipt_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_receipt_lines', 'lot_id')) {
                $table->unsignedBigInteger('lot_id')->nullable()->index();
            }

            if (! Schema::hasColumn('purchase_receipt_lines', 'lot_number')) {
                $table->string('lot_number', 120)->nullable()->index();
            }

            if (! Schema::hasColumn('purchase_receipt_lines', 'lot_expiration_date')) {
                $table->date('lot_expiration_date')->nullable()->index();
            }

            if (! Schema::hasColumn('purchase_receipt_lines', 'serial_numbers')) {
                $table->json('serial_numbers')->nullable();
            }

            if (! Schema::hasColumn('purchase_receipt_lines', 'tracking_type')) {
                $table->string('tracking_type', 20)->default('none')->index();
            }
        });

        if (
            Schema::hasColumn('purchase_receipt_lines', 'lot_id')
            && Schema::hasTable('stock_lots')
            && ! $this->constraintExists('purchase_receipt_lines', 'purchase_receipt_lines_lot_id_foreign')
        ) {
            DB::statement("
                ALTER TABLE purchase_receipt_lines
                ADD CONSTRAINT purchase_receipt_lines_lot_id_foreign
                FOREIGN KEY (lot_id)
                REFERENCES stock_lots(id)
                ON DELETE SET NULL
            ");
        }

        if (
            Schema::hasColumn('purchase_receipt_lines', 'tracking_type')
            && ! $this->constraintExists('purchase_receipt_lines', 'purchase_receipt_lines_tracking_type_check')
        ) {
            DB::statement("
                ALTER TABLE purchase_receipt_lines
                ADD CONSTRAINT purchase_receipt_lines_tracking_type_check
                CHECK (tracking_type IN ('none', 'lot', 'serial'))
            ");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_receipt_lines')) {
            return;
        }

        $this->dropConstraintIfExists('purchase_receipt_lines', 'purchase_receipt_lines_lot_id_foreign');
        $this->dropConstraintIfExists('purchase_receipt_lines', 'purchase_receipt_lines_tracking_type_check');

        Schema::table('purchase_receipt_lines', function (Blueprint $table): void {
            foreach ([
                'tracking_type',
                'serial_numbers',
                'lot_expiration_date',
                'lot_number',
                'lot_id',
            ] as $column) {
                if (Schema::hasColumn('purchase_receipt_lines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    protected function constraintExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->exists();
    }

    protected function dropConstraintIfExists(string $table, string $constraint): void
    {
        if ($this->constraintExists($table, $constraint)) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
        }
    }
};
