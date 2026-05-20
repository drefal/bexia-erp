<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_payables')) {
            Schema::create('account_payables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

                $table->string('number', 80);
                $table->string('status', 40)->default('open');

                $table->string('source_type', 80)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();

                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->foreignId('purchase_receipt_id')->nullable()->constrained('purchase_receipts')->nullOnDelete();

                $table->unsignedBigInteger('supplier_contact_id')->nullable()->index();
                $table->string('supplier_name')->nullable();
                $table->string('supplier_reference')->nullable();

                $table->date('issue_date')->nullable();
                $table->date('due_date')->nullable();
                $table->string('currency', 8)->default('MXN');

                $table->decimal('subtotal', 18, 4)->default(0);
                $table->decimal('tax_total', 18, 4)->default(0);
                $table->decimal('total', 18, 4)->default(0);
                $table->decimal('paid_total', 18, 4)->default(0);
                $table->decimal('balance_total', 18, 4)->default(0);

                $table->string('accounting_status', 40)->nullable();
                $table->foreignId('accounting_entry_id')->nullable()->constrained('accounting_entries')->nullOnDelete();
                $table->timestamp('accounting_posted_at')->nullable();
                $table->text('accounting_error_message')->nullable();

                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'number'], 'account_payables_company_number_unique');
                $table->unique(['company_id', 'source_type', 'source_id'], 'account_payables_source_unique');
                $table->index(['company_id', 'status', 'due_date'], 'account_payables_company_status_due_idx');
                $table->index(['company_id', 'supplier_contact_id'], 'account_payables_company_supplier_idx');
            });
        }

        if (! Schema::hasTable('account_payable_payments')) {
            Schema::create('account_payable_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('account_payable_id')->constrained('account_payables')->cascadeOnDelete();

                $table->foreignId('treasury_account_id')->nullable()->constrained('treasury_accounts')->nullOnDelete();
                $table->foreignId('payment_form_id')->nullable()->constrained('payment_forms')->nullOnDelete();
                $table->foreignId('treasury_movement_id')->nullable()->constrained('treasury_movements')->nullOnDelete();
                $table->foreignId('accounting_entry_id')->nullable()->constrained('accounting_entries')->nullOnDelete();

                $table->decimal('amount', 18, 4)->default(0);
                $table->date('payment_date')->nullable();
                $table->string('currency', 8)->default('MXN');
                $table->string('reference')->nullable();
                $table->string('status', 40)->default('draft');

                $table->timestamp('posted_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();

                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'status', 'payment_date'], 'ap_payments_company_status_date_idx');
                $table->index(['account_payable_id', 'status'], 'ap_payments_payable_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_payable_payments');
        Schema::dropIfExists('account_payables');
    }
};
