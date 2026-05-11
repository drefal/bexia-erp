<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'auditable_type',
        'auditable_id',
        'event',
        'field_name',
        'old_value',
        'new_value',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        ?int $companyId,
        ?int $userId,
        string $auditableType,
        ?int $auditableId,
        string $event,
        ?string $fieldName = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        array $metadata = []
    ): self {
        return self::query()->create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'event' => $event,
            'field_name' => $fieldName,
            'old_value' => $oldValue === null ? null : ['value' => $oldValue],
            'new_value' => $newValue === null ? null : ['value' => $newValue],
            'metadata' => $metadata ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
