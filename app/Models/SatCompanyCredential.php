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
        'cer_file_path',
        'key_file_path',
        'password_encrypted',
        'credential_type',
        'certificate_valid_from',
        'certificate_valid_to',
        'credential_status',
        'is_enabled',
        'last_verified_at',
        'last_error_message',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'certificate_valid_from' => 'datetime',
        'certificate_valid_to' => 'datetime',
        'last_verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'password_encrypted',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
