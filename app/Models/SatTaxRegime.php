<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatTaxRegime extends Model
{
    protected $table = 'sat_tax_regimes';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function getDisplayNameAttribute(): string
    {
        return trim($this->code . ' - ' . $this->name);
    }
}
