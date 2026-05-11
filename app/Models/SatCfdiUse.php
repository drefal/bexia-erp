<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatCfdiUse extends Model
{
    protected $table = 'sat_cfdi_uses';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function getDisplayNameAttribute(): string
    {
        return trim($this->code . ' - ' . $this->name);
    }
}
