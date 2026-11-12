<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceAuthField extends Model
{
    protected $fillable = [
        'commerce_connector_version_id',
        'field_key',
        'label',
        'field_type',
        'credential_owner',
        'is_required',
        'is_secret',
        'is_active',
        'sort_order',
        'help_text',
        'validation_rules',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_secret' => 'boolean',
        'is_active' => 'boolean',
        'validation_rules' => 'array',
    ];

    public function connectorVersion(): BelongsTo
    {
        return $this->belongsTo(
            CommerceConnectorVersion::class,
            'commerce_connector_version_id'
        );
    }
}
