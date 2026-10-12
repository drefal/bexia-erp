<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRunVersion extends Model
{
    protected $fillable = [
        'company_id',
        'payroll_run_id',
        'version_number',
        'payroll_group_id',
        'calculation_mode_snapshot',
        'run_type',
        'snapshot_reason',
        'name',
        'period_type',
        'period_start',
        'period_end',
        'payment_date',
        'status',
        'currency',
        'employees_count',
        'base_total',
        'overtime_total',
        'perceptions_total',
        'gross_total',
        'deductions_total',
        'net_total',
        'summary',
        'issuer_snapshot',
        'calculated_by_user_id',
        'calculated_at',
        'snapshot_by_user_id',
        'snapshot_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
        'employees_count' => 'integer',
        'base_total' => 'decimal:2',
        'overtime_total' => 'decimal:2',
        'perceptions_total' => 'decimal:2',
        'gross_total' => 'decimal:2',
        'deductions_total' => 'decimal:2',
        'net_total' => 'decimal:2',
        'summary' => 'array',
        'issuer_snapshot' => 'array',
        'calculated_at' => 'datetime',
        'snapshot_at' => 'datetime',
    ];

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function payrollGroup()
    {
        return $this->belongsTo(PayrollGroup::class);
    }

    public function lines()
    {
        return $this->hasMany(PayrollRunVersionLine::class);
    }

    public function calculatedBy()
    {
        return $this->belongsTo(User::class, 'calculated_by_user_id');
    }

    public function snapshotBy()
    {
        return $this->belongsTo(User::class, 'snapshot_by_user_id');
    }
}
