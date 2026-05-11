<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPurchaseUnit extends Model
{
    protected $table = 'product_purchase_units';

    protected $fillable = [
        'company_id',
        'product_id',
        'sat_unit_key',
        'sat_unit_name',
        'name',
        'factor',
        'is_default',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'factor' => 'decimal:6',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
