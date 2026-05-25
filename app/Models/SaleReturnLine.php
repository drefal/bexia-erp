<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnLine extends Model
{
    protected $fillable = [
        'sale_return_id',
        'sale_delivery_id',
        'sale_delivery_line_id',
        'sales_order_id',
        'sales_order_line_id',
        'company_id',
        'product_id',
        'product_variant_id',
        'product_label',
        'variant_label',
        'quantity',
        'unit_cost',
        'total_cost',
        'stock_lot_id',
        'lot_tracking_metadata',
        'stock_serial_number_id',
        'serial_tracking_metadata',
        'stock_movement_line_id',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'lot_tracking_metadata' => 'array',
        'serial_tracking_metadata' => 'array',
        'metadata' => 'array',
    ];

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'sale_return_id');
    }
}
