<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomBalloonOrderLineExtra extends Model
{
    protected $fillable = [
        'company_id',
        'order_line_id',
        'extra_id',
        'extra_name_snapshot',
        'pricing_type_snapshot',
        'quantity',
        'unit_price',
        'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(
            CustomBalloonOrderLine::class,
            'order_line_id'
        );
    }

    public function extra(): BelongsTo
    {
        return $this->belongsTo(CustomBalloonExtra::class, 'extra_id');
    }
}
