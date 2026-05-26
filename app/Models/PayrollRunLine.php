<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRunLine extends Model
{
    protected $fillable = [
        'company_id',
        'payroll_run_id',
        'employee_id',
        'employee_contract_id',
        'status',
        'base_salary',
        'salary_type',
        'daily_salary',
        'hourly_rate',
        'period_days',
        'payable_days',
        'attendance_records',
        'worked_minutes',
        'worked_hours',
        'late_minutes',
        'early_leave_minutes',
        'overtime_minutes',
        'overtime_hours',
        'absence_days',
        'rest_day_worked_days',
        'approved_incidents_count',
        'approved_incident_deduction_days',
        'approved_incident_deduction_minutes',
        'base_amount',
        'overtime_amount',
        'incident_perceptions',
        'incident_deductions',
        'gross_amount',
        'deductions_amount',
        'net_amount',
        'details',
        'notes',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'daily_salary' => 'decimal:2',
        'hourly_rate' => 'decimal:4',
        'period_days' => 'decimal:2',
        'payable_days' => 'decimal:2',
        'attendance_records' => 'integer',
        'worked_minutes' => 'integer',
        'worked_hours' => 'decimal:2',
        'late_minutes' => 'integer',
        'early_leave_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'overtime_hours' => 'decimal:2',
        'absence_days' => 'decimal:2',
        'rest_day_worked_days' => 'decimal:2',
        'approved_incidents_count' => 'integer',
        'approved_incident_deduction_days' => 'decimal:2',
        'approved_incident_deduction_minutes' => 'integer',
        'base_amount' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'incident_perceptions' => 'decimal:2',
        'incident_deductions' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'deductions_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'details' => 'array',
    ];

    public function perceptionApplications()
    {
        return $this->hasMany(\App\Models\EmployeePayrollPerceptionApplication::class);
    }

    public function deductionApplications()
    {
        return $this->hasMany(\App\Models\EmployeePayrollDeductionApplication::class);
    }

    public function concepts()
    {
        return $this->hasMany(\App\Models\PayrollRunLineConcept::class)->orderBy('sort_order')->orderBy('id');
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function payrollRun()
    {
        return $this->belongsTo(\App\Models\PayrollRun::class);
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }

    public function contract()
    {
        return $this->belongsTo(\App\Models\EmployeeContract::class, 'employee_contract_id');
    }
}
