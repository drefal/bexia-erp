<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeVacationBalance extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'period_start',
        'period_end',
        'years_of_service',
        'entitled_days',
        'carried_over_days',
        'adjusted_days',
        'taken_days',
        'pending_days',
        'expired_days',
        'policy_code',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'years_of_service' => 'integer',
        'entitled_days' => 'decimal:2',
        'carried_over_days' => 'decimal:2',
        'adjusted_days' => 'decimal:2',
        'taken_days' => 'decimal:2',
        'pending_days' => 'decimal:2',
        'expired_days' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by_user_id');
    }
}
