<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentForm extends Model
{
    protected $table = 'payment_forms';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_reference' => 'boolean',
        'requires_bank' => 'boolean',
        'is_cash' => 'boolean',
        'is_credit' => 'boolean',
    ];
}
