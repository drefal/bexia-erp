<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'attendance_qr_enabled')) {
                $table->boolean('attendance_qr_enabled')->default(true)->after('sat_constancia_parsed_data');
            }

            if (! Schema::hasColumn('companies', 'attendance_geofence_enabled')) {
                $table->boolean('attendance_geofence_enabled')->default(true)->after('attendance_qr_enabled');
            }

            if (! Schema::hasColumn('companies', 'attendance_allow_outside_geofence')) {
                $table->boolean('attendance_allow_outside_geofence')->default(true)->after('attendance_geofence_enabled');
            }

            if (! Schema::hasColumn('companies', 'attendance_review_outside_geofence')) {
                $table->boolean('attendance_review_outside_geofence')->default(true)->after('attendance_allow_outside_geofence');
            }

            if (! Schema::hasColumn('companies', 'attendance_default_radius_meters')) {
                $table->unsignedInteger('attendance_default_radius_meters')->default(200)->after('attendance_review_outside_geofence');
            }

            if (! Schema::hasColumn('companies', 'attendance_default_accuracy_meters')) {
                $table->unsignedInteger('attendance_default_accuracy_meters')->default(150)->after('attendance_default_radius_meters');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            foreach ([
                'attendance_default_accuracy_meters',
                'attendance_default_radius_meters',
                'attendance_review_outside_geofence',
                'attendance_allow_outside_geofence',
                'attendance_geofence_enabled',
                'attendance_qr_enabled',
            ] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
