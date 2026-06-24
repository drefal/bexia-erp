<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            if (! Schema::hasColumn('employees', 'attendance_qr_token')) {
                $table->string('attendance_qr_token', 80)->nullable()->after('badge_id');
                $table->unique('attendance_qr_token');
            }

            if (! Schema::hasColumn('employees', 'attendance_qr_enabled')) {
                $table->boolean('attendance_qr_enabled')->default(true)->after('attendance_qr_token');
            }

            if (! Schema::hasColumn('employees', 'attendance_qr_generated_at')) {
                $table->timestamp('attendance_qr_generated_at')->nullable()->after('attendance_qr_enabled');
            }

            if (! Schema::hasColumn('employees', 'attendance_pin')) {
                $table->string('attendance_pin')->nullable()->after('attendance_qr_generated_at');
            }
        });

        if (Schema::hasColumn('employees', 'attendance_qr_token')) {
            DB::table('employees')
                ->whereNull('attendance_qr_token')
                ->orderBy('id')
                ->select(['id'])
                ->chunkById(100, function ($employees): void {
                    foreach ($employees as $employee) {
                        DB::table('employees')
                            ->where('id', $employee->id)
                            ->update([
                                'attendance_qr_token' => Str::random(48),
                                'attendance_qr_enabled' => true,
                                'attendance_qr_generated_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            foreach ([
                'attendance_pin',
                'attendance_qr_generated_at',
                'attendance_qr_enabled',
                'attendance_qr_token',
            ] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
