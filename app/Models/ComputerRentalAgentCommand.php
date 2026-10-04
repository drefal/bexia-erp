<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComputerRentalAgentCommand extends Model
{
    protected $guarded = [];

    protected $casts = [
        'requested_at' => 'datetime',
        'delivered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function station()
    {
        return $this->belongsTo(
            ComputerRentalStation::class,
            'station_id'
        );
    }
}
