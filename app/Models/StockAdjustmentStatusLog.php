<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentStatusLog extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'company_id',
        'from_status',
        'to_status',
        'action',
        'reason',
        'notes',
        'user_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }
}
