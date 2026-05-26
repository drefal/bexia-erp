<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    protected $fillable = [
        'company_id',
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
        'approval_status',
        'approval_request_id',
        'approval_requested_by_user_id',
        'approval_requested_at',
        'approved_by_user_id',
        'approved_at',
        'rejected_by_user_id',
        'rejected_at',
        'rejection_reason',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'calculated_by_user_id',
        'calculated_at',
    ];

    protected $casts = [
        'rejected_at' => 'datetime',
        'approved_at' => 'datetime',
        'approval_requested_at' => 'datetime',
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
        'calculated_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function lines()
    {
        return $this->hasMany(\App\Models\PayrollRunLine::class);
    }

    public function perceptionApplications()
    {
        return $this->hasMany(\App\Models\EmployeePayrollPerceptionApplication::class);
    }

    public function deductionApplications()
    {
        return $this->hasMany(\App\Models\EmployeePayrollDeductionApplication::class);
    }

    public function lineConcepts()
    {
        return $this->hasMany(\App\Models\PayrollRunLineConcept::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by_user_id');
    }

    public function calculatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'calculated_by_user_id');
    }

    public static function statusOptions(): array
    {
        return [
            'draft' => 'Borrador',
            'calculated' => 'Calculada',
            'pending_approval' => 'Pendiente de aprobación',
            'approved' => 'Aprobada',
            'closed' => 'Cerrada',
            'cancelled' => 'Cancelada',
        ];
    }

    public static function periodTypeOptions(): array
    {
        return [
            'semanal' => 'Semanal',
            'catorcenal' => 'Catorcenal',
            'quincenal' => 'Quincenal',
            'mensual' => 'Mensual',
        ];
    }
}
