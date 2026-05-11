<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'product_category_id',
        'inventory_unit_id',
        'internal_reference',
        'name',
        'description',
        'product_type',
        'tracking',
        'costing_method',
        'standard_cost',
        'sale_price',
        'purchase_price',
        'sat_product_service_code',
        'sat_unit_code',
        'inventory_account_id',
        'cogs_account_id',
        'sales_income_account_id',
        'can_be_sold',
        'can_be_purchased',
        'available_in_pos',
        'is_active',
    ];

    protected $casts = [
        'standard_cost' => 'decimal:4',
        'sale_price' => 'decimal:4',
        'purchase_price' => 'decimal:4',
        'can_be_sold' => 'boolean',
        'can_be_purchased' => 'boolean',
        'available_in_pos' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Product::class, 'product_template_id');
    }

    public function templateAttributes(): HasMany
    {
        return $this->hasMany(ProductTemplateAttribute::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_template_id');
    }

    public function primaryImage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductImage::class, 'product_template_id')
            ->where('is_primary', true)
            ->orderBy('sort_order');
    }

}
