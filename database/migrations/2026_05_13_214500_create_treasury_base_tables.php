<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * BEXIA_V5524A_TREASURY_BASE
         * Tesorería vive fuera de Contabilidad.
         * Contabilidad podrá tomar estos movimientos después para pólizas/asientos.
         */

        if (! Schema::hasTable('banks')) {
            Schema::create('banks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name');
                $table->string('code')->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'name']);
            });
        }

        if (! Schema::hasTable('treasury_accounts')) {
            Schema::create('treasury_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('bank_id')->nullable()->index();
                $table->unsignedBigInteger('accounting_account_id')->nullable()->index();

                $table->string('type')->default('bank')->index(); // bank / cash
                $table->string('name');
                $table->string('account_number')->nullable();
                $table->string('clabe', 32)->nullable();
                $table->string('currency_code', 3)->default('MXN')->index();

                $table->decimal('opening_balance', 18, 6)->default(0);
                $table->decimal('current_balance', 18, 6)->default(0);

                $table->boolean('is_active')->default(true)->index();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'name']);
            });
        }

        if (! Schema::hasTable('treasury_movements')) {
            Schema::create('treasury_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('treasury_account_id')->index();
                $table->unsignedBigInteger('payment_form_id')->nullable()->index();
                $table->unsignedBigInteger('accounting_entry_id')->nullable()->index();

                $table->string('type')->index(); // inbound / outbound / transfer / adjustment
                $table->string('source_type')->nullable()->index(); // customer_invoice / supplier_bill / pos / manual
                $table->unsignedBigInteger('source_id')->nullable()->index();

                $table->date('movement_date')->index();
                $table->decimal('amount', 18, 6);
                $table->string('currency_code', 3)->default('MXN')->index();

                $table->string('reference')->nullable()->index();
                $table->text('description')->nullable();

                $table->string('status')->default('draft')->index(); // draft / posted / cancelled
                $table->timestamp('posted_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable()->index();

                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_movements');
        Schema::dropIfExists('treasury_accounts');
        Schema::dropIfExists('banks');
    }
};
