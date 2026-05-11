<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDeliveryLine extends Model
{
    protected $fillable = [
        'sale_delivery_id',
        'sales_order_id',
        'sales_order_line_id',
        'company_id',
        'product_id',
        'product_variant_id',
        'product_label',
        'variant_label',
        'ordered_quantity',
        'quantity',
        'unit_cost',
        'stock_movement_line_id',
        'notes',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(SaleDelivery::class, 'sale_delivery_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class, 'sales_order_id');
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(SaleOrderLine::class, 'sales_order_line_id');
    }
}
