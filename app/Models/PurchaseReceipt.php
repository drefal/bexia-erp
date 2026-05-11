<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceipt extends Model
{
    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'number',
        'status',
        'received_at',
        'warehouse_id',
        'location_id',
        'received_by_user_id',
        'total_without_tax',
        'total_tax',
        'total_with_tax',
        'notes',
        'stock_movement_id',
        'inventory_posted_at',
        'stock_quant_posted_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'inventory_posted_at' => 'datetime',
        'stock_quant_posted_at' => 'datetime',
        'total_without_tax' => 'decimal:6',
        'total_tax' => 'decimal:6',
        'total_with_tax' => 'decimal:6',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }
}
