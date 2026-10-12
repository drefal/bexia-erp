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
        'payment_scheme',
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
        'sat_work_state_code',
        'daily_salary',
        'integrated_daily_salary',
        'social_security_base_salary',
        'is_unionized',
];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'signed_at' => 'date',
        'probation_end_date' => 'date',
        /*
         * BEXIA_V5836_CONTRACT_SALARY_CASTS
         */
        'base_salary' => 'decimal:2',
        'daily_salary' => 'decimal:4',
        'integrated_daily_salary' => 'decimal:4',
        'social_security_base_salary' => 'decimal:4',
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

    public function generateAutomaticContractNumber(): string
    {
        $company = $this->company;

        $companyName = trim((string) ($company?->name ?? 'EMPRESA'));
        $firstWord = preg_split('/\\s+/', $companyName)[0] ?? 'EMPRESA';

        $companyCode = strtoupper(
            preg_replace('/[^A-Za-z0-9]/', '', $firstWord)
        );

        if ($companyCode === '') {
            $companyCode = 'EMPRESA';
        }

        $year = $this->start_date
            ? $this->start_date->format('Y')
            : now()->format('Y');

        return sprintf(
            'CTR-%s-%s-%06d',
            $companyCode,
            $year,
            (int) $this->getKey()
        );
    }

    public function assignAutomaticContractNumberIfMissing(): void
    {
        if (filled($this->contract_number) || ! $this->exists) {
            return;
        }

        $this->forceFill([
            'contract_number' => $this->generateAutomaticContractNumber(),
        ])->saveQuietly();
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
