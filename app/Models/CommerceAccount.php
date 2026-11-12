<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceAccount extends Model
{
    protected $fillable = [
        'company_id',
        'commerce_channel_id',
        'name',
        'external_account_id',
        'country',
        'currency',
        'status',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_successful_sync_at' => 'datetime',
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
