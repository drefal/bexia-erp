<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollFiscalRunResult extends Model
{
    protected $fillable = [
        'company_id',
        'payroll_run_id',
        'payroll_run_version_id',
        'calculation_mode_snapshot',
        'employee_id',
        'payroll_run_line_id',
        'run_type',
        'payment_date',
        'fiscal_year',
        'fiscal_month',
        'taxable_income',
        'exempt_income',
        'isr_before_subsidy',
        'subsidy_caused',
        'subsidy_applied',
        'isr_withheld',
        'isr_tariff_code',
        'subsidy_rule_code',
        'calculation_snapshot',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'payroll_run_id' => 'integer',
        'payroll_run_version_id' => 'integer',
        'employee_id' => 'integer',
        'payroll_run_line_id' => 'integer',
        'payment_date' => 'date',
        'fiscal_year' => 'integer',
        'fiscal_month' => 'integer',

        'taxable_income' => 'decimal:6',
        'exempt_income' => 'decimal:6',
        'isr_before_subsidy' => 'decimal:6',
        'subsidy_caused' => 'decimal:6',
        'subsidy_applied' => 'decimal:6',
        'isr_withheld' => 'decimal:6',

        'calculation_snapshot' => 'array',
    ];

    public function payrollRun()
    {
        return $this->belongsTo(
            PayrollRun::class
        );
    }

    public function payrollRunVersion()
    {
        return $this->belongsTo(
            PayrollRunVersion::class
        );
    }

    public function employee()
    {
        return $this->belongsTo(
            Employee::class
        );
    }

    public function payrollRunLine()
    {
        return $this->belongsTo(
            PayrollRunLine::class
        );
    }
}
