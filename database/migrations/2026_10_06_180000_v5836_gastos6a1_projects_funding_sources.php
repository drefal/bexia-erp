<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_projects', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['company_id', 'code'],
                'expense_projects_company_code_unique'
            );

            $table->index(
                ['company_id', 'is_active'],
                'expense_projects_company_active_idx'
            );
        });

        Schema::create('funding_sources', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['company_id', 'code'],
                'funding_sources_company_code_unique'
            );

            $table->index(
                ['company_id', 'is_active'],
                'funding_sources_company_active_idx'
            );
        });

        Schema::table('expense_report_lines', function (Blueprint $table): void {
            $table->foreignId('expense_project_id')
                ->nullable()
                ->after('expense_category_id')
                ->constrained('expense_projects')
                ->nullOnDelete();

            $table->index(
                ['expense_project_id', 'expense_date'],
                'expense_report_lines_project_date_idx'
            );
        });

        Schema::table('petty_cash_fund_movements', function (Blueprint $table): void {
            $table->foreignId('funding_source_id')
                ->nullable()
                ->after('type')
                ->constrained('funding_sources')
                ->nullOnDelete();

            $table->index(
                ['funding_source_id', 'movement_date'],
                'petty_cash_movements_source_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_fund_movements', function (Blueprint $table): void {
            $table->dropForeign(['funding_source_id']);
            $table->dropIndex('petty_cash_movements_source_date_idx');
            $table->dropColumn('funding_source_id');
        });

        Schema::table('expense_report_lines', function (Blueprint $table): void {
            $table->dropForeign(['expense_project_id']);
            $table->dropIndex('expense_report_lines_project_date_idx');
            $table->dropColumn('expense_project_id');
        });

        Schema::dropIfExists('funding_sources');
        Schema::dropIfExists('expense_projects');
    }
};
