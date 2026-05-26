<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            if (! Schema::hasColumn('employees', 'hire_date')) {
                $table->date('hire_date')->nullable()->after('employee_type')->index();
            }

            if (! Schema::hasColumn('employees', 'termination_date')) {
                $table->date('termination_date')->nullable()->after('hire_date')->index();
            }
        });

        if (! Schema::hasTable('employee_vacation_balances')) {
            Schema::create('employee_vacation_balances', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

                $table->date('period_start')->index();
                $table->date('period_end')->index();

                $table->unsignedInteger('years_of_service')->default(0);

                $table->decimal('entitled_days', 8, 2)->default(0);
                $table->decimal('carried_over_days', 8, 2)->default(0);
                $table->decimal('adjusted_days', 8, 2)->default(0);
                $table->decimal('taken_days', 8, 2)->default(0);
                $table->decimal('pending_days', 8, 2)->default(0);
                $table->decimal('expired_days', 8, 2)->default(0);

                $table->string('policy_code', 60)->default('MX_LFT_2023');
                $table->string('status', 30)->default('open')->index();

                $table->text('notes')->nullable();

                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->unique(['employee_id', 'period_start', 'period_end'], 'emp_vac_balance_unique_period');
                $table->index(['company_id', 'employee_id']);
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'period_start', 'period_end'], 'emp_vac_balance_company_period_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_vacation_balances');

        Schema::table('employees', function (Blueprint $table): void {
            if (Schema::hasColumn('employees', 'termination_date')) {
                $table->dropColumn('termination_date');
            }

            if (Schema::hasColumn('employees', 'hire_date')) {
                $table->dropColumn('hire_date');
            }
        });
    }
};
