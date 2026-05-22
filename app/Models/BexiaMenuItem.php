<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BexiaMenuItem extends Model
{
    protected $fillable = [
        'group_id',
        'key',
        'label',
        'default_label',
        'sort',
        'is_visible',
        'is_system',
        'source',
        'file_path',
        'class_name',
        'route_name',
        'permission_name',
        'meta',
    ];

    protected $casts = [
        'sort' => 'integer',
        'is_visible' => 'boolean',
        'is_system' => 'boolean',
        'meta' => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(BexiaMenuGroup::class, 'group_id');
    }
}
