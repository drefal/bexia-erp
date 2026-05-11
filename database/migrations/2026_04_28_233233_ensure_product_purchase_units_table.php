<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_purchase_units')) {
            Schema::create('product_purchase_units', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->index();

                $table->string('sat_unit_key', 20)->nullable()->index();
                $table->string('sat_unit_name', 150)->nullable();

                $table->string('name', 80);
                $table->decimal('factor', 18, 6)->default(1);

                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);

                $table->string('notes', 255)->nullable();

                $table->timestamps();

                $table->index(['product_id', 'is_active']);
            });

            return;
        }

        Schema::table('product_purchase_units', function (Blueprint $table) {
            if (! Schema::hasColumn('product_purchase_units', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            }

            if (! Schema::hasColumn('product_purchase_units', 'product_id')) {
                $table->unsignedBigInteger('product_id')->index();
            }

            if (! Schema::hasColumn('product_purchase_units', 'sat_unit_key')) {
                $table->string('sat_unit_key', 20)->nullable()->index();
            }

            if (! Schema::hasColumn('product_purchase_units', 'sat_unit_name')) {
                $table->string('sat_unit_name', 150)->nullable();
            }

            if (! Schema::hasColumn('product_purchase_units', 'name')) {
                $table->string('name', 80)->default('Unidad');
            }

            if (! Schema::hasColumn('product_purchase_units', 'factor')) {
                $table->decimal('factor', 18, 6)->default(1);
            }

            if (! Schema::hasColumn('product_purchase_units', 'is_default')) {
                $table->boolean('is_default')->default(false);
            }

            if (! Schema::hasColumn('product_purchase_units', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }

            if (! Schema::hasColumn('product_purchase_units', 'notes')) {
                $table->string('notes', 255)->nullable();
            }

            if (! Schema::hasColumn('product_purchase_units', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_purchase_units');
    }
};
