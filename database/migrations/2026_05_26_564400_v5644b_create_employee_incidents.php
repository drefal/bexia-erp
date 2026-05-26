<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_incidents')) {
            Schema::create('employee_incidents', function (Blueprint $table) {
                $table->id();

                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('hr_incident_type_id')->nullable()->constrained('hr_incident_types')->nullOnDelete();

                $table->string('title');
                $table->string('status', 30)->default('draft')->index();

                $table->date('start_date')->index();
                $table->date('end_date')->nullable()->index();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();

                $table->decimal('quantity', 12, 2)->nullable();
                $table->string('quantity_unit', 30)->nullable();

                $table->boolean('affects_payroll')->default(false)->index();
                $table->decimal('payroll_amount', 14, 2)->nullable();

                $table->boolean('requires_approval')->default(false)->index();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();

                $table->string('attachment_path')->nullable();
                $table->string('attachment_original_name')->nullable();

                $table->text('description')->nullable();
                $table->text('resolution_notes')->nullable();

                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->index(['company_id', 'employee_id']);
                $table->index(['company_id', 'hr_incident_type_id']);
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'start_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_incidents');
    }
};
