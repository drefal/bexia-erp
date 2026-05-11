<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_price_cost_audits')) {
            Schema::create('product_price_cost_audits', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();

                $table->string('field_name', 100)->index();
                $table->string('field_label', 150);

                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();

                $table->decimal('old_numeric_value', 18, 6)->nullable();
                $table->decimal('new_numeric_value', 18, 6)->nullable();

                $table->string('source', 50)->default('manual')->index();
                $table->text('notes')->nullable();

                $table->string('product_reference')->nullable();
                $table->string('product_name')->nullable();

                $table->timestamp('changed_at')->index();

                $table->timestamps();

                if (Schema::hasTable('products')) {
                    $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                }

                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_cost_audits');
    }
};
