<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomBalloonOrderLine extends Model
{
    protected $fillable = [
        'company_id',
        'order_id',
        'model_id',
        'size_id',
        'model_name_snapshot',
        'size_name_snapshot',
        'quantity',
        'base_unit_price',
        'model_adjustment',
        'unit_price',
        'subtotal',
        'personalization_text',
        'personalization_color',
        'personalization_font',
        'notes',
        'customer_reference_image_path',
        'design_image_path',
        'final_image_path',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'base_unit_price' => 'decimal:2',
        'model_adjustment' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomBalloonOrder::class, 'order_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(CustomBalloonModel::class, 'model_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(CustomBalloonSize::class, 'size_id');
    }

    public function extras(): HasMany
    {
        return $this->hasMany(
            CustomBalloonOrderLineExtra::class,
            'order_line_id'
        );
    }
}
