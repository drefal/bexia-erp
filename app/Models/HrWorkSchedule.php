<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrWorkSchedule extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'schedule_type',
        'start_time',
        'end_time',
        'work_days',
        'hours_per_day',
        'hours_per_week',
        'break_minutes',
        'break_sessions_allowed',
        'tolerance_late_minutes',
        'tolerance_early_leave_minutes',
        'is_active',
    ];

    protected $casts = [
        'work_days' => 'array',
        'hours_per_day' => 'decimal:2',
        'hours_per_week' => 'decimal:2',
        'break_minutes' => 'integer',
        'break_sessions_allowed' => 'integer',
        'tolerance_late_minutes' => 'integer',
        'tolerance_early_leave_minutes' => 'integer',
        'is_active' => 'boolean',
    ];


    /*
     * V5.64.14b-start
     * Detalle operativo por día del horario.
     */
    public function days(): HasMany
    {
        return $this->hasMany(HrWorkScheduleDay::class, 'hr_work_schedule_id')
            ->orderBy('day_index');
    }
    /*
     * V5.64.14b-end
     */

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
