<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayrollPurchaseInstallment extends Model
{
    protected $fillable = [
        'company_id',
        'employee_payroll_purchase_id',
        'employee_id',
        'employee_payroll_deduction_id',
        'employee_payroll_deduction_application_id',
        'payroll_run_id',
        'installment_number',
        'due_date',
        'scheduled_amount',
        'applied_amount',
        'status',
        'applied_at',
    ];

    protected $casts = [
        'installment_number' => 'integer',
        'due_date' => 'date',
        'scheduled_amount' => 'decimal:2',
        'applied_amount' => 'decimal:2',
        'applied_at' => 'datetime',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(EmployeePayrollPurchase::class, 'employee_payroll_purchase_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function deduction(): BelongsTo
    {
        return $this->belongsTo(EmployeePayrollDeduction::class, 'employee_payroll_deduction_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(
            EmployeePayrollDeductionApplication::class,
            'employee_payroll_deduction_application_id'
        );
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }
}
