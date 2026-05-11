<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contacts')) {
            return;
        }

        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')->nullable()->index();
            $table->foreignId('parent_contact_id')->nullable()->index();

            $table->string('contact_type', 30)->default('company'); // company/person
            $table->string('address_type', 30)->default('main'); // main/invoice/delivery/contact/other

            $table->string('name', 255);
            $table->string('commercial_name', 255)->nullable();
            $table->string('fiscal_name', 255)->nullable();

            $table->boolean('is_customer')->default(true);
            $table->boolean('is_supplier')->default(false);
            $table->boolean('is_active')->default(true);

            $table->string('rfc', 20)->nullable()->index();
            $table->string('curp', 30)->nullable();

            $table->string('email', 255)->nullable();
            $table->string('phone', 80)->nullable();
            $table->string('mobile', 80)->nullable();
            $table->string('website', 255)->nullable();

            $table->string('street', 255)->nullable();
            $table->string('street2', 255)->nullable();
            $table->string('exterior_number', 80)->nullable();
            $table->string('interior_number', 80)->nullable();
            $table->string('neighborhood', 255)->nullable();
            $table->string('locality', 255)->nullable();
            $table->string('municipality', 255)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('state', 255)->nullable();
            $table->string('country', 120)->nullable()->default('México');
            $table->string('postal_code', 20)->nullable()->index();

            $table->string('sat_country_code', 20)->nullable()->default('MEX');
            $table->string('sat_tax_regime_code', 20)->nullable()->index();
            $table->string('sat_cfdi_use_code', 20)->nullable()->index();
            $table->string('payment_form_code', 20)->nullable()->index();
            $table->string('payment_method_code', 20)->nullable()->index();
            $table->string('fiscal_zip', 20)->nullable()->index();

            $table->string('branch_name', 255)->nullable();
            $table->boolean('blacklisted_sat')->default(false);

            $table->string('price_list_name', 255)->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('sales_payment_terms', 255)->nullable();
            $table->string('delivery_method', 255)->nullable();

            $table->string('purchase_payment_terms', 255)->nullable();
            $table->string('supplier_currency', 20)->nullable();
            $table->string('supplier_reference', 255)->nullable();

            $table->text('internal_notes')->nullable();
            $table->json('extra_attributes')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'is_customer']);
            $table->index(['company_id', 'is_supplier']);
            $table->index(['company_id', 'contact_type']);
        });

        if (Schema::hasTable('companies')) {
            Schema::table('contacts', function (Blueprint $table): void {
                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();
            });
        }

        Schema::table('contacts', function (Blueprint $table): void {
            $table->foreign('parent_contact_id')
                ->references('id')
                ->on('contacts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
