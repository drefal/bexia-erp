<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_attendances', function (Blueprint $table): void {
            $table->unsignedBigInteger('clock_in_attendance_terminal_id')->nullable();
            $table->unsignedBigInteger('clock_out_attendance_terminal_id')->nullable();
            $table->string('clock_in_photo_path', 500)->nullable();
            $table->string('clock_out_photo_path', 500)->nullable();

            $table->foreign('clock_in_attendance_terminal_id', 'ea_clock_in_terminal_fk')
                ->references('id')
                ->on('attendance_terminals')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('clock_out_attendance_terminal_id', 'ea_clock_out_terminal_fk')
                ->references('id')
                ->on('attendance_terminals')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index('clock_in_attendance_terminal_id', 'ea_clock_in_terminal_idx');
            $table->index('clock_out_attendance_terminal_id', 'ea_clock_out_terminal_idx');
        });
    }

    public function down(): void
    {
        Schema::table('employee_attendances', function (Blueprint $table): void {
            $table->dropForeign('ea_clock_in_terminal_fk');
            $table->dropForeign('ea_clock_out_terminal_fk');
            $table->dropIndex('ea_clock_in_terminal_idx');
            $table->dropIndex('ea_clock_out_terminal_idx');
            $table->dropColumn([
                'clock_in_attendance_terminal_id',
                'clock_out_attendance_terminal_id',
                'clock_in_photo_path',
                'clock_out_photo_path',
            ]);
        });
    }
};
