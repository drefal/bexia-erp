<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contacts')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table): void {
            if (! Schema::hasColumn('contacts', 'salesperson_user_id')) {
                $table->unsignedBigInteger('salesperson_user_id')->nullable()->index()->after('is_supplier');
            }

            if (! Schema::hasColumn('contacts', 'customer_cfdi_use_code')) {
                $table->string('customer_cfdi_use_code', 20)->nullable()->after('salesperson_user_id');
            }

            if (! Schema::hasColumn('contacts', 'customer_payment_method_code')) {
                $table->string('customer_payment_method_code', 20)->nullable()->after('customer_cfdi_use_code');
            }

            if (! Schema::hasColumn('contacts', 'customer_payment_form_code')) {
                $table->string('customer_payment_form_code', 20)->nullable()->after('customer_payment_method_code');
            }

            if (! Schema::hasColumn('contacts', 'customer_currency_code')) {
                $table->string('customer_currency_code', 10)->nullable()->default('MXN')->after('customer_payment_form_code');
            }

            if (! Schema::hasColumn('contacts', 'customer_payment_terms_text')) {
                $table->string('customer_payment_terms_text', 255)->nullable()->after('customer_currency_code');
            }

            if (! Schema::hasColumn('contacts', 'customer_credit_limit')) {
                $table->decimal('customer_credit_limit', 14, 2)->nullable()->after('customer_payment_terms_text');
            }

            if (! Schema::hasColumn('contacts', 'supplier_payment_form_code')) {
                $table->string('supplier_payment_form_code', 20)->nullable()->after('customer_credit_limit');
            }

            if (! Schema::hasColumn('contacts', 'supplier_currency_code')) {
                $table->string('supplier_currency_code', 10)->nullable()->default('MXN')->after('supplier_payment_form_code');
            }

            if (! Schema::hasColumn('contacts', 'supplier_payment_terms_text')) {
                $table->string('supplier_payment_terms_text', 255)->nullable()->after('supplier_currency_code');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contacts')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table): void {
            foreach ([
                'supplier_payment_terms_text',
                'supplier_currency_code',
                'supplier_payment_form_code',
                'customer_credit_limit',
                'customer_payment_terms_text',
                'customer_currency_code',
                'customer_payment_form_code',
                'customer_payment_method_code',
                'customer_cfdi_use_code',
                'salesperson_user_id',
            ] as $column) {
                if (Schema::hasColumn('contacts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
