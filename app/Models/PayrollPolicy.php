<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollPolicy extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'status',
        'is_active',
        'overtime_multiplier',
        'rest_day_overtime_multiplier',
        'holiday_overtime_multiplier',
        'late_tolerance_minutes',
        'late_discount_mode',
        'late_minutes_to_absence',
        'early_leave_discount_mode',
        'absence_discount_mode',
        'rest_day_worked_mode',
        'holiday_worked_mode',
        'settings',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'overtime_multiplier' => 'decimal:4',
        'rest_day_overtime_multiplier' => 'decimal:4',
        'holiday_overtime_multiplier' => 'decimal:4',
        'late_tolerance_minutes' => 'integer',
        'late_minutes_to_absence' => 'integer',
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (PayrollPolicy $policy): void {
            if (blank($policy->status)) {
                $policy->status = 'active';
            }

            if ($policy->is_active) {
                $policy->status = 'active';
            }
        });

        static::saved(function (PayrollPolicy $policy): void {
            if (! $policy->is_active) {
                return;
            }

            static::query()
                ->where('company_id', $policy->company_id)
                ->whereKeyNot($policy->getKey())
                ->where('is_active', true)
                ->update(['is_active' => false]);
        });
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by_user_id');
    }

    public static function statusOptions(): array
    {
        return [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ];
    }

    public static function lateDiscountModeOptions(): array
    {
        return [
            'none' => 'No descontar por retardo',
            'minutes' => 'Descontar minutos efectivos',
            'accumulate_to_absence' => 'Acumular retardos como falta',
        ];
    }

    public static function earlyLeaveDiscountModeOptions(): array
    {
        return [
            'none' => 'No descontar salida temprana',
            'minutes' => 'Descontar minutos efectivos',
        ];
    }

    public static function absenceDiscountModeOptions(): array
    {
        return [
            'incident_only' => 'Solo por incidencia aprobada',
            'attendance_days' => 'Descontar faltas de asistencia',
        ];
    }

    public static function workedDayModeOptions(): array
    {
        return [
            'informational' => 'Solo informativo',
            'pay_with_multiplier' => 'Pagar con multiplicador',
        ];
    }

    public static function defaultValues(?int $companyId = null): array
    {
        return [
            'company_id' => $companyId,
            'name' => 'Política estándar de nómina',
            'status' => 'active',
            'is_active' => true,
            'overtime_multiplier' => 2,
            'rest_day_overtime_multiplier' => 2,
            'holiday_overtime_multiplier' => 2,
            'late_tolerance_minutes' => 0,
            'late_discount_mode' => 'none',
            'late_minutes_to_absence' => 0,
            'early_leave_discount_mode' => 'none',
            'absence_discount_mode' => 'incident_only',
            'rest_day_worked_mode' => 'informational',
            'holiday_worked_mode' => 'informational',
            'settings' => [],
        ];
    }
}
