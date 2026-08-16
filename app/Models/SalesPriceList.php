<?php

namespace App\Models;

use App\Models\Company;
use App\Models\SalesPriceListItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesPriceList extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'currency',
        'price_type',
        'calculation_type',
        'formula_basis',
        'base_price_list_id',
        'adjustment_percent',
        'payment_provider',
        'installment_months',
        'public_calculator',
        'public_sort',
        'is_default',
        'is_active',
        'valid_from',
        'valid_to',
        'notes',
    ];

    protected $casts = [
        'adjustment_percent' => 'decimal:4',
        'installment_months' => 'integer',
        'public_calculator' => 'boolean',
        'public_sort' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];


    public function basePriceList(): BelongsTo
    {
        return $this->belongsTo(SalesPriceList::class, 'base_price_list_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesPriceListItem::class, 'sales_price_list_id');
    }
}
