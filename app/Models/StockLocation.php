<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockLocation extends Model
{
    protected $fillable = [
        'company_id',
        'warehouse_id',
        'parent_id',
        'stock_location_type_id',
        'code',
        'name',
        'barcode',
        'description',
        'is_active',
        'allow_negative_stock',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_negative_stock' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(StockLocationType::class, 'stock_location_type_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(StockLocation::class, 'parent_id');
    }
}
