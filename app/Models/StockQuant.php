<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockQuant extends Model
{
    protected $fillable = [
        'company_id',
        'warehouse_id',
        'location_id',
        'product_id',
        'product_variant_id',
        'lot_id',
        'quantity',
        'reserved_quantity',
        'average_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'reserved_quantity' => 'decimal:6',
        'average_cost' => 'decimal:6',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    public function getAvailableQuantityAttribute(): float
    {
        return (float) $this->quantity - (float) $this->reserved_quantity;
    }
}
