<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatUnit extends Model
{
    protected $fillable = [
        'key',
        'name',
        'symbol',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getDisplayNameAttribute(): string
    {
        return trim($this->key . ' - ' . $this->name);
    }
}
