<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * v5.54.1 - Núcleo de salida por número de serie.
         *
         * Prepara columnas para poder guardar qué número de serie específico
         * se usó en una salida, ya venga de:
         *
         * - PDV
         * - Entrega de venta
         * - Devolución
         * - Movimiento de inventario
         *
         * Esta migración no cambia pantallas ni lógica de cobro.
         */

        $this->addSerialColumnsToDocumentLine('pos_order_lines');
        $this->addSerialColumnsToDocumentLine('sale_delivery_lines');
        $this->addSerialColumnsToDocumentLine('pos_order_refund_lines');

        if (Schema::hasTable('stock_movement_lines')) {
            Schema::table('stock_movement_lines', function (Blueprint $table): void {
                if (! Schema::hasColumn('stock_movement_lines', 'stock_serial_number_id')) {
                    $table->foreignId('stock_serial_number_id')
                        ->nullable()
                        ->after('lot_id')
                        ->constrained('stock_serial_numbers')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('stock_movement_lines', 'source_type')) {
                    $table->string('source_type', 80)->nullable()->after('stock_serial_number_id')->index();
                }

                if (! Schema::hasColumn('stock_movement_lines', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->after('source_type')->index();
                }

                if (! Schema::hasColumn('stock_movement_lines', 'source_line_type')) {
                    $table->string('source_line_type', 80)->nullable()->after('source_id')->index();
                }

                if (! Schema::hasColumn('stock_movement_lines', 'source_line_id')) {
                    $table->unsignedBigInteger('source_line_id')->nullable()->after('source_line_type')->index();
                }
            });
        }

        if (Schema::hasTable('stock_serial_numbers')) {
            Schema::table('stock_serial_numbers', function (Blueprint $table): void {
                if (! Schema::hasColumn('stock_serial_numbers', 'out_stock_movement_line_id')) {
                    $table->foreignId('out_stock_movement_line_id')
                        ->nullable()
                        ->after('stock_movement_line_id')
                        ->constrained('stock_movement_lines')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('stock_serial_numbers', 'out_source_type')) {
                    $table->string('out_source_type', 80)->nullable()->after('out_stock_movement_line_id')->index();
                }

                if (! Schema::hasColumn('stock_serial_numbers', 'out_source_id')) {
                    $table->unsignedBigInteger('out_source_id')->nullable()->after('out_source_type')->index();
                }

                if (! Schema::hasColumn('stock_serial_numbers', 'out_source_line_type')) {
                    $table->string('out_source_line_type', 80)->nullable()->after('out_source_id')->index();
                }

                if (! Schema::hasColumn('stock_serial_numbers', 'out_source_line_id')) {
                    $table->unsignedBigInteger('out_source_line_id')->nullable()->after('out_source_line_type')->index();
                }

                if (! Schema::hasColumn('stock_serial_numbers', 'sold_at')) {
                    $table->timestamp('sold_at')->nullable()->after('out_source_line_id');
                }

                if (! Schema::hasColumn('stock_serial_numbers', 'sold_by')) {
                    $table->unsignedBigInteger('sold_by')->nullable()->after('sold_at')->index();
                }

                if (! Schema::hasColumn('stock_serial_numbers', 'returned_at')) {
                    $table->timestamp('returned_at')->nullable()->after('sold_by');
                }

                if (! Schema::hasColumn('stock_serial_numbers', 'returned_by')) {
                    $table->unsignedBigInteger('returned_by')->nullable()->after('returned_at')->index();
                }
            });
        }
    }

    public function down(): void
    {
        $this->dropSerialColumnsFromDocumentLine('pos_order_refund_lines');
        $this->dropSerialColumnsFromDocumentLine('sale_delivery_lines');
        $this->dropSerialColumnsFromDocumentLine('pos_order_lines');

        if (Schema::hasTable('stock_serial_numbers')) {
            Schema::table('stock_serial_numbers', function (Blueprint $table): void {
                $this->dropForeignIfExists($table, 'stock_serial_numbers', 'out_stock_movement_line_id');

                $this->dropColumnIfExists($table, 'stock_serial_numbers', 'returned_by');
                $this->dropColumnIfExists($table, 'stock_serial_numbers', 'returned_at');
                $this->dropColumnIfExists($table, 'stock_serial_numbers', 'sold_by');
                $this->dropColumnIfExists($table, 'stock_serial_numbers', 'sold_at');
                $this->dropColumnIfExists($table, 'stock_serial_numbers', 'out_source_line_id');
                $this->dropColumnIfExists($table, 'stock_serial_numbers', 'out_source_line_type');
                $this->dropColumnIfExists($table, 'stock_serial_numbers', 'out_source_id');
                $this->dropColumnIfExists($table, 'stock_serial_numbers', 'out_source_type');
                $this->dropColumnIfExists($table, 'stock_serial_numbers', 'out_stock_movement_line_id');
            });
        }

        if (Schema::hasTable('stock_movement_lines')) {
            Schema::table('stock_movement_lines', function (Blueprint $table): void {
                $this->dropForeignIfExists($table, 'stock_movement_lines', 'stock_serial_number_id');

                $this->dropColumnIfExists($table, 'stock_movement_lines', 'source_line_id');
                $this->dropColumnIfExists($table, 'stock_movement_lines', 'source_line_type');
                $this->dropColumnIfExists($table, 'stock_movement_lines', 'source_id');
                $this->dropColumnIfExists($table, 'stock_movement_lines', 'source_type');
                $this->dropColumnIfExists($table, 'stock_movement_lines', 'stock_serial_number_id');
            });
        }
    }

    private function addSerialColumnsToDocumentLine(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'stock_serial_number_id')) {
                $table->foreignId('stock_serial_number_id')
                    ->nullable()
                    ->after('product_variant_id')
                    ->constrained('stock_serial_numbers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn($tableName, 'serial_tracking_metadata')) {
                $table->json('serial_tracking_metadata')->nullable()->after('stock_serial_number_id');
            }
        });
    }

    private function dropSerialColumnsFromDocumentLine(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            $this->dropForeignIfExists($table, $tableName, 'stock_serial_number_id');
            $this->dropColumnIfExists($table, $tableName, 'serial_tracking_metadata');
            $this->dropColumnIfExists($table, $tableName, 'stock_serial_number_id');
        });
    }

    private function dropColumnIfExists(Blueprint $table, string $tableName, string $column): void
    {
        if (Schema::hasColumn($tableName, $column)) {
            $table->dropColumn($column);
        }
    }

    private function dropForeignIfExists(Blueprint $table, string $tableName, string $column): void
    {
        if (! Schema::hasColumn($tableName, $column)) {
            return;
        }

        try {
            $table->dropForeign([$column]);
        } catch (Throwable $e) {
            // Evita romper rollback si el constraint tiene nombre distinto.
        }
    }
};
