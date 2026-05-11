<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashDenomination extends Model
{
    protected $table = 'cash_denominations';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'value' => 'decimal:2',
    ];
}
