<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOperationType extends Model
{
    protected $fillable = [
        'company_id',
        'warehouse_id',
        'code',
        'name',
        'operation_kind',
        'source_location_id',
        'destination_location_id',
        'reference_prefix',
        'next_number',
        'sequence',
        'description',
        'is_active',
    ];

    protected $casts = [
        'next_number' => 'integer',
        'sequence' => 'integer',
        'is_active' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'destination_location_id');
    }
}
