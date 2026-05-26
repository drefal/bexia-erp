<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_work_schedule_days')) {
            Schema::create('hr_work_schedule_days', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('hr_work_schedule_id')->constrained('hr_work_schedules')->cascadeOnDelete();

                $table->string('day_of_week', 20);
                $table->unsignedTinyInteger('day_index')->default(1);

                $table->boolean('is_working_day')->default(true);
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();

                $table->unsignedSmallInteger('break_minutes')->default(0);
                $table->unsignedSmallInteger('tolerance_late_minutes')->default(0);
                $table->unsignedSmallInteger('tolerance_early_leave_minutes')->default(0);

                $table->decimal('expected_hours', 8, 2)->nullable();

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->unique(['hr_work_schedule_id', 'day_of_week'], 'hr_work_schedule_day_unique');
                $table->index(['company_id', 'day_of_week']);
                $table->index(['company_id', 'is_working_day']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_work_schedule_days');
    }
};
