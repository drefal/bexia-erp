<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_receipts')) {
            Schema::create('purchase_receipts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('purchase_order_id')->index();
                $table->string('number', 80)->index();
                $table->string('status', 50)->default('received')->index();
                $table->timestamp('received_at')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('received_by_user_id')->nullable()->index();
                $table->decimal('total_without_tax', 18, 6)->default(0);
                $table->decimal('total_tax', 18, 6)->default(0);
                $table->decimal('total_with_tax', 18, 6)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('purchase_receipt_lines')) {
            Schema::create('purchase_receipt_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_receipt_id')->index();
                $table->unsignedBigInteger('purchase_order_id')->index();
                $table->unsignedBigInteger('purchase_order_line_id')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
                $table->unsignedBigInteger('variant_id')->nullable()->index();
                $table->string('product_label', 500)->nullable();
                $table->string('variant_label', 500)->nullable();
                $table->string('purchase_unit_label', 120)->nullable();
                $table->decimal('received_quantity', 18, 6)->default(0);
                $table->decimal('received_base_quantity', 18, 6)->default(0);
                $table->decimal('unit_cost_without_tax', 18, 6)->default(0);
                $table->decimal('tax_rate', 10, 4)->default(0);
                $table->decimal('line_total_without_tax', 18, 6)->default(0);
                $table->decimal('line_tax', 18, 6)->default(0);
                $table->decimal('line_total_with_tax', 18, 6)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('purchase_order_lines')) {
            Schema::table('purchase_order_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_order_lines', 'received_quantity')) {
                    $table->decimal('received_quantity', 18, 6)->default(0)->after('ordered_quantity');
                }

                if (! Schema::hasColumn('purchase_order_lines', 'received_base_quantity')) {
                    $table->decimal('received_base_quantity', 18, 6)->default(0)->after('received_quantity');
                }

                if (! Schema::hasColumn('purchase_order_lines', 'receipt_status')) {
                    $table->string('receipt_status', 50)->nullable()->index()->after('received_base_quantity');
                }

                if (! Schema::hasColumn('purchase_order_lines', 'last_received_at')) {
                    $table->timestamp('last_received_at')->nullable()->after('receipt_status');
                }
            });
        }
    }

    public function down(): void
    {
        // No eliminamos columnas para no perder historial de recepciones.
    }
};
