<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    protected $fillable = [
        'company_id',
        'parent_id',
        'code',
        'name',
        'inventory_account_id',
        'cogs_account_id',
        'sales_income_account_id',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_category_id');
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
}
