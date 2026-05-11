<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_journal_mappings')) {
            return;
        }

        Schema::create('accounting_journal_mappings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('module', 80);
            $table->string('operation_type', 120);

            $table->foreignId('journal_id')
                ->constrained('accounting_journals')
                ->cascadeOnDelete();

            $table->boolean('is_active')->default(true);
            $table->json('options')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'module', 'operation_type'], 'acct_journal_map_unique');
            $table->index(['company_id', 'module']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_journal_mappings');
    }
};
