<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatCfdiConcept extends Model
{
    protected $fillable = [
        'sat_cfdi_document_id',
        'company_id',
        'product_key',
        'identification_number',
        'description',
        'quantity',
        'unit_key',
        'unit_name',
        'unit_price',
        'amount',
        'discount',
        'taxes',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'unit_price' => 'decimal:6',
        'amount' => 'decimal:6',
        'discount' => 'decimal:6',
        'taxes' => 'array',
        'metadata' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(SatCfdiDocument::class, 'sat_cfdi_document_id');
    }
}
