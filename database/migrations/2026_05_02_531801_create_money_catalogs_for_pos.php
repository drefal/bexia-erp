<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_forms')) {
            Schema::create('payment_forms', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('code', 20)->index();
                $table->string('name');
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('requires_reference')->default(false);
                $table->boolean('requires_bank')->default(false);
                $table->boolean('is_cash')->default(false);
                $table->boolean('is_credit')->default(false);
                $table->integer('sort_order')->default(10);
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'payment_forms_company_code_unique');
            });
        }

        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('code', 10)->index();
                $table->string('name');
                $table->string('symbol', 10)->nullable();
                $table->decimal('exchange_rate', 18, 6)->default(1);
                $table->boolean('is_default')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->integer('sort_order')->default(10);
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'currencies_company_code_unique');
            });
        }

        if (! Schema::hasTable('cash_denominations')) {
            Schema::create('cash_denominations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('currency_id')->nullable()->index();
                $table->string('name');
                $table->decimal('value', 16, 2);
                $table->string('type')->default('bill')->index();
                $table->boolean('is_active')->default(true)->index();
                $table->integer('sort_order')->default(10);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('pos_points')) {
            Schema::table('pos_points', function (Blueprint $table) {
                if (! Schema::hasColumn('pos_points', 'currency_ids')) {
                    $table->json('currency_ids')->nullable();
                }

                if (! Schema::hasColumn('pos_points', 'default_currency_id')) {
                    $table->unsignedBigInteger('default_currency_id')->nullable()->index();
                }

                if (! Schema::hasColumn('pos_points', 'cash_denomination_ids')) {
                    $table->json('cash_denomination_ids')->nullable();
                }

                if (! Schema::hasColumn('pos_points', 'payment_method_ids')) {
                    $table->json('payment_method_ids')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // No se eliminan tablas/columnas para evitar perdida de configuracion.
    }
};
