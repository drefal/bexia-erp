<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockSerialNumber extends Model
{
    protected $fillable = [
        'company_id',
        'product_id',
        'product_variant_id',
        'lot_id',
        'serial_number',
        'current_warehouse_id',
        'current_location_id',
        'status',
        'source_type',
        'source_id',
        'purchase_order_id',
        'purchase_receipt_id',
        'stock_movement_line_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_variant_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'lot_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'current_warehouse_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'current_location_id');
    }

    public function getDisplayNameAttribute(): string
    {
        $serial = trim((string) $this->serial_number);

        return $serial !== '' ? $serial : 'Serie #' . $this->id;
    }
}
