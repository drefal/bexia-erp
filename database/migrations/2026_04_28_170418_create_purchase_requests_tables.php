<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_requests')) {
            Schema::create('purchase_requests', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('number')->unique();

                $table->string('status', 40)->default('draft')->index();

                $table->unsignedBigInteger('supplier_id')->nullable()->index();
                $table->string('supplier_name')->nullable()->index();

                $table->unsignedBigInteger('requested_by_user_id')->nullable()->index();

                $table->string('source', 80)->default('suggested_purchase_list')->index();
                $table->decimal('budget_amount', 18, 4)->nullable();

                $table->decimal('total_without_tax', 18, 4)->default(0);
                $table->decimal('total_tax', 18, 4)->default(0);
                $table->decimal('total_with_tax', 18, 4)->default(0);

                $table->text('notes')->nullable();
                $table->timestamp('requested_at')->nullable();

                $table->timestamps();
            });
        }

        if (! Schema::hasTable('purchase_request_lines')) {
            Schema::create('purchase_request_lines', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('purchase_request_id')->index();

                $table->unsignedBigInteger('company_id')->nullable()->index();

                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();

                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();

                $table->string('product_label')->nullable();
                $table->string('variant_label')->nullable();
                $table->string('warehouse_label')->nullable();
                $table->string('location_label')->nullable();

                $table->decimal('available_quantity', 18, 6)->default(0);
                $table->decimal('suggested_quantity', 18, 6)->default(0);
                $table->decimal('requested_quantity', 18, 6)->default(0);
                $table->decimal('pending_quantity', 18, 6)->default(0);

                $table->decimal('unit_cost_without_tax', 18, 6)->default(0);
                $table->decimal('tax_rate', 8, 4)->default(0);
                $table->decimal('unit_cost_with_tax', 18, 6)->default(0);

                $table->decimal('line_total_without_tax', 18, 4)->default(0);
                $table->decimal('line_tax', 18, 4)->default(0);
                $table->decimal('line_total_with_tax', 18, 4)->default(0);

                $table->string('priority')->nullable();
                $table->string('priority_label')->nullable();
                $table->string('cost_source')->nullable();

                $table->json('source_data')->nullable();

                $table->timestamps();

                $table->foreign('purchase_request_id')
                    ->references('id')
                    ->on('purchase_requests')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_lines');
        Schema::dropIfExists('purchase_requests');
    }
};
