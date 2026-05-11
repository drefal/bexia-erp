<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_product_inventory_classifications')) {
            Schema::create('accounting_product_inventory_classifications', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('product_id');

                $table->string('classification', 50)->default('stockable');
                $table->boolean('requires_inventory_cost')->default(true);
                $table->string('source', 80)->default('manual');
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true);

                $table->timestamps();

                $table->unique(['company_id', 'product_id'], 'acct_prod_inv_class_unique');
                $table->index(['company_id', 'requires_inventory_cost'], 'acct_prod_inv_class_cost_idx');
                $table->index(['product_id'], 'acct_prod_inv_class_product_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_product_inventory_classifications');
    }
};
