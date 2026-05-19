<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomsOffice extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'display_name',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getLabelAttribute(): string
    {
        return trim((string) ($this->display_name ?: $this->name));
    }
}
