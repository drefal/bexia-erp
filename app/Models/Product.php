<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'company_id',
        'product_template_id',
        'product_category_id',
        'inventory_unit_id',
        'sku',
        'internal_reference',
        'barcode',
        'model',
        'brand',
        'material',
        'color',
        'product_line',
        'condition',
        'catalog',
        'responsible_user_id',
        'name',
        'variant_name',
        'variant_signature',
        'is_variant',
        'description',
        'image_path',
        'product_type',
        'invoice_policy',
        'tracking',
        'costing_method',
        'standard_cost',
        'sale_price',
        'purchase_price',
        'weight',
        'volume',
        'purchase_lead_time_days',
        'customer_lead_time_days',
        'manufacturing_lead_time_days',
        'country_of_origin',
        'hs_code',
        'sat_product_service_code',
        'sat_unit_code',
        'hazardous_material_code',
        'hazardous_packaging_code',
        'tariff_fraction',
        'customs_unit_code',
        'sale_description',
        'purchase_description',
        'last_purchase_cost',
        'last_supplier_name',
        'last_purchase_at',
        'extra_attributes',
        'inventory_account_id',
        'cogs_account_id',
        'sales_income_account_id',
        'can_be_sold',
        'can_be_purchased',
        'available_in_pos',
        'is_pos_favorite',
        'weigh_with_scale',
        'include_in_global_invoice',
        'allow_out_of_stock_sales',
        'is_active',
        'parent_product_id',
        'has_variants',
        'variant_group',
        'variant_value',
        'sat_tax_object_code',        'purchase_pack_units',
        'purchase_min_quantity',
        'purchase_multiple_quantity',
        'preferred_supplier_id',
        'sale_tax_rate',
        'average_cost_without_tax',
        'sale_price_without_tax',
        'purchase_tax_rate',
];

    protected $casts = [
        'standard_cost' => 'decimal:4',
        'sale_price' => 'decimal:4',
        'purchase_price' => 'decimal:4',
        'weight' => 'decimal:4',
        'volume' => 'decimal:4',
        'purchase_lead_time_days' => 'decimal:2',
        'customer_lead_time_days' => 'decimal:2',
        'manufacturing_lead_time_days' => 'decimal:2',
        'last_purchase_cost' => 'decimal:4',
        'last_purchase_at' => 'datetime',
        'extra_attributes' => 'array',
        'is_variant' => 'boolean',
        'can_be_sold' => 'boolean',
        'can_be_purchased' => 'boolean',
        'available_in_pos' => 'boolean',
        'is_pos_favorite' => 'boolean',
        'weigh_with_scale' => 'boolean',
        'include_in_global_invoice' => 'boolean',
        'allow_out_of_stock_sales' => 'boolean',
        'is_active' => 'boolean',
        'has_variants' => 'boolean',
];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProductTemplate::class, 'product_template_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'inventory_account_id');
    }

    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'cogs_account_id');
    }

    public function salesIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'sales_income_account_id');
    }

    public function satProductService(): BelongsTo
    {
        return $this->belongsTo(SatProductServiceCode::class, 'sat_product_service_code', 'code');
    }

    public function variantAttributeValues(): HasMany
    {
        return $this->hasMany(ProductVariantAttributeValue::class);
    }

    public function attributeAssignments(): HasMany
    {
        return $this->hasMany(ProductAttributeAssignment::class);
    }


    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function primaryImage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->where('is_primary', true)
            ->orderBy('sort_order');
    }


    public function parentProduct()
    {
        return $this->belongsTo(self::class, 'parent_product_id');
    }

    public function variants()
    {
        return $this->hasMany(self::class, 'parent_product_id');
    }


    public function productTaxRates()
    {
        return $this->hasMany(\App\Models\ProductTaxRate::class);
    }

    public function saleTaxRates()
    {
        return $this->belongsToMany(\App\Models\TaxRate::class, 'product_tax_rates')
            ->withPivot(['company_id', 'usage_type', 'is_active'])
            ->wherePivot('usage_type', 'sale')
            ->wherePivot('is_active', true)
            ->withTimestamps();
    }

    public function purchaseTaxRates()
    {
        return $this->belongsToMany(\App\Models\TaxRate::class, 'product_tax_rates')
            ->withPivot(['company_id', 'usage_type', 'is_active'])
            ->wherePivot('usage_type', 'purchase')
            ->wherePivot('is_active', true)
            ->withTimestamps();
    }



    public function purchaseUnits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ProductPurchaseUnit::class, 'product_id');
    }

    public function activePurchaseUnits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->purchaseUnits()->where('is_active', true);
    }


}
