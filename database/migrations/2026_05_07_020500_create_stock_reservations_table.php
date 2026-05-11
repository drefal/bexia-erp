<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_reservations')) {
            Schema::create('stock_reservations', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();

                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
                $table->unsignedBigInteger('lot_id')->nullable()->index();

                $table->string('source_type')->default('pos_order')->index();
                $table->unsignedBigInteger('source_id')->index();

                $table->unsignedBigInteger('pos_order_id')->nullable()->index();
                $table->unsignedBigInteger('pos_order_line_id')->nullable()->index();

                $table->decimal('quantity', 18, 6)->default(0);
                $table->string('status')->default('active')->index();

                $table->timestamp('reserved_at')->nullable()->index();
                $table->timestamp('released_at')->nullable()->index();
                $table->string('released_reason')->nullable();

                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index([
                    'company_id',
                    'warehouse_id',
                    'location_id',
                    'product_id',
                    'product_variant_id',
                    'lot_id',
                    'status',
                ], 'stock_reservations_scope_status_idx');

                $table->index([
                    'source_type',
                    'source_id',
                    'status',
                ], 'stock_reservations_source_status_idx');
            });

            return;
        }

        Schema::table('stock_reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_reservations', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            }

            if (! Schema::hasColumn('stock_reservations', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
            }

            if (! Schema::hasColumn('stock_reservations', 'location_id')) {
                $table->unsignedBigInteger('location_id')->nullable()->index();
            }

            if (! Schema::hasColumn('stock_reservations', 'product_id')) {
                $table->unsignedBigInteger('product_id')->index();
            }

            if (! Schema::hasColumn('stock_reservations', 'product_variant_id')) {
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
            }

            if (! Schema::hasColumn('stock_reservations', 'lot_id')) {
                $table->unsignedBigInteger('lot_id')->nullable()->index();
            }

            if (! Schema::hasColumn('stock_reservations', 'source_type')) {
                $table->string('source_type')->default('pos_order')->index();
            }

            if (! Schema::hasColumn('stock_reservations', 'source_id')) {
                $table->unsignedBigInteger('source_id')->index();
            }

            if (! Schema::hasColumn('stock_reservations', 'pos_order_id')) {
                $table->unsignedBigInteger('pos_order_id')->nullable()->index();
            }

            if (! Schema::hasColumn('stock_reservations', 'pos_order_line_id')) {
                $table->unsignedBigInteger('pos_order_line_id')->nullable()->index();
            }

            if (! Schema::hasColumn('stock_reservations', 'quantity')) {
                $table->decimal('quantity', 18, 6)->default(0);
            }

            if (! Schema::hasColumn('stock_reservations', 'status')) {
                $table->string('status')->default('active')->index();
            }

            if (! Schema::hasColumn('stock_reservations', 'reserved_at')) {
                $table->timestamp('reserved_at')->nullable()->index();
            }

            if (! Schema::hasColumn('stock_reservations', 'released_at')) {
                $table->timestamp('released_at')->nullable()->index();
            }

            if (! Schema::hasColumn('stock_reservations', 'released_reason')) {
                $table->string('released_reason')->nullable();
            }

            if (! Schema::hasColumn('stock_reservations', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->index();
            }

            if (! Schema::hasColumn('stock_reservations', 'metadata')) {
                $table->json('metadata')->nullable();
            }

            if (! Schema::hasColumn('stock_reservations', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // No se elimina para no perder historial de reservas.
    }
};
