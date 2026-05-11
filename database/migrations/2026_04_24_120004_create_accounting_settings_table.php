<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_settings')) {
            return;
        }

        Schema::create('accounting_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('inventory_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->foreignId('cogs_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->foreignId('sales_income_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->foreignId('customer_receivable_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->foreignId('supplier_payable_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->foreignId('vat_creditable_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->foreignId('vat_payable_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->foreignId('cash_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->foreignId('bank_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->foreignId('inventory_adjustment_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->foreignId('default_journal_id')
                ->nullable()
                ->constrained('accounting_journals')
                ->nullOnDelete();

            $table->foreignId('purchases_journal_id')
                ->nullable()
                ->constrained('accounting_journals')
                ->nullOnDelete();

            $table->foreignId('sales_journal_id')
                ->nullable()
                ->constrained('accounting_journals')
                ->nullOnDelete();

            $table->foreignId('pos_journal_id')
                ->nullable()
                ->constrained('accounting_journals')
                ->nullOnDelete();

            $table->string('costing_method', 30)->default('average');

            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_settings');
    }
};
