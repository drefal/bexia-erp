<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollGroup extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'payroll_periodicity_id',
        'calculation_mode',
        'is_active',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'payroll_periodicity_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payrollPeriodicity(): BelongsTo
    {
        return $this->belongsTo(
            PayrollPeriodicity::class,
            'payroll_periodicity_id'
        );
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function payrollRuns(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    public static function calculationModeOptions(): array
    {
        return [
            'operational' => 'Nómina simple',
            'fiscal_preview' => 'Nómina fiscal sin timbrado',
            'fiscal_cfdi' => 'Nómina fiscal con CFDI',
        ];
    }

    public function calculationMode(): string
    {
        $mode = (string) (
            $this->calculation_mode
            ?: 'operational'
        );

        return array_key_exists(
            $mode,
            static::calculationModeOptions()
        )
            ? $mode
            : 'operational';
    }

    public function calculatesFiscalTaxes(): bool
    {
        return in_array(
            $this->calculationMode(),
            ['fiscal_preview', 'fiscal_cfdi'],
            true
        );
    }

    public function requiresSatConcepts(): bool
    {
        return $this->calculatesFiscalTaxes();
    }

    public function enablesCfdi(): bool
    {
        return $this->calculationMode()
            === 'fiscal_cfdi';
    }

    public function displayLabel(): string
    {
        return trim(
            ($this->code ? $this->code . ' - ' : '')
            . $this->name
        );
    }
}
