<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatCfdiTax extends Model
{
    protected $fillable = [
        'sat_cfdi_document_id',
        'company_id',
        'tax_direction',
        'tax',
        'factor_type',
        'rate_or_fee',
        'base',
        'amount',
        'metadata',
    ];

    protected $casts = [
        'rate_or_fee' => 'decimal:6',
        'base' => 'decimal:6',
        'amount' => 'decimal:6',
        'metadata' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(SatCfdiDocument::class, 'sat_cfdi_document_id');
    }
}
