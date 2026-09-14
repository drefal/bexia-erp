<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'attendance_geofence_policy')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->string('attendance_geofence_policy', 32)
                    ->default('fixed');
            });
        }

        Schema::table('employee_attendances', function (Blueprint $table): void {
            $table->boolean('meal_punch_required')
                ->default(false);

            $table->unsignedBigInteger('meal_out_hr_attendance_location_id')
                ->nullable();

            $table->decimal('meal_out_latitude', 10, 7)
                ->nullable();

            $table->decimal('meal_out_longitude', 10, 7)
                ->nullable();

            $table->integer('meal_out_accuracy_meters')
                ->nullable();

            $table->integer('meal_out_distance_meters')
                ->nullable();

            $table->unsignedBigInteger('meal_in_hr_attendance_location_id')
                ->nullable();

            $table->decimal('meal_in_latitude', 10, 7)
                ->nullable();

            $table->decimal('meal_in_longitude', 10, 7)
                ->nullable();

            $table->integer('meal_in_accuracy_meters')
                ->nullable();

            $table->integer('meal_in_distance_meters')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_attendances', function (Blueprint $table): void {
            $table->dropColumn([
                'meal_punch_required',

                'meal_out_hr_attendance_location_id',
                'meal_out_latitude',
                'meal_out_longitude',
                'meal_out_accuracy_meters',
                'meal_out_distance_meters',

                'meal_in_hr_attendance_location_id',
                'meal_in_latitude',
                'meal_in_longitude',
                'meal_in_accuracy_meters',
                'meal_in_distance_meters',
            ]);
        });

        if (Schema::hasColumn('employees', 'attendance_geofence_policy')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->dropColumn('attendance_geofence_policy');
            });
        }
    }
};
