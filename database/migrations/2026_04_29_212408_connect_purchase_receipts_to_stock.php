<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_movement_lines')) {
            Schema::create('stock_movement_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_movement_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('source_location_id')->nullable()->index();
                $table->unsignedBigInteger('destination_location_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
                $table->unsignedBigInteger('variant_id')->nullable()->index();
                $table->decimal('quantity', 18, 6)->default(0);
                $table->decimal('unit_cost_without_tax', 18, 6)->default(0);
                $table->decimal('total_cost_without_tax', 18, 6)->default(0);
                $table->string('reference', 120)->nullable()->index();
                $table->string('origin_document', 160)->nullable()->index();
                $table->unsignedBigInteger('purchase_receipt_id')->nullable()->index();
                $table->unsignedBigInteger('purchase_receipt_line_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'product_variant_id']);
            });
        }

        if (! Schema::hasTable('stock_balances')) {
            Schema::create('stock_balances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
                $table->unsignedBigInteger('variant_id')->nullable()->index();
                $table->decimal('quantity', 18, 6)->default(0);
                $table->decimal('average_cost_without_tax', 18, 6)->default(0);
                $table->decimal('total_cost_without_tax', 18, 6)->default(0);
                $table->timestamp('last_movement_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'warehouse_id', 'location_id']);
                $table->index(['product_id', 'product_variant_id']);
            });
        }

        if (Schema::hasTable('purchase_receipts')) {
            Schema::table('purchase_receipts', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_receipts', 'stock_movement_id')) {
                    $table->unsignedBigInteger('stock_movement_id')->nullable()->index()->after('received_by_user_id');
                }

                if (! Schema::hasColumn('purchase_receipts', 'inventory_posted_at')) {
                    $table->timestamp('inventory_posted_at')->nullable()->after('stock_movement_id');
                }
            });
        }

        if (Schema::hasTable('purchase_receipt_lines')) {
            Schema::table('purchase_receipt_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_receipt_lines', 'stock_movement_line_id')) {
                    $table->unsignedBigInteger('stock_movement_line_id')->nullable()->index()->after('purchase_receipt_line_id');
                }

                if (! Schema::hasColumn('purchase_receipt_lines', 'inventory_posted_at')) {
                    $table->timestamp('inventory_posted_at')->nullable()->after('stock_movement_line_id');
                }
            });
        }
    }

    public function down(): void
    {
        // No eliminamos tablas/campos para no perder historial de inventario.
    }
};
