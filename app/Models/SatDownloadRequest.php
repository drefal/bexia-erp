<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SatDownloadRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'requested_by_id',
        'request_uuid',
        'direction',
        'request_kind',
        'date_from',
        'date_to',
        'status',
        'requested_at',
        'finished_at',
        'sat_status_code',
        'sat_message',
        'payload',
        'error_message',
    ];

    protected $casts = [
        'date_from' => 'datetime',
        'date_to' => 'datetime',
        'requested_at' => 'datetime',
        'finished_at' => 'datetime',
        'payload' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(SatDownloadPackage::class);
    }
}
