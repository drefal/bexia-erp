<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchase_receipt_lines', 'stock_lots', 'stock_serial_numbers'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'motor_number')) {
                    $table->text('motor_number')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'customs_entry_number')) {
                    $table->string('customs_entry_number', 120)->nullable();
                }

                if (! Schema::hasColumn($tableName, 'customs_entry_date')) {
                    $table->date('customs_entry_date')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'customs_office')) {
                    $table->string('customs_office', 160)->nullable();
                }

                if (! Schema::hasColumn($tableName, 'imported_model')) {
                    $table->string('imported_model', 160)->nullable();
                }

                if (! Schema::hasColumn($tableName, 'imported_color')) {
                    $table->string('imported_color', 160)->nullable();
                }

                if (! Schema::hasColumn($tableName, 'import_document_reference')) {
                    $table->string('import_document_reference', 180)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['purchase_receipt_lines', 'stock_lots', 'stock_serial_numbers'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach ([
                    'import_document_reference',
                    'imported_color',
                    'imported_model',
                    'customs_office',
                    'customs_entry_date',
                    'customs_entry_number',
                    'motor_number',
                ] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
