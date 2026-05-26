<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePayrollPerception extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'payroll_concept_id',
        'type',
        'code',
        'name',
        'original_amount',
        'remaining_amount',
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
        'remaining_amount' => 'decimal:2',
        'period_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'max_periods' => 'integer',
        'applied_periods' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (EmployeePayrollPerception $perception): void {
            if (blank($perception->status)) {
                $perception->status = 'active';
            }

            if (blank($perception->code)) {
                $perception->code = static::defaultCodeForType((string) $perception->type);
            }

            if (blank($perception->name)) {
                $perception->name = static::typeOptions()[(string) $perception->type] ?? 'Percepción de empleado';
            }

            if ((float) $perception->remaining_amount <= 0 && in_array((string) $perception->status, ['active', 'paused'], true)) {
                $perception->status = 'paid';
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

    public function concept()
    {
        return $this->belongsTo(\App\Models\PayrollConcept::class, 'payroll_concept_id');
    }

    public function applications()
    {
        return $this->hasMany(\App\Models\EmployeePayrollPerceptionApplication::class);
    }

    public static function typeOptions(): array
    {
        return [
            'bonus' => 'Bono productividad',
            'commission' => 'Comisión',
            'gratification' => 'Gratificación',
            'transport_allowance' => 'Apoyo transporte',
            'meal_allowance' => 'Apoyo comida',
            'other' => 'Otra percepción',
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
            'commission' => 'COMISION',
            'gratification' => 'GRATIFICACION',
            'transport_allowance' => 'APOYO_TRANSPORTE',
            'meal_allowance' => 'APOYO_COMIDA',
            'bonus' => 'BONO_PRODUCTIVIDAD',
            default => 'BONO_PRODUCTIVIDAD',
        };
    }
}
