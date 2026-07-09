<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('odoo_history_account_moves')) {
            Schema::create('odoo_history_account_moves', function (Blueprint $table) {
                $table->id();
                $table->string('period_label')->index();
                $table->string('month_key', 7)->index();
                $table->string('source_file')->index();
                $table->bigInteger('odoo_id')->index();
                $table->bigInteger('odoo_company_id')->nullable()->index();
                $table->bigInteger('odoo_partner_id')->nullable()->index();
                $table->bigInteger('odoo_journal_id')->nullable()->index();
                $table->bigInteger('odoo_currency_id')->nullable()->index();
                $table->text('name')->nullable();
                $table->text('ref')->nullable();
                $table->string('move_type')->nullable()->index();
                $table->string('state')->nullable()->index();
                $table->string('payment_state')->nullable()->index();
                $table->date('invoice_date')->nullable()->index();
                $table->date('record_date')->nullable()->index();
                $table->decimal('amount_untaxed', 24, 6)->default(0);
                $table->decimal('amount_tax', 24, 6)->default(0);
                $table->decimal('amount_total', 24, 6)->default(0);
                $table->decimal('amount_residual', 24, 6)->default(0);
                $table->timestamp('create_date')->nullable();
                $table->timestamp('write_date')->nullable();
                $table->timestamps();

                $table->unique(['period_label', 'odoo_id'], 'odoo_hist_account_moves_unique');
            });
        }

        if (! Schema::hasTable('odoo_history_account_move_lines')) {
            Schema::create('odoo_history_account_move_lines', function (Blueprint $table) {
                $table->id();
                $table->string('period_label')->index();
                $table->string('month_key', 7)->index();
                $table->string('source_file')->index();
                $table->bigInteger('odoo_id')->index();
                $table->bigInteger('odoo_company_id')->nullable()->index();
                $table->bigInteger('odoo_move_id')->nullable()->index();
                $table->bigInteger('odoo_account_id')->nullable()->index();
                $table->bigInteger('odoo_partner_id')->nullable()->index();
                $table->bigInteger('odoo_product_id')->nullable()->index();
                $table->bigInteger('odoo_currency_id')->nullable()->index();
                $table->text('name')->nullable();
                $table->date('record_date')->nullable()->index();
                $table->decimal('debit', 24, 6)->default(0);
                $table->decimal('credit', 24, 6)->default(0);
                $table->decimal('balance', 24, 6)->default(0);
                $table->decimal('amount_currency', 24, 6)->default(0);
                $table->decimal('quantity', 24, 6)->default(0);
                $table->decimal('price_unit', 24, 6)->default(0);
                $table->decimal('price_subtotal', 24, 6)->default(0);
                $table->decimal('price_total', 24, 6)->default(0);
                $table->boolean('reconciled')->nullable()->index();
                $table->timestamp('create_date')->nullable();
                $table->timestamp('write_date')->nullable();
                $table->timestamps();

                $table->unique(['period_label', 'odoo_id'], 'odoo_hist_account_move_lines_unique');
            });
        }

        if (! Schema::hasTable('odoo_history_account_payments')) {
            Schema::create('odoo_history_account_payments', function (Blueprint $table) {
                $table->id();
                $table->string('period_label')->index();
                $table->string('month_key', 7)->index();
                $table->string('source_file')->index();
                $table->bigInteger('odoo_id')->index();
                $table->bigInteger('odoo_company_id')->nullable()->index();
                $table->bigInteger('odoo_partner_id')->nullable()->index();
                $table->bigInteger('odoo_journal_id')->nullable()->index();
                $table->bigInteger('odoo_currency_id')->nullable()->index();
                $table->text('name')->nullable();
                $table->text('ref')->nullable();
                $table->string('payment_type')->nullable()->index();
                $table->string('partner_type')->nullable()->index();
                $table->string('state')->nullable()->index();
                $table->date('record_date')->nullable()->index();
                $table->decimal('amount', 24, 6)->default(0);
                $table->timestamp('create_date')->nullable();
                $table->timestamp('write_date')->nullable();
                $table->timestamps();

                $table->unique(['period_label', 'odoo_id'], 'odoo_hist_account_payments_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_history_account_payments');
        Schema::dropIfExists('odoo_history_account_move_lines');
        Schema::dropIfExists('odoo_history_account_moves');
    }
};
