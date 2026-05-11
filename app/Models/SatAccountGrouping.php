<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SatAccountGrouping extends Model
{
    protected $fillable = [
        'code',
        'level',
        'name',
        'account_type',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_active' => 'boolean',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(AccountingAccount::class, 'sat_grouping_code', 'code');
    }
}
