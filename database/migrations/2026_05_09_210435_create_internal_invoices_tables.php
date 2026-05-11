<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('contact_id')->nullable()->index();

                $table->string('number', 80)->index();
                $table->string('status', 40)->default('draft')->index();

                $table->string('source_type', 60)->nullable()->index();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->string('source_number', 120)->nullable();

                $table->date('invoice_date')->nullable()->index();
                $table->date('due_date')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();

                $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
                $table->unsignedBigInteger('cancelled_by_user_id')->nullable()->index();
                $table->text('cancel_reason')->nullable();

                $table->string('currency_code', 10)->default('MXN');
                $table->decimal('subtotal', 16, 4)->default(0);
                $table->decimal('discount_total', 16, 4)->default(0);
                $table->decimal('tax_total', 16, 4)->default(0);
                $table->decimal('total', 16, 4)->default(0);
                $table->decimal('paid_total', 16, 4)->default(0);
                $table->decimal('balance_total', 16, 4)->default(0);

                $table->string('issuer_name', 255)->nullable();
                $table->string('issuer_tax_id', 40)->nullable();
                $table->string('issuer_tax_regime', 40)->nullable();
                $table->string('issuer_postal_code', 20)->nullable();

                $table->string('customer_name', 255)->nullable();
                $table->string('customer_fiscal_name', 255)->nullable();
                $table->string('customer_rfc', 40)->nullable();
                $table->string('customer_tax_regime_code', 40)->nullable();
                $table->string('customer_cfdi_use_code', 40)->nullable();
                $table->string('customer_postal_code', 20)->nullable();

                $table->string('payment_form_code', 40)->nullable();
                $table->string('payment_method_code', 40)->nullable();
                $table->string('payment_terms', 255)->nullable();

                $table->string('cfdi_uuid', 120)->nullable()->index();
                $table->string('cfdi_status', 40)->nullable()->index();
                $table->string('xml_path', 500)->nullable();
                $table->string('pdf_path', 500)->nullable();

                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'number']);
                $table->unique(['source_type', 'source_id']);
            });
        }

        if (! Schema::hasTable('invoice_lines')) {
            Schema::create('invoice_lines', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('invoice_id')->index();
                $table->unsignedBigInteger('company_id')->index();

                $table->string('source_type', 60)->nullable()->index();
                $table->unsignedBigInteger('source_line_id')->nullable()->index();

                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('product_name', 255)->nullable();
                $table->text('description')->nullable();

                $table->decimal('quantity', 16, 4)->default(0);
                $table->decimal('unit_price_without_tax', 16, 4)->default(0);
                $table->decimal('unit_price', 16, 4)->default(0);
                $table->decimal('tax_rate', 10, 6)->default(0);
                $table->decimal('subtotal', 16, 4)->default(0);
                $table->decimal('discount_total', 16, 4)->default(0);
                $table->decimal('tax_total', 16, 4)->default(0);
                $table->decimal('total', 16, 4)->default(0);

                $table->string('sat_product_service_code', 80)->nullable();
                $table->string('sat_unit_code', 80)->nullable();
                $table->string('sat_tax_object_code', 80)->nullable();

                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index(['invoice_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('invoice_payments')) {
            Schema::create('invoice_payments', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('invoice_id')->index();
                $table->unsignedBigInteger('company_id')->index();

                $table->string('source_type', 60)->nullable()->index();
                $table->unsignedBigInteger('source_payment_id')->nullable()->index();

                $table->unsignedBigInteger('payment_form_id')->nullable()->index();
                $table->string('payment_label', 255)->nullable();
                $table->string('payment_form_code', 40)->nullable();

                $table->decimal('amount', 16, 4)->default(0);
                $table->string('status', 40)->default('paid')->index();
                $table->timestamp('paid_at')->nullable();

                $table->json('metadata')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
