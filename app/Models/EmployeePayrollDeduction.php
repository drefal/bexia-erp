<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePayrollDeduction extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'employee_payroll_purchase_id',
        'payroll_concept_id',
        'type',
        'code',
        'name',
        'original_amount',
        'outstanding_amount',
        'period_amount',
        'start_date',
        'end_date',
        'max_periods',
        'applied_periods',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'period_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'max_periods' => 'integer',
        'applied_periods' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (EmployeePayrollDeduction $deduction): void {
            if (blank($deduction->status)) {
                $deduction->status = 'active';
            }

            if (blank($deduction->code)) {
                $deduction->code = static::defaultCodeForType((string) $deduction->type);
            }

            if (blank($deduction->name)) {
                $deduction->name = static::typeOptions()[(string) $deduction->type] ?? 'Descuento de empleado';
            }

            if ((float) $deduction->outstanding_amount <= 0 && in_array((string) $deduction->status, ['active', 'paused'], true)) {
                $deduction->status = 'paid';
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }

    public function purchase()
    {
        return $this->belongsTo(
            \App\Models\EmployeePayrollPurchase::class,
            'employee_payroll_purchase_id'
        );
    }

    public function concept()
    {
        return $this->belongsTo(\App\Models\PayrollConcept::class, 'payroll_concept_id');
    }

    public function applications()
    {
        return $this->hasMany(\App\Models\EmployeePayrollDeductionApplication::class);
    }

    public static function typeOptions(): array
    {
        return [
            'loan' => 'Préstamo empleado',
            'advance' => 'Anticipo de nómina',
            'recurring_discount' => 'Descuento recurrente',
            'product_purchase' => 'Compra vía nómina',
            'other' => 'Otro descuento',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'active' => 'Activo',
            'paused' => 'Pausado',
            'paid' => 'Pagado',
            'cancelled' => 'Cancelado',
        ];
    }

    public static function defaultCodeForType(string $type): string
    {
        return match ($type) {
            'advance' => 'ANTICIPO_NOMINA',
            'recurring_discount' => 'DESCUENTO_RECURRENTE',
            'product_purchase' => 'COMPRA_EMPLEADO',
            'loan' => 'PRESTAMO_EMPLEADO',
            default => 'DESCUENTO_RECURRENTE',
        };
    }
}
