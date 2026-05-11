<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_orders')) {
            Schema::create('pos_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('pos_point_id')->nullable()->index();
                $table->unsignedBigInteger('pos_session_id')->nullable()->index();
                $table->unsignedBigInteger('employee_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();

                $table->string('number')->unique();
                $table->string('status')->default('pending_payment')->index();

                $table->decimal('subtotal', 16, 4)->default(0);
                $table->decimal('tax_total', 16, 4)->default(0);
                $table->decimal('total', 16, 4)->default(0);

                $table->string('currency_code', 10)->default('MXN');
                $table->json('metadata')->nullable();

                $table->timestamp('ordered_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();

                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_order_lines')) {
            Schema::create('pos_order_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pos_order_id')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();

                $table->string('product_name');
                $table->string('product_reference')->nullable();

                $table->decimal('quantity', 16, 4)->default(1);
                $table->decimal('unit_price', 16, 4)->default(0);
                $table->decimal('tax_rate', 8, 4)->default(0.16);

                $table->decimal('subtotal', 16, 4)->default(0);
                $table->decimal('tax_total', 16, 4)->default(0);
                $table->decimal('total', 16, 4)->default(0);

                $table->json('metadata')->nullable();

                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_order_payments')) {
            Schema::create('pos_order_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pos_order_id')->index();
                $table->unsignedBigInteger('payment_form_id')->nullable()->index();

                $table->string('payment_label')->nullable();
                $table->decimal('amount', 16, 4)->default(0);
                $table->string('status')->default('pending')->index();
                $table->json('metadata')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_order_payments');
        Schema::dropIfExists('pos_order_lines');
        Schema::dropIfExists('pos_orders');
    }
};
