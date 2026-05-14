<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'default_payment_term_id' => 'integer',
    ];

    public function defaultPaymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class, 'default_payment_term_id');
    }


}
