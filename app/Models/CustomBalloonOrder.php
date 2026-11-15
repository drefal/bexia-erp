<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomBalloonOrder extends Model
{
    protected $fillable = [
        'company_id',
        'number',
        'customer_contact_id',
        'customer_name_snapshot',
        'customer_phone_snapshot',
        'status',
        'ordered_at',
        'promised_at',
        'subtotal',
        'extras_total',
        'discount_total',
        'total',
        'deposit_amount',
        'balance_amount',
        'payment_status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancel_reason',
        'delivered_at',
        'pos_order_id',
        'sent_to_pos_at',
        'metadata',
    ];

    protected $casts = [
        'ordered_at' => 'datetime',
        'promised_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'delivered_at' => 'datetime',
        'sent_to_pos_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'extras_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'customer_contact_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CustomBalloonOrderLine::class, 'order_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CustomBalloonOrderEvent::class, 'order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
