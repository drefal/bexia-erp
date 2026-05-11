<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRate extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'tax_type',
        'factor_type',
        'rate',
        'is_withholding',
        'is_active',
        'sort_order',
        'description',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'is_withholding' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getDisplayNameAttribute(): string
    {
        $rate = rtrim(rtrim(number_format((float) $this->rate * 100, 4, '.', ''), '0'), '.');

        if ($this->factor_type === 'exento') {
            return "{$this->code} - {$this->name} Exento";
        }

        return "{$this->code} - {$this->name} {$rate}%";
    }
}
