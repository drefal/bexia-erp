<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceCompanyChannel extends Model
{
    protected $fillable = [
        'company_id',
        'commerce_channel_id',
        'is_enabled',
        'status',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(
            CommerceChannel::class,
            'commerce_channel_id'
        );
    }
}
