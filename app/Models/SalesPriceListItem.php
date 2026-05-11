<?php

namespace App\Models;

use App\Models\SalesPriceList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesPriceListItem extends Model
{
    protected $fillable = [
        'sales_price_list_id',
        'company_id',
        'product_id',
        'product_variant_id',
        'min_quantity',
        'price_without_tax',
        'tax_rate',
        'price_with_tax',
        'discount_percent',
        'valid_from',
        'valid_to',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'min_quantity' => 'decimal:6',
        'price_without_tax' => 'decimal:6',
        'tax_rate' => 'decimal:4',
        'price_with_tax' => 'decimal:6',
        'discount_percent' => 'decimal:4',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (SalesPriceListItem $item): void {
            $price = (float) ($item->price_without_tax ?? 0);
            $taxRate = (float) ($item->tax_rate ?? 0);

            $item->price_with_tax = round($price * (1 + ($taxRate / 100)), 6);
        });
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(SalesPriceList::class, 'sales_price_list_id');
    }
}
