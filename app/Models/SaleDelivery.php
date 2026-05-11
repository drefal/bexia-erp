<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleDelivery extends Model
{
    protected $fillable = [
        'company_id',
        'sales_order_id',
        'number',
        'status',
        'delivery_type',
        'planned_at',
        'delivered_at',
        'warehouse_id',
        'source_location_id',
        'destination_location_id',
        'stock_movement_id',
        'created_by_user_id',
        'cancelled_by_user_id',
        'cancelled_at',
        'notes',
    ];

    protected $casts = [
        'planned_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class, 'sales_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleDeliveryLine::class, 'sale_delivery_id');
    }
}
