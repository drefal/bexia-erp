<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComputerRentalStation extends Model
{
    protected $table = 'computer_rental_stations';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'last_heartbeat_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function posPoint(): BelongsTo
    {
        return $this->belongsTo(PosPoint::class);
    }

    public function defaultRate(): BelongsTo
    {
        return $this->belongsTo(
            ComputerRentalRate::class,
            'default_rate_id'
        );
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(
            ComputerRentalSession::class,
            'station_id'
        );
    }

    public function activeSession()
    {
        return $this->hasOne(
            ComputerRentalSession::class,
            'station_id'
        )->where('status', 'active');
    }
}
