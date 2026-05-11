<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exit_inventory_items')) {
            Schema::create('exit_inventory_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('form_submission_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedInteger('item_index')->default(0);
                $table->string('folio')->nullable()->index();

                $table->string('item_description')->nullable();
                $table->decimal('requested_quantity', 18, 6)->default(0);
                $table->decimal('delivered_quantity', 18, 6)->default(0);

                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();

                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('source_location_id')->nullable()->index();
                $table->unsignedBigInteger('destination_location_id')->nullable()->index();

                $table->string('source_warehouse_label')->nullable();
                $table->string('destination_warehouse_label')->nullable();
                $table->string('project_label')->nullable();

                $table->string('delivery_status')->default('pending')->index();
                $table->json('raw_item')->nullable();

                $table->timestamps();

                $table->unique(['form_submission_id', 'item_index'], 'exit_inventory_items_submission_index_unique');
            });
        }

        if (! Schema::hasTable('exit_deliveries')) {
            Schema::create('exit_deliveries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('form_submission_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('number')->nullable()->unique();
                $table->string('origin_folio')->nullable()->index();

                $table->string('status')->default('draft')->index();
                $table->timestamp('delivered_at')->nullable();

                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('source_location_id')->nullable()->index();
                $table->unsignedBigInteger('destination_location_id')->nullable()->index();
                $table->unsignedBigInteger('stock_movement_id')->nullable()->index();

                $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
                $table->unsignedBigInteger('delivered_by_user_id')->nullable()->index();

                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('exit_delivery_lines')) {
            Schema::create('exit_delivery_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('exit_delivery_id')->index();
                $table->unsignedBigInteger('exit_inventory_item_id')->index();

                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();

                $table->decimal('quantity', 18, 6)->default(0);
                $table->decimal('unit_cost', 18, 6)->default(0);

                $table->unsignedBigInteger('stock_movement_line_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // No eliminamos para no perder trazabilidad.
    }
};
