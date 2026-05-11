<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_order_refunds')) {
            Schema::create('pos_order_refunds', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('pos_order_id')->index();
                $table->unsignedBigInteger('pos_session_id')->nullable()->index();
                $table->unsignedBigInteger('pos_point_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();

                $table->string('number')->unique();
                $table->string('type')->default('total')->index(); // total | partial
                $table->string('status')->default('draft')->index(); // draft | done | cancelled

                $table->text('reason');
                $table->decimal('subtotal', 18, 4)->default(0);
                $table->decimal('tax_total', 18, 4)->default(0);
                $table->decimal('total', 18, 4)->default(0);
                $table->decimal('payment_total', 18, 4)->default(0);

                $table->unsignedBigInteger('stock_movement_id')->nullable()->index();
                $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
                $table->timestamp('refunded_at')->nullable()->index();

                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['pos_order_id', 'status']);
                $table->index(['pos_session_id', 'status']);
            });
        }

        if (! Schema::hasTable('pos_order_refund_lines')) {
            Schema::create('pos_order_refund_lines', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('pos_order_refund_id')->index();
                $table->unsignedBigInteger('pos_order_id')->index();
                $table->unsignedBigInteger('pos_order_line_id')->nullable()->index();

                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();

                $table->string('product_name')->nullable();
                $table->string('product_reference')->nullable();

                $table->decimal('quantity', 18, 6)->default(0);
                $table->decimal('unit_price', 18, 6)->default(0);
                $table->decimal('tax_rate', 8, 4)->default(0);
                $table->decimal('subtotal', 18, 4)->default(0);
                $table->decimal('tax_total', 18, 4)->default(0);
                $table->decimal('total', 18, 4)->default(0);

                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['pos_order_refund_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('pos_order_refund_payments')) {
            Schema::create('pos_order_refund_payments', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('pos_order_refund_id')->index();
                $table->unsignedBigInteger('pos_order_id')->index();
                $table->unsignedBigInteger('payment_form_id')->nullable()->index();

                $table->string('payment_label')->nullable();
                $table->decimal('amount', 18, 4)->default(0);
                $table->string('status')->default('refunded')->index();

                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_order_refund_payments');
        Schema::dropIfExists('pos_order_refund_lines');
        Schema::dropIfExists('pos_order_refunds');
    }
};
