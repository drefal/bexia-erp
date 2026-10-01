<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('employee_attendances')
            && ! Schema::hasColumn(
                'employee_attendances',
                'break_sessions_allowed'
            )
        ) {
            Schema::table('employee_attendances', function (Blueprint $table): void {
                $table->unsignedSmallInteger('break_sessions_allowed')
                    ->default(0);
            });

            /*
             * Compatibilidad histórica:
             * antes del multi-descanso, cualquier jornada con descanso
             * permitía exactamente una salida.
             */
            DB::table('employee_attendances')
                ->where('break_minutes', '>', 0)
                ->update([
                    'break_sessions_allowed' => 1,
                ]);
        }

        if (Schema::hasTable('employee_attendance_breaks')) {
            DB::statement(
                'ALTER TABLE employee_attendance_breaks
                 DROP CONSTRAINT IF EXISTS employee_attendance_break_type_unique'
            );

            DB::statement(
                'CREATE INDEX IF NOT EXISTS employee_attendance_break_lookup_idx
                 ON employee_attendance_breaks
                 (employee_attendance_id, break_type, started_at)'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_attendance_breaks')) {
            $duplicate = DB::table('employee_attendance_breaks')
                ->select(
                    'employee_attendance_id',
                    'break_type',
                    DB::raw('COUNT(*) AS total')
                )
                ->groupBy('employee_attendance_id', 'break_type')
                ->havingRaw('COUNT(*) > 1')
                ->first();

            if ($duplicate) {
                throw new \RuntimeException(
                    'No se puede restaurar el índice único: existen asistencias con múltiples descansos.'
                );
            }

            DB::statement(
                'DROP INDEX IF EXISTS employee_attendance_break_lookup_idx'
            );

            DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'employee_attendance_breaks'::regclass
          AND conname = 'employee_attendance_break_type_unique'
    ) THEN
        ALTER TABLE employee_attendance_breaks
            ADD CONSTRAINT employee_attendance_break_type_unique
            UNIQUE (employee_attendance_id, break_type);
    END IF;
END
$$
SQL
            );
        }

        if (
            Schema::hasTable('employee_attendances')
            && Schema::hasColumn(
                'employee_attendances',
                'break_sessions_allowed'
            )
        ) {
            Schema::table('employee_attendances', function (Blueprint $table): void {
                $table->dropColumn('break_sessions_allowed');
            });
        }
    }
};
