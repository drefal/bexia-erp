<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_receipts')) {
            Schema::table('purchase_receipts', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_receipts', 'stock_quant_posted_at')) {
                    $table->timestamp('stock_quant_posted_at')->nullable()->after('inventory_posted_at');
                }
            });
        }

        if (Schema::hasTable('purchase_receipt_lines')) {
            Schema::table('purchase_receipt_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_receipt_lines', 'stock_quant_posted_at')) {
                    $table->timestamp('stock_quant_posted_at')->nullable()->after('inventory_posted_at');
                }
            });
        }
    }

    public function down(): void
    {
        // No revertimos para no perder trazabilidad.
    }
};
