<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollIsrTariffBracket extends Model
{
    protected $fillable = [
        'payroll_isr_tariff_id',
        'sort_order',
        'lower_limit',
        'upper_limit',
        'fixed_fee',
        'marginal_rate',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'lower_limit' => 'decimal:6',
        'upper_limit' => 'decimal:6',
        'fixed_fee' => 'decimal:6',
        'marginal_rate' => 'decimal:6',
    ];

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(
            PayrollIsrTariff::class,
            'payroll_isr_tariff_id'
        );
    }
}
