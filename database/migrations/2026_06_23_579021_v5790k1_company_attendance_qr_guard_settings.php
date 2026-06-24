<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'attendance_same_device_block_minutes')) {
                $table->unsignedSmallInteger('attendance_same_device_block_minutes')
                    ->default(10)
                    ->after('name');
            }

            if (! Schema::hasColumn('companies', 'attendance_qr_guard_enabled')) {
                $table->boolean('attendance_qr_guard_enabled')
                    ->default(true)
                    ->after('attendance_same_device_block_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            foreach ([
                'attendance_qr_guard_enabled',
                'attendance_same_device_block_minutes',
            ] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
