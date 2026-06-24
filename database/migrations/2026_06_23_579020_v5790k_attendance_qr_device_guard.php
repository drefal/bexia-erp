<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_attendances', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_attendances', 'clock_in_device_fingerprint')) {
                $table->string('clock_in_device_fingerprint', 64)->nullable()->after('clock_in_user_agent');
                $table->index('clock_in_device_fingerprint');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_out_device_fingerprint')) {
                $table->string('clock_out_device_fingerprint', 64)->nullable()->after('clock_in_device_fingerprint');
                $table->index('clock_out_device_fingerprint');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_in_device_info')) {
                $table->json('clock_in_device_info')->nullable()->after('clock_out_device_fingerprint');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_out_device_info')) {
                $table->json('clock_out_device_info')->nullable()->after('clock_in_device_info');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_in_device_guard_status')) {
                $table->string('clock_in_device_guard_status')->nullable()->after('clock_out_device_info');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_out_device_guard_status')) {
                $table->string('clock_out_device_guard_status')->nullable()->after('clock_in_device_guard_status');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_in_device_guard_message')) {
                $table->text('clock_in_device_guard_message')->nullable()->after('clock_out_device_guard_status');
            }

            if (! Schema::hasColumn('employee_attendances', 'clock_out_device_guard_message')) {
                $table->text('clock_out_device_guard_message')->nullable()->after('clock_in_device_guard_message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_attendances', function (Blueprint $table): void {
            foreach ([
                'clock_out_device_guard_message',
                'clock_in_device_guard_message',
                'clock_out_device_guard_status',
                'clock_in_device_guard_status',
                'clock_out_device_info',
                'clock_in_device_info',
                'clock_out_device_fingerprint',
                'clock_in_device_fingerprint',
            ] as $column) {
                if (Schema::hasColumn('employee_attendances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
