<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BexiaMenuGroup extends Model
{
    protected $fillable = [
        'key',
        'label',
        'default_label',
        'sort',
        'is_visible',
        'is_system',
        'meta',
    ];

    protected $casts = [
        'sort' => 'integer',
        'is_visible' => 'boolean',
        'is_system' => 'boolean',
        'meta' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(BexiaMenuItem::class, 'group_id')
            ->orderBy('sort')
            ->orderBy('label');
    }
}
