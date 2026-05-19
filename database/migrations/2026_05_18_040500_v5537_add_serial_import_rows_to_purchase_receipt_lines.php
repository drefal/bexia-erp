<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_receipt_lines')) {
            return;
        }

        Schema::table('purchase_receipt_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_receipt_lines', 'serial_import_rows')) {
                $table->json('serial_import_rows')->nullable()->after('serial_numbers');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_receipt_lines')) {
            return;
        }

        Schema::table('purchase_receipt_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_receipt_lines', 'serial_import_rows')) {
                $table->dropColumn('serial_import_rows');
            }
        });
    }
};
