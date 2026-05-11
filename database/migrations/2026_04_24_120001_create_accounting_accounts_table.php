<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_accounts')) {
            return;
        }

        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->string('code', 50);
            $table->string('name');

            $table->string('type', 40);
            $table->string('normal_balance', 10)->default('debit');

            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);

            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_accounts');
    }
};
