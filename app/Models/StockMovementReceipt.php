<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockMovementReceipt extends Model
{
    protected $fillable = [
        'stock_movement_id',
        'company_id',
        'received_by',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(
            StockMovement::class,
            'stock_movement_id'
        );
    }

    public function lines(): HasMany
    {
        return $this->hasMany(
            StockMovementReceiptLine::class,
            'stock_movement_receipt_id'
        );
    }
}
