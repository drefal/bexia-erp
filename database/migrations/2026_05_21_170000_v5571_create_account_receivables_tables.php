<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_receivables')) {
            Schema::create('account_receivables', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

                $table->string('number', 80);
                $table->string('status', 40)->default('open');

                $table->string('source_type', 80)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();

                $table->unsignedBigInteger('sale_order_id')->nullable()->index();
                $table->unsignedBigInteger('invoice_id')->nullable()->index();

                $table->unsignedBigInteger('customer_contact_id')->nullable()->index();
                $table->string('customer_name')->nullable();
                $table->string('customer_reference')->nullable();

                $table->date('issue_date')->nullable();
                $table->date('due_date')->nullable();
                $table->string('currency', 8)->default('MXN');

                $table->decimal('subtotal', 18, 4)->default(0);
                $table->decimal('tax_total', 18, 4)->default(0);
                $table->decimal('total', 18, 4)->default(0);
                $table->decimal('collected_total', 18, 4)->default(0);
                $table->decimal('balance_total', 18, 4)->default(0);

                $table->string('accounting_status', 40)->nullable();
                $table->foreignId('accounting_entry_id')->nullable()->constrained('accounting_entries')->nullOnDelete();
                $table->timestamp('accounting_posted_at')->nullable();
                $table->text('accounting_error_message')->nullable();

                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'number'], 'account_receivables_company_number_unique');
                $table->unique(['company_id', 'source_type', 'source_id'], 'account_receivables_source_unique');
                $table->index(['company_id', 'status', 'due_date'], 'account_receivables_company_status_due_idx');
                $table->index(['company_id', 'customer_contact_id'], 'account_receivables_company_customer_idx');
            });
        }

        if (! Schema::hasTable('account_receivable_payments')) {
            Schema::create('account_receivable_payments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('account_receivable_id')->constrained('account_receivables')->cascadeOnDelete();

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

                $table->index(['company_id', 'status', 'payment_date'], 'ar_payments_company_status_date_idx');
                $table->index(['account_receivable_id', 'status'], 'ar_payments_receivable_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_receivable_payments');
        Schema::dropIfExists('account_receivables');
    }
};
