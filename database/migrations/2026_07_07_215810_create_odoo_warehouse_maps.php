<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('odoo_warehouse_maps')) {
            Schema::create('odoo_warehouse_maps', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('odoo_warehouse_id')->unique();
                $table->string('odoo_warehouse_name')->nullable();
                $table->string('odoo_warehouse_code')->nullable();
                $table->bigInteger('odoo_company_id')->nullable();
                $table->string('odoo_company_name')->nullable();

                $table->unsignedBigInteger('bexia_warehouse_id')->nullable();
                $table->string('bexia_warehouse_name')->nullable();
                $table->string('bexia_warehouse_code')->nullable();
                $table->unsignedBigInteger('bexia_company_id')->nullable();

                $table->string('status')->default('pending');
                $table->string('match_method')->nullable();
                $table->unsignedInteger('confidence')->default(0);
                $table->text('notes')->nullable();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['odoo_company_id']);
                $table->index(['bexia_company_id']);
                $table->index(['bexia_warehouse_id']);
                $table->index(['status']);
                $table->index(['match_method']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_warehouse_maps');
    }
};
