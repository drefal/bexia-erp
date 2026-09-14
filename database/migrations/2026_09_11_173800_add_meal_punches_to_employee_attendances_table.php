<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_attendances', function (Blueprint $table): void {
            $table->timestamp('meal_out_at')->nullable();
            $table->timestamp('meal_in_at')->nullable();

            $table->unsignedBigInteger('meal_out_attendance_terminal_id')->nullable();
            $table->unsignedBigInteger('meal_in_attendance_terminal_id')->nullable();

            $table->string('meal_out_photo_path', 1000)->nullable();
            $table->string('meal_in_photo_path', 1000)->nullable();

            $table->string('meal_out_method', 50)->nullable();
            $table->string('meal_in_method', 50)->nullable();

            $table->string('meal_out_ip_address', 255)->nullable();
            $table->string('meal_in_ip_address', 255)->nullable();

            $table->text('meal_out_user_agent')->nullable();
            $table->text('meal_in_user_agent')->nullable();

            $table->string('meal_out_device_fingerprint', 64)->nullable();
            $table->string('meal_in_device_fingerprint', 64)->nullable();

            $table->json('meal_out_device_info')->nullable();
            $table->json('meal_in_device_info')->nullable();

            $table->string('meal_out_device_guard_status', 100)->nullable();
            $table->string('meal_in_device_guard_status', 100)->nullable();

            $table->text('meal_out_device_guard_message')->nullable();
            $table->text('meal_in_device_guard_message')->nullable();

            $table->string('meal_out_location_status', 100)->nullable();
            $table->string('meal_in_location_status', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_attendances', function (Blueprint $table): void {
            $table->dropColumn([
                'meal_out_at',
                'meal_in_at',

                'meal_out_attendance_terminal_id',
                'meal_in_attendance_terminal_id',

                'meal_out_photo_path',
                'meal_in_photo_path',

                'meal_out_method',
                'meal_in_method',

                'meal_out_ip_address',
                'meal_in_ip_address',

                'meal_out_user_agent',
                'meal_in_user_agent',

                'meal_out_device_fingerprint',
                'meal_in_device_fingerprint',

                'meal_out_device_info',
                'meal_in_device_info',

                'meal_out_device_guard_status',
                'meal_in_device_guard_status',

                'meal_out_device_guard_message',
                'meal_in_device_guard_message',

                'meal_out_location_status',
                'meal_in_location_status',
            ]);
        });
    }
};
