<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EmployeeContract extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'contract_number',
        'contract_type',
        'status',
        'start_date',
        'end_date',
        'signed_at',
        'probation_end_date',
        'hr_department_id',
        'hr_job_position_id',
        'hr_work_schedule_id',
        'payroll_employer_registration_id',
        'payroll_periodicity_id',
        'base_salary',
        'salary_type',
        'currency',
        'hours_per_week',
        'is_current',
        'file_path',
        'file_original_name',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'sat_contract_type_code',
        'sat_workday_type_code',
        'sat_regime_type_code',
        'sat_risk_position_code',
        'daily_salary',
        'integrated_daily_salary',
        'is_unionized',
];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'signed_at' => 'date',
        'probation_end_date' => 'date',
        'base_salary' => 'decimal:2',
        'hours_per_week' => 'decimal:2',
        'is_current' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }

    public function department()
    {
        return $this->belongsTo(\App\Models\HrDepartment::class, 'hr_department_id');
    }

    public function jobPosition()
    {
        return $this->belongsTo(\App\Models\HrJobPosition::class, 'hr_job_position_id');
    }

    public function workSchedule()
    {
        return $this->belongsTo(\App\Models\HrWorkSchedule::class, 'hr_work_schedule_id');
    }

    public function employerRegistration()
    {
        return $this->belongsTo(\App\Models\PayrollEmployerRegistration::class, 'payroll_employer_registration_id');
    }

    public function payrollPeriodicity()
    {
        return $this->belongsTo(\App\Models\PayrollPeriodicity::class, 'payroll_periodicity_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by_user_id');
    }

    public function getFileUrlAttribute(): ?string
    {
        if (blank($this->file_path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($this->file_path)) {
            return null;
        }

        return asset('storage/' . ltrim($this->file_path, '/'));
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->end_date !== null && $this->end_date->isPast();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->end_date !== null
            && $this->end_date->isFuture()
            && $this->end_date->lte(today()->addDays(30));
    }
}
