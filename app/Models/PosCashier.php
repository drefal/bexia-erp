<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class PosCashier extends Model
{
    protected $table = 'pos_cashiers';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'can_discount' => 'boolean',
        'can_cancel' => 'boolean',
        'can_open_cash_drawer' => 'boolean',
    ];

    public function setPlainPinAttribute($value): void
    {
        $value = trim((string) $value);

        if ($value !== '') {
            $this->attributes['pin_hash'] = Hash::make($value);
        }
    }
}
