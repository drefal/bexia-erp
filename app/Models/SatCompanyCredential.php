<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SatCompanyCredential extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'rfc',
        'legal_name',
        'certificate_serial',
        'credential_status',
        'is_enabled',
        'last_verified_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'last_verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
