<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePayrollDeductionApplication extends Model
{
    protected $fillable = [
        'company_id',
        'employee_payroll_deduction_id',
        'payroll_run_id',
        'payroll_run_line_id',
        'payroll_run_line_concept_id',
        'employee_id',
        'amount',
        'balance_before',
        'balance_after',
        'applied_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'applied_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function deduction()
    {
        return $this->belongsTo(\App\Models\EmployeePayrollDeduction::class, 'employee_payroll_deduction_id');
    }

    public function payrollRun()
    {
        return $this->belongsTo(\App\Models\PayrollRun::class);
    }

    public function payrollRunLine()
    {
        return $this->belongsTo(\App\Models\PayrollRunLine::class);
    }

    public function payrollRunLineConcept()
    {
        return $this->belongsTo(\App\Models\PayrollRunLineConcept::class);
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }
}
