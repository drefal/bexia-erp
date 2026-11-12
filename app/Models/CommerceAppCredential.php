<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceAppCredential extends Model
{
    protected $fillable = [
        'commerce_connector_version_id',
        'field_key',
    ];

    protected $hidden = [
        'secret_value',
    ];

    protected $casts = [
        'secret_value' => 'encrypted',
    ];

    public function connectorVersion(): BelongsTo
    {
        return $this->belongsTo(
            CommerceConnectorVersion::class,
            'commerce_connector_version_id'
        );
    }
}
