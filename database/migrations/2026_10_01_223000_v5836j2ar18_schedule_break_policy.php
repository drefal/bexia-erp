<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_work_schedules')) {
            if (! Schema::hasColumn('hr_work_schedules', 'break_minutes')) {
                Schema::table('hr_work_schedules', function (Blueprint $table): void {
                    $table->unsignedSmallInteger('break_minutes')
                        ->default(0);
                });
            }

            if (! Schema::hasColumn('hr_work_schedules', 'break_sessions_allowed')) {
                Schema::table('hr_work_schedules', function (Blueprint $table): void {
                    $table->unsignedSmallInteger('break_sessions_allowed')
                        ->default(0);
                });
            }

            if (! Schema::hasColumn('hr_work_schedules', 'tolerance_late_minutes')) {
                Schema::table('hr_work_schedules', function (Blueprint $table): void {
                    $table->unsignedSmallInteger('tolerance_late_minutes')
                        ->default(0);
                });
            }

            if (! Schema::hasColumn('hr_work_schedules', 'tolerance_early_leave_minutes')) {
                Schema::table('hr_work_schedules', function (Blueprint $table): void {
                    $table->unsignedSmallInteger('tolerance_early_leave_minutes')
                        ->default(0);
                });
            }
        }

        if (Schema::hasTable('hr_work_schedule_days')) {
            if (! Schema::hasColumn('hr_work_schedule_days', 'break_applies')) {
                Schema::table('hr_work_schedule_days', function (Blueprint $table): void {
                    $table->boolean('break_applies')
                        ->default(false);
                });
            }

            if (! Schema::hasColumn('hr_work_schedule_days', 'break_sessions_allowed')) {
                Schema::table('hr_work_schedule_days', function (Blueprint $table): void {
                    $table->unsignedSmallInteger('break_sessions_allowed')
                        ->default(0);
                });
            }
        }

        /*
         * Compatibilidad con horarios existentes.
         *
         * La cabecera toma como política inicial el primer día laborable
         * configurado. Los detalles actuales NO pierden sus tolerancias.
         *
         * break_applies se deriva del break_minutes histórico:
         * > 0 = sí aplica
         * = 0 = no aplica
         *
         * Como hasta ahora el checador permitía una sola comida:
         * break_minutes > 0 => 1 salida
         */
        if (
            Schema::hasTable('hr_work_schedules')
            && Schema::hasTable('hr_work_schedule_days')
        ) {
            $schedules = DB::table('hr_work_schedules')
                ->select('id')
                ->orderBy('id')
                ->get();

            foreach ($schedules as $schedule) {
                $firstWorkingDay = DB::table('hr_work_schedule_days')
                    ->where('hr_work_schedule_id', $schedule->id)
                    ->where('is_working_day', true)
                    ->orderBy('day_index')
                    ->first();

                $breakMinutes = max(
                    0,
                    (int) ($firstWorkingDay->break_minutes ?? 0)
                );

                $lateTolerance = max(
                    0,
                    (int) ($firstWorkingDay->tolerance_late_minutes ?? 0)
                );

                $earlyTolerance = max(
                    0,
                    (int) ($firstWorkingDay->tolerance_early_leave_minutes ?? 0)
                );

                DB::table('hr_work_schedules')
                    ->where('id', $schedule->id)
                    ->update([
                        'break_minutes' => $breakMinutes,
                        'break_sessions_allowed' => $breakMinutes > 0 ? 1 : 0,
                        'tolerance_late_minutes' => $lateTolerance,
                        'tolerance_early_leave_minutes' => $earlyTolerance,
                    ]);

                $days = DB::table('hr_work_schedule_days')
                    ->where('hr_work_schedule_id', $schedule->id)
                    ->get(['id', 'is_working_day', 'break_minutes']);

                foreach ($days as $day) {
                    $applies =
                        (bool) $day->is_working_day
                        && (int) ($day->break_minutes ?? 0) > 0;

                    DB::table('hr_work_schedule_days')
                        ->where('id', $day->id)
                        ->update([
                            'break_applies' => $applies,
                            'break_sessions_allowed' => $applies ? 1 : 0,
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hr_work_schedule_days')) {
            if (Schema::hasColumn('hr_work_schedule_days', 'break_sessions_allowed')) {
                Schema::table('hr_work_schedule_days', function (Blueprint $table): void {
                    $table->dropColumn('break_sessions_allowed');
                });
            }

            if (Schema::hasColumn('hr_work_schedule_days', 'break_applies')) {
                Schema::table('hr_work_schedule_days', function (Blueprint $table): void {
                    $table->dropColumn('break_applies');
                });
            }
        }

        if (Schema::hasTable('hr_work_schedules')) {
            foreach ([
                'break_minutes',
                'break_sessions_allowed',
                'tolerance_late_minutes',
                'tolerance_early_leave_minutes',
            ] as $column) {
                if (Schema::hasColumn('hr_work_schedules', $column)) {
                    Schema::table('hr_work_schedules', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
