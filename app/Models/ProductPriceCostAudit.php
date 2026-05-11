<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceCostAudit extends Model
{
    protected $fillable = [
        'company_id',
        'product_id',
        'user_id',
        'field_name',
        'field_label',
        'old_value',
        'new_value',
        'old_numeric_value',
        'new_numeric_value',
        'source',
        'notes',
        'product_reference',
        'product_name',
        'changed_at',
    ];

    protected $casts = [
        'old_numeric_value' => 'decimal:6',
        'new_numeric_value' => 'decimal:6',
        'changed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
