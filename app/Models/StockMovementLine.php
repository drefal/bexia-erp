<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovementLine extends Model
{
    protected $fillable = [
        'stock_movement_id',
        'product_id',
        'product_variant_id',
        'lot_id',
        'requested_quantity',
        'done_quantity',
        'unit_cost',
        'notes',
    ];

    protected $casts = [
        'requested_quantity' => 'decimal:6',
        'done_quantity' => 'decimal:6',
        'unit_cost' => 'decimal:6',
    ];

    protected static function booted(): void
    {
        static::saving(function (StockMovementLine $line): void {
            if ((float) $line->requested_quantity <= 0 && (float) $line->done_quantity > 0) {
                $line->requested_quantity = $line->done_quantity;
            }
        });
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }
}
