<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomBalloonSize extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'dimensions',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(CustomBalloonPrice::class, 'size_id');
    }

    public function modelSizes(): HasMany
    {
        return $this->hasMany(
            CustomBalloonModelSize::class,
            'size_id'
        );
    }
}
