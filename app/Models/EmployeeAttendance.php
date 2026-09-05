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
        'clock_in_attendance_terminal_id',
        'clock_out_attendance_terminal_id',
        'clock_in_photo_path',
        'clock_out_photo_path',
        'break_minutes',
        'expected_hours',
        'worked_minutes',
        'worked_hours',
        'late_minutes',
        'early_leave_minutes',
        'overtime_minutes',
        'source',
        'clock_in_method',
        'clock_out_method',
        'clock_in_hr_attendance_location_id',
        'clock_out_hr_attendance_location_id',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_in_accuracy_meters',
        'clock_in_distance_meters',
        'clock_in_location_status',
        'clock_out_latitude',
        'clock_out_longitude',
        'clock_out_accuracy_meters',
        'clock_out_distance_meters',
        'clock_out_location_status',
        'mobile_review_status',
        'mobile_reviewed_by_user_id',
        'mobile_reviewed_at',
        'mobile_review_notes',
        'clock_in_ip_address',
        'clock_out_ip_address',
        'clock_in_user_agent',
        'clock_out_user_agent',
        'clock_in_device_fingerprint',
        'clock_out_device_fingerprint',
        'clock_in_device_info',
        'clock_out_device_info',
        'clock_in_device_guard_status',
        'clock_out_device_guard_status',
        'clock_in_device_guard_message',
        'clock_out_device_guard_message',
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
        'clock_in_latitude' => 'decimal:7',
        'clock_in_longitude' => 'decimal:7',
        'clock_in_accuracy_meters' => 'integer',
        'clock_in_distance_meters' => 'integer',
        'clock_out_latitude' => 'decimal:7',
        'clock_out_longitude' => 'decimal:7',
        'clock_out_accuracy_meters' => 'integer',
        'clock_out_distance_meters' => 'integer',
        'mobile_reviewed_at' => 'datetime',
        'clock_in_device_info' => 'array',
        'clock_out_device_info' => 'array',
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


    public function clockInAttendanceLocation()
    {
        return $this->belongsTo(\App\Models\HrAttendanceLocation::class, 'clock_in_hr_attendance_location_id');
    }

    public function clockOutAttendanceLocation()
    {
        return $this->belongsTo(\App\Models\HrAttendanceLocation::class, 'clock_out_hr_attendance_location_id');
    }


    public function clockInAttendanceTerminal()
    {
        return $this->belongsTo(\App\Models\AttendanceTerminal::class, 'clock_in_attendance_terminal_id');
    }

    public function clockOutAttendanceTerminal()
    {
        return $this->belongsTo(\App\Models\AttendanceTerminal::class, 'clock_out_attendance_terminal_id');
    }

    public function mobileReviewedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'mobile_reviewed_by_user_id');
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
    public function setWorkedMinutesAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['worked_minutes'] = null;

            return;
        }

        if (is_numeric($value)) {
            $this->attributes['worked_minutes'] = (int) round((float) $value);

            return;
        }

        $this->attributes['worked_minutes'] = $value;
    }


}
