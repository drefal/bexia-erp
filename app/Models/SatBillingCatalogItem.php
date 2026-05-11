<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatBillingCatalogItem extends Model
{
    protected $fillable = [
        'catalog_id',
        'catalog_key',
        'source_sheet',
        'source_row',
        'code',
        'name',
        'description',
        'valid_from',
        'valid_to',
        'extra_attributes',
        'external_key',
        'is_active',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'extra_attributes' => 'array',
        'is_active' => 'boolean',
    ];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(SatBillingCatalog::class, 'catalog_id');
    }
}
