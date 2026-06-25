<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_attendance_locations', function (Blueprint $table): void {
            if (! Schema::hasColumn('hr_attendance_locations', 'geofence_type')) {
                $table->string('geofence_type', 20)
                    ->default('circle')
                    ->after('address')
                    ->index();
            }

            if (! Schema::hasColumn('hr_attendance_locations', 'polygon_coordinates')) {
                $table->json('polygon_coordinates')
                    ->nullable()
                    ->after('radius_meters');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendance_locations', function (Blueprint $table): void {
            if (Schema::hasColumn('hr_attendance_locations', 'polygon_coordinates')) {
                $table->dropColumn('polygon_coordinates');
            }

            if (Schema::hasColumn('hr_attendance_locations', 'geofence_type')) {
                $table->dropColumn('geofence_type');
            }
        });
    }
};
