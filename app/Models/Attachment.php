<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    protected $fillable = [
        'company_id',
        'attachable_type',
        'attachable_id',
        'target_table',
        'target_id',
        'title',
        'description',
        'disk',
        'storage_path',
        'url',
        'original_filename',
        'mimetype',
        'file_size',
        'checksum',
        'store_fname',
        'source_system',
        'source_model',
        'source_id',
        'source_attachment_id',
        'source_reference',
        'legacy_reference',
        'legacy_company_id',
        'legacy_payload',
        'migrated_at',
        'migration_batch_id',
        'is_legacy',
        'locked',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'attachable_id' => 'integer',
        'target_id' => 'integer',
        'file_size' => 'integer',
        'legacy_company_id' => 'integer',
        'legacy_payload' => 'array',
        'migrated_at' => 'datetime',
        'is_legacy' => 'boolean',
        'locked' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
