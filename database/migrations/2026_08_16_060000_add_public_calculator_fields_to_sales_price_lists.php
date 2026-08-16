<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_price_lists', function (Blueprint $table): void {
            $table->string('payment_provider', 50)->nullable();
            $table->unsignedSmallInteger('installment_months')->nullable();
            $table->boolean('public_calculator')->default(false);
            $table->unsignedSmallInteger('public_sort')->nullable();

            $table->index(
                ['company_id', 'public_calculator', 'payment_provider'],
                'spl_public_calculator_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('sales_price_lists', function (Blueprint $table): void {
            $table->dropIndex('spl_public_calculator_idx');

            $table->dropColumn([
                'payment_provider',
                'installment_months',
                'public_calculator',
                'public_sort',
            ]);
        });
    }
};
