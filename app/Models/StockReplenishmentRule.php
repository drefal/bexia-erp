<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReplenishmentRule extends Model
{
    protected $fillable = [
        'company_id',
        'warehouse_id',
        'location_id',
        'product_id',
        'product_variant_id',
        'min_quantity',
        'max_quantity',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
            'preferred_supplier_id',
        'lead_time_days',
        'priority',
];

    protected $casts = [
        'min_quantity' => 'decimal:6',
        'max_quantity' => 'decimal:6',
        'is_active' => 'boolean',
        'lead_time_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockReplenishmentRule $rule): void {
            if (! $rule->created_by && auth()->id()) {
                $rule->created_by = auth()->id();
            }

            if (! $rule->updated_by && auth()->id()) {
                $rule->updated_by = auth()->id();
            }
        });

        static::updating(function (StockReplenishmentRule $rule): void {
            if (auth()->id()) {
                $rule->updated_by = auth()->id();
            }
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_variant_id');
    }
}
