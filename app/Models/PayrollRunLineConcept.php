<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRunLineConcept extends Model
{
    protected $fillable = [
        'company_id',
        'payroll_run_id',
        'payroll_run_line_id',
        'employee_id',
        'payroll_concept_id',
        'code',
        'name',
        'type',
        'category',
        'source',
        'unit',
        'quantity',
        'rate',
        'amount',
        'metadata',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'rate' => 'decimal:4',
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'sort_order' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function payrollRun()
    {
        return $this->belongsTo(\App\Models\PayrollRun::class);
    }

    public function payrollRunLine()
    {
        return $this->belongsTo(\App\Models\PayrollRunLine::class);
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }

    public function concept()
    {
        return $this->belongsTo(\App\Models\PayrollConcept::class, 'payroll_concept_id');
    }
}
