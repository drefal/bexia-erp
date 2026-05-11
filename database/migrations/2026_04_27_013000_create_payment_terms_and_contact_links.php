<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_terms')) {
            Schema::create('payment_terms', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('code', 50);
                $table->string('name', 150);
                $table->unsignedInteger('days')->default(0);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code']);
            });
        }

        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table): void {
                if (! Schema::hasColumn('contacts', 'customer_payment_term_id')) {
                    $table->unsignedBigInteger('customer_payment_term_id')->nullable()->index()->after('customer_payment_terms_text');
                }

                if (! Schema::hasColumn('contacts', 'supplier_payment_term_id')) {
                    $table->unsignedBigInteger('supplier_payment_term_id')->nullable()->index()->after('supplier_payment_terms_text');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table): void {
                if (Schema::hasColumn('contacts', 'supplier_payment_term_id')) {
                    $table->dropColumn('supplier_payment_term_id');
                }

                if (Schema::hasColumn('contacts', 'customer_payment_term_id')) {
                    $table->dropColumn('customer_payment_term_id');
                }
            });
        }

        Schema::dropIfExists('payment_terms');
    }
};
