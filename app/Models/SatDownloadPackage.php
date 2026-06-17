<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SatDownloadPackage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sat_download_request_id',
        'company_id',
        'package_id',
        'status',
        'file_path',
        'documents_count',
        'checksum',
        'downloaded_at',
        'imported_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
        'imported_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SatDownloadRequest::class, 'sat_download_request_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
