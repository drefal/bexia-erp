<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatCfdiCancellationReason extends Model
{
    protected $fillable = [
        'code',
        'name',
        'requires_replacement_uuid',
        'active',
        'notes',
    ];

    protected $casts = [
        'requires_replacement_uuid' => 'boolean',
        'active' => 'boolean',
    ];

    public function label(): string
    {
        return $this->code.' - '.$this->name;
    }
}
