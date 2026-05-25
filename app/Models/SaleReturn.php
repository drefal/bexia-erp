<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    protected $fillable = [
        'company_id',
        'sale_delivery_id',
        'sales_order_id',
        'number',
        'status',
        'return_type',
        'warehouse_id',
        'source_location_id',
        'destination_location_id',
        'stock_movement_id',
        'reason',
        'notes',
        'created_by_user_id',
        'returned_at',
        'metadata',
    ];

    protected $casts = [
        'returned_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(SaleReturnLine::class, 'sale_return_id');
    }
}
