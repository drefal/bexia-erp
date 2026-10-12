<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAttendanceManualDay extends Model
{
    protected $fillable = [
        'company_id',
        'attendance_date',
        'is_locked',
        'locked_at',
        'locked_by_user_id',
        'unlocked_at',
        'unlocked_by_user_id',
        'unlock_reason',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function lockedBy()
    {
        return $this->belongsTo(
            User::class,
            'locked_by_user_id'
        );
    }

    public function unlockedBy()
    {
        return $this->belongsTo(
            User::class,
            'unlocked_by_user_id'
        );
    }
}
