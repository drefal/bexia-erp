<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatUnitCode extends Model
{
    protected $fillable = [
        'type',
        'code',
        'name',
        'symbol',
        'is_active',
        'description',
        'note',
        'valid_from',
        'valid_to',
];

    protected $casts = [
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',];
}
