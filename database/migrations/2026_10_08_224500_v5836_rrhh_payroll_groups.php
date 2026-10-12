<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_groups')) {
            Schema::create('payroll_groups', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->cascadeOnDelete();

                $table->string('code', 30);
                $table->string('name', 120);

                $table->foreignId('payroll_periodicity_id')
                    ->nullable()
                    ->constrained('payroll_periodicities')
                    ->nullOnDelete();

                $table->boolean('is_active')->default(true)->index();
                $table->text('notes')->nullable();

                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('updated_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->unique(
                    ['company_id', 'code'],
                    'payroll_groups_company_code_unique'
                );

                $table->index(
                    ['company_id', 'is_active'],
                    'payroll_groups_company_active_index'
                );
            });
        }

        if (! Schema::hasColumn('employees', 'payroll_group_id')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->foreignId('payroll_group_id')
                    ->nullable()
                    ->constrained('payroll_groups')
                    ->nullOnDelete()
                    ->index();
            });
        }

        if (! Schema::hasColumn('payroll_runs', 'payroll_group_id')) {
            Schema::table('payroll_runs', function (Blueprint $table): void {
                $table->foreignId('payroll_group_id')
                    ->nullable()
                    ->constrained('payroll_groups')
                    ->nullOnDelete()
                    ->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_runs', 'payroll_group_id')) {
            Schema::table('payroll_runs', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('payroll_group_id');
            });
        }

        if (Schema::hasColumn('employees', 'payroll_group_id')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('payroll_group_id');
            });
        }

        Schema::dropIfExists('payroll_groups');
    }
};
