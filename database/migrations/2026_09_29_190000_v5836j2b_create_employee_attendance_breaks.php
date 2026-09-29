<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_attendance_breaks')) {
            return;
        }

        Schema::create('employee_attendance_breaks', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('employee_attendance_id')
                ->constrained('employee_attendances')
                ->cascadeOnDelete();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('break_type', 40)->default('meal');

            $table->dateTime('started_at')->nullable()->index();
            $table->dateTime('ended_at')->nullable()->index();

            $table->foreignId('start_attendance_terminal_id')
                ->nullable()
                ->constrained('attendance_terminals')
                ->nullOnDelete();

            $table->foreignId('end_attendance_terminal_id')
                ->nullable()
                ->constrained('attendance_terminals')
                ->nullOnDelete();

            $table->string('start_method', 40)->nullable();
            $table->string('end_method', 40)->nullable();

            $table->string('start_photo_path', 500)->nullable();
            $table->string('end_photo_path', 500)->nullable();

            $table->string('start_ip_address', 45)->nullable();
            $table->string('end_ip_address', 45)->nullable();

            $table->text('start_user_agent')->nullable();
            $table->text('end_user_agent')->nullable();

            $table->string('start_device_fingerprint', 64)->nullable();
            $table->string('end_device_fingerprint', 64)->nullable();

            $table->json('start_device_info')->nullable();
            $table->json('end_device_info')->nullable();

            $table->string('start_device_guard_status', 40)->nullable();
            $table->string('end_device_guard_status', 40)->nullable();

            $table->timestamps();

            $table->unique(
                ['employee_attendance_id', 'break_type'],
                'employee_attendance_break_type_unique'
            );

            $table->index(
                ['company_id', 'break_type'],
                'employee_attendance_break_company_type_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_breaks');
    }
};
