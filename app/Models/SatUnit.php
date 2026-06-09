<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatUnit extends Model
{
    protected $table = 'sat_unit_codes';

    protected $fillable = [
        'type',
        'code',
        'name',
        'symbol',
        'description',
        'note',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];
}
