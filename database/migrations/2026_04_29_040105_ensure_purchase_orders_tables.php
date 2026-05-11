<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('purchase_request_id')->nullable()->index();

                $table->string('number', 60)->unique();
                $table->string('status', 40)->default('draft')->index();

                $table->unsignedBigInteger('supplier_contact_id')->nullable()->index();
                $table->string('supplier_name', 255)->nullable();

                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->string('warehouse_label', 255)->nullable();
                $table->string('location_label', 255)->nullable();

                $table->dateTime('order_date')->nullable();
                $table->dateTime('expected_date')->nullable();

                $table->string('currency', 10)->default('MXN');
                $table->string('origin', 255)->nullable();

                $table->decimal('total_without_tax', 18, 6)->default(0);
                $table->decimal('total_tax', 18, 6)->default(0);
                $table->decimal('total_with_tax', 18, 6)->default(0);

                $table->string('source_snapshot_hash', 128)->nullable()->index();
                $table->string('current_hash', 128)->nullable()->index();
                $table->string('approval_hash', 128)->nullable()->index();
                $table->boolean('differs_from_request')->default(false);
                $table->string('approval_required_reason', 255)->nullable();
                $table->dateTime('submitted_for_approval_at')->nullable();
                $table->dateTime('confirmed_at')->nullable();
                $table->unsignedBigInteger('confirmed_by_user_id')->nullable()->index();

                $table->text('notes')->nullable();

                $table->unsignedBigInteger('created_by_user_id')->nullable()->index();

                $table->timestamps();

                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('purchase_order_lines')) {
            Schema::create('purchase_order_lines', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('purchase_order_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();

                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();

                $table->string('product_label', 255)->nullable();
                $table->string('variant_label', 255)->nullable();

                $table->string('purchase_unit_type', 60)->nullable();
                $table->string('purchase_unit_label', 120)->nullable();
                $table->decimal('purchase_unit_factor', 18, 6)->default(1);

                $table->string('sat_unit_key', 20)->nullable()->index();
                $table->string('sat_unit_name', 150)->nullable();

                $table->decimal('ordered_quantity', 18, 6)->default(0);
                $table->decimal('base_quantity', 18, 6)->default(0);

                $table->decimal('received_quantity', 18, 6)->default(0);
                $table->decimal('received_base_quantity', 18, 6)->default(0);

                $table->decimal('unit_cost_without_tax', 18, 6)->default(0);
                $table->decimal('tax_rate', 10, 4)->default(0);
                $table->decimal('unit_cost_with_tax', 18, 6)->default(0);

                $table->decimal('line_total_without_tax', 18, 6)->default(0);
                $table->decimal('line_tax', 18, 6)->default(0);
                $table->decimal('line_total_with_tax', 18, 6)->default(0);

                $table->string('notes', 255)->nullable();

                $table->timestamps();

                $table->index(['purchase_order_id', 'product_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
