<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComputerRentalSession extends Model
{
    protected $table = 'computer_rental_sessions';

    protected $guarded = [];

    protected $casts = [
        'hourly_rate' => 'decimal:4',
        'prepaid_price' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'cancellation_grace_minutes' => 'integer',
        'amount' => 'decimal:4',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'sent_to_pos_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(
            ComputerRentalStation::class,
            'station_id'
        );
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(
            ComputerRentalRate::class,
            'rate_id'
        );
    }

    public function posPoint(): BelongsTo
    {
        return $this->belongsTo(PosPoint::class);
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(
            PosSession::class,
            'pos_session_id'
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function posOrder(): BelongsTo
    {
        return $this->belongsTo(
            PosOrder::class,
            'pos_order_id'
        );
    }

    public function consumptionLines()
    {
        return $this->hasMany(
            ComputerRentalSessionLine::class,
            'computer_rental_session_id'
        )->orderBy('id');
    }
}
