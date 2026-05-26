<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_policies')) {
            Schema::create('payroll_policies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();

                $table->string('name');
                $table->string('status', 40)->default('active')->index();
                $table->boolean('is_active')->default(false)->index();

                $table->decimal('overtime_multiplier', 8, 4)->default(2);
                $table->decimal('rest_day_overtime_multiplier', 8, 4)->default(2);
                $table->decimal('holiday_overtime_multiplier', 8, 4)->default(2);

                $table->unsignedInteger('late_tolerance_minutes')->default(0);
                $table->string('late_discount_mode', 40)->default('none');
                $table->unsignedInteger('late_minutes_to_absence')->default(0);

                $table->string('early_leave_discount_mode', 40)->default('none');
                $table->string('absence_discount_mode', 40)->default('incident_only');
                $table->string('rest_day_worked_mode', 40)->default('informational');
                $table->string('holiday_worked_mode', 40)->default('informational');

                $table->json('settings')->nullable();
                $table->text('notes')->nullable();

                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->index(['company_id', 'is_active']);
                $table->index(['company_id', 'status']);
                $table->unique(['company_id', 'name'], 'payroll_policies_company_name_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_policies');
    }
};
