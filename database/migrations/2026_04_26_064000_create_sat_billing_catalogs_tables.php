<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sat_billing_catalogs')) {
            Schema::create('sat_billing_catalogs', function (Blueprint $table): void {
                $table->id();
                $table->string('catalog_key', 120)->unique();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sat_billing_catalog_items')) {
            Schema::create('sat_billing_catalog_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('catalog_id')->nullable()->index();
                $table->string('catalog_key', 120)->index();
                $table->string('source_sheet', 120)->index();
                $table->integer('source_row')->nullable();

                $table->string('code', 120)->index();
                $table->text('name')->nullable();
                $table->text('description')->nullable();

                $table->date('valid_from')->nullable();
                $table->date('valid_to')->nullable();

                $table->json('extra_attributes')->nullable();
                $table->string('external_key', 64)->index();
                $table->boolean('is_active')->default(true);

                $table->timestamps();

                $table->unique(['catalog_key', 'external_key'], 'sat_billing_catalog_items_unique');
            });
        }

        if (
            Schema::hasTable('sat_billing_catalogs') &&
            Schema::hasTable('sat_billing_catalog_items')
        ) {
            try {
                Schema::table('sat_billing_catalog_items', function (Blueprint $table): void {
                    $table->foreign('catalog_id')
                        ->references('id')
                        ->on('sat_billing_catalogs')
                        ->nullOnDelete();
                });
            } catch (Throwable $e) {
                //
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sat_billing_catalog_items');
        Schema::dropIfExists('sat_billing_catalogs');
    }
};
