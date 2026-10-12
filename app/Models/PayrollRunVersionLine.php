<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRunVersionLine extends Model
{
    protected $fillable = [
        'company_id',
        'payroll_run_version_id',
        'original_payroll_run_line_id',
        'employee_id',
        'employee_contract_id',
        'employee_name_snapshot',
        'contract_number_snapshot',
        'employee_snapshot',
        'contract_snapshot',
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
        'concepts_snapshot',
        'notes',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'daily_salary' => 'decimal:2',
        'hourly_rate' => 'decimal:4',
        'period_days' => 'decimal:2',
        'payable_days' => 'decimal:2',
        'worked_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'absence_days' => 'decimal:2',
        'rest_day_worked_days' => 'decimal:2',
        'approved_incident_deduction_days' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'incident_perceptions' => 'decimal:2',
        'incident_deductions' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'deductions_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'details' => 'array',
        'concepts_snapshot' => 'array',
        'employee_snapshot' => 'array',
        'contract_snapshot' => 'array',
    ];

    public function version()
    {
        return $this->belongsTo(
            PayrollRunVersion::class,
            'payroll_run_version_id'
        );
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function contract()
    {
        return $this->belongsTo(
            EmployeeContract::class,
            'employee_contract_id'
        );
    }
}
