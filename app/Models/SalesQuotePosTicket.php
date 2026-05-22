<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesQuotePosTicket extends Model
{
    protected $table = 'sales_quote_pos_tickets';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expired_at' => 'datetime',
    ];
}
