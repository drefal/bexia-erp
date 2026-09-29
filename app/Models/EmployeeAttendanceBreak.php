<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAttendanceBreak extends Model
{
    protected $fillable = [
        'employee_attendance_id',
        'company_id',
        'break_type',
        'started_at',
        'ended_at',

        'start_attendance_terminal_id',
        'end_attendance_terminal_id',

        'start_method',
        'end_method',

        'start_photo_path',
        'end_photo_path',

        'start_ip_address',
        'end_ip_address',

        'start_user_agent',
        'end_user_agent',

        'start_device_fingerprint',
        'end_device_fingerprint',

        'start_device_info',
        'end_device_info',

        'start_device_guard_status',
        'end_device_guard_status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'start_device_info' => 'array',
        'end_device_info' => 'array',
    ];

    public function attendance()
    {
        return $this->belongsTo(EmployeeAttendance::class, 'employee_attendance_id');
    }

    public function startTerminal()
    {
        return $this->belongsTo(
            AttendanceTerminal::class,
            'start_attendance_terminal_id'
        );
    }

    public function endTerminal()
    {
        return $this->belongsTo(
            AttendanceTerminal::class,
            'end_attendance_terminal_id'
        );
    }

    public function actualMinutes(): ?int
    {
        if (! $this->started_at || ! $this->ended_at) {
            return null;
        }

        return max(
            0,
            (int) round($this->started_at->diffInMinutes($this->ended_at))
        );
    }
}
