<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommerceConnectorVersion extends Model
{
    protected $fillable = [
        'commerce_channel_id',
        'version',
        'adapter_key',
        'api_version',
        'auth_type',
        'status',
        'capabilities',
        'settings_schema',
        'connection_instructions',
        'is_enabled',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'settings_schema' => 'array',
        'is_enabled' => 'boolean',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(
            CommerceChannel::class,
            'commerce_channel_id'
        );
    }

    public function authFields(): HasMany
    {
        return $this->hasMany(
            CommerceAuthField::class,
            'commerce_connector_version_id'
        );
    }
}
