<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairOrderPart extends Model
{
    protected $fillable = [
        'company_id',
        'repair_order_id',
        'total_price',
        'unit_price',
        'product_name',
        'source_type',
        'product_id',
        'sku',
        'description',
        'quantity',
        'unit_cost',
        'total_cost',
        'stock_movement_id',
        'requested_by',
        'delivered_by',
        'delivered_at',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'delivered_at' => 'datetime',
    ];

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $part): void {
            if (($part->description === null || trim((string) $part->description) === '') && filled($part->product_name)) {
                $part->description = (string) $part->product_name;
            }

            if ($part->description === null || trim((string) $part->description) === '') {
                $part->description = 'Refaccion/material';
            }

            $quantity = (float) ($part->quantity ?: 0);
            $unitCost = (float) ($part->unit_cost ?: 0);
            $unitPrice = (float) ($part->unit_price ?: 0);

            if ($quantity > 0 && $unitCost >= 0) {
                $part->total_cost = round($quantity * $unitCost, 2);
            }

            if ($quantity > 0 && $unitPrice >= 0) {
                $part->total_price = round($quantity * $unitPrice, 2);
            }
        });
    }

}
