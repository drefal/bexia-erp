<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAttribute extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'is_variant',
        'is_active',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'is_variant' => 'boolean',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProductAttributeAssignment::class);
    }

}
