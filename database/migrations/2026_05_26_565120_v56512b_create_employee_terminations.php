<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_terminations')) {
            Schema::create('employee_terminations', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('employee_contract_id')->nullable()->constrained('employee_contracts')->nullOnDelete();

                $table->string('termination_number')->nullable()->index();
                $table->string('termination_type', 60)->default('resignation')->index();
                $table->string('status', 30)->default('draft')->index();

                $table->date('termination_date')->index();
                $table->date('last_working_day')->nullable()->index();
                $table->date('notice_date')->nullable();

                $table->boolean('rehire_eligible')->default(false);
                $table->decimal('settlement_amount', 14, 2)->nullable();
                $table->string('currency', 3)->default('MXN');

                $table->string('file_path')->nullable();
                $table->string('file_original_name')->nullable();

                $table->text('reason')->nullable();
                $table->text('notes')->nullable();

                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'employee_id']);
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'termination_type']);
                $table->index(['company_id', 'termination_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_terminations');
    }
};
