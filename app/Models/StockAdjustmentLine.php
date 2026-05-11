<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentLine extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'product_variant_id',
        'lot_id',
        'current_quantity',
        'counted_quantity',
        'difference_quantity',
        'unit_cost',
        'notes',
    ];

    protected $casts = [
        'current_quantity' => 'decimal:6',
        'counted_quantity' => 'decimal:6',
        'difference_quantity' => 'decimal:6',
        'unit_cost' => 'decimal:6',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }
}
