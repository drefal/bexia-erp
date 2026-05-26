<?php

namespace App\Models;

use App\Support\EmployeeAttendanceCalculator;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendance extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'hr_work_schedule_id',
        'attendance_date',
        'status',
        'expected_start_at',
        'expected_end_at',
        'clock_in_at',
        'clock_out_at',
        'break_minutes',
        'expected_hours',
        'worked_minutes',
        'worked_hours',
        'late_minutes',
        'early_leave_minutes',
        'overtime_minutes',
        'source',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'expected_start_at' => 'datetime',
        'expected_end_at' => 'datetime',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'break_minutes' => 'integer',
        'expected_hours' => 'decimal:2',
        'worked_minutes' => 'integer',
        'worked_hours' => 'decimal:2',
        'late_minutes' => 'integer',
        'early_leave_minutes' => 'integer',
        'overtime_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (EmployeeAttendance $attendance): void {
            EmployeeAttendanceCalculator::apply($attendance);
        });
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }

    public function workSchedule()
    {
        return $this->belongsTo(\App\Models\HrWorkSchedule::class, 'hr_work_schedule_id');
    }


    /*
     * V5.64.16b-start
     * Incidencias generadas desde asistencia.
     */
    public function incidents()
    {
        return $this->hasMany(\App\Models\EmployeeIncident::class, 'employee_attendance_id');
    }
    /*
     * V5.64.16b-end
     */

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by_user_id');
    }

    public static function statusOptions(): array
    {
        return [
            'present' => 'Puntual',
            'late' => 'Retardo',
            'early_leave' => 'Salida temprana',
            'late_early_leave' => 'Retardo y salida temprana',
            'absence' => 'Falta',
            'incomplete' => 'Incompleta',
            'rest_day' => 'Descanso',
            'rest_day_worked' => 'Descanso trabajado',
            'no_schedule' => 'Sin horario',
        ];
    }
}
