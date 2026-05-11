<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tax_rates')) {
            return;
        }

        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->nullable()
                ->index();

            $table->string('code', 80);
            $table->string('name', 255);

            $table->string('tax_type', 40)->default('iva');
            $table->string('factor_type', 40)->default('tasa');

            $table->decimal('rate', 12, 6)->default(0);

            $table->boolean('is_withholding')->default(false);
            $table->boolean('is_active')->default(true);

            $table->integer('sort_order')->default(0);
            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        if (Schema::hasTable('companies')) {
            Schema::table('tax_rates', function (Blueprint $table): void {
                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
