<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovementReceiptLine extends Model
{
    protected $fillable = [
        'stock_movement_receipt_id',
        'stock_movement_line_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(
            StockMovementReceipt::class,
            'stock_movement_receipt_id'
        );
    }

    public function movementLine(): BelongsTo
    {
        return $this->belongsTo(
            StockMovementLine::class,
            'stock_movement_line_id'
        );
    }
}
