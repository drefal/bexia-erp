<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollFiscalEmployeeMonth extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'fiscal_year',
        'fiscal_month',
        'taxable_income',
        'exempt_income',
        'isr_before_subsidy',
        'subsidy_caused',
        'subsidy_applied',
        'isr_withheld',
        'ordinary_runs_count',
        'extraordinary_runs_count',
        'last_payroll_run_id',
        'calculated_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'fiscal_year' => 'integer',
        'fiscal_month' => 'integer',

        'taxable_income' => 'decimal:6',
        'exempt_income' => 'decimal:6',
        'isr_before_subsidy' => 'decimal:6',
        'subsidy_caused' => 'decimal:6',
        'subsidy_applied' => 'decimal:6',
        'isr_withheld' => 'decimal:6',

        'ordinary_runs_count' => 'integer',
        'extraordinary_runs_count' => 'integer',

        'last_payroll_run_id' => 'integer',
        'calculated_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(
            Employee::class
        );
    }

    public function lastPayrollRun()
    {
        return $this->belongsTo(
            PayrollRun::class,
            'last_payroll_run_id'
        );
    }
}
