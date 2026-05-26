<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_concepts')) {
            Schema::create('payroll_concepts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();

                $table->string('code', 80);
                $table->string('name');
                $table->string('type', 40)->default('perception')->index();
                $table->string('category', 80)->default('other')->index();
                $table->string('source', 40)->default('system')->index();
                $table->string('unit', 40)->default('amount');
                $table->string('sat_key', 80)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->integer('sort_order')->default(100)->index();
                $table->text('notes')->nullable();

                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'payroll_concepts_company_code_unique');
                $table->index(['company_id', 'type']);
                $table->index(['company_id', 'category']);
            });
        }

        if (! Schema::hasTable('payroll_run_line_concepts')) {
            Schema::create('payroll_run_line_concepts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
                $table->foreignId('payroll_run_line_id')->constrained('payroll_run_lines')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('payroll_concept_id')->nullable()->constrained('payroll_concepts')->nullOnDelete();

                $table->string('code', 80);
                $table->string('name');
                $table->string('type', 40)->default('perception')->index();
                $table->string('category', 80)->default('other')->index();
                $table->string('source', 40)->default('system')->index();
                $table->string('unit', 40)->default('amount');

                $table->decimal('quantity', 14, 4)->default(0);
                $table->decimal('rate', 14, 4)->default(0);
                $table->decimal('amount', 14, 2)->default(0);
                $table->json('metadata')->nullable();
                $table->integer('sort_order')->default(100)->index();

                $table->timestamps();

                $table->index(['company_id', 'payroll_run_id']);
                $table->index(['payroll_run_line_id', 'type']);
                $table->index(['employee_id', 'payroll_run_id']);
                $table->index(['code', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_line_concepts');
        Schema::dropIfExists('payroll_concepts');
    }
};
