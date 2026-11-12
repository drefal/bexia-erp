<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommerceChannel extends Model
{
    protected $fillable = [
        'code',
        'name',
        'is_active',
        'capabilities',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capabilities' => 'array',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(
            CommerceAccount::class,
            'commerce_channel_id'
        );
    }
}
