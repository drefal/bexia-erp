<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiInsightUserAccess extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'is_enabled',
        'access_level',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'last_access_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'last_access_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AiInsightUserAccess $access): void {
            if (auth()->id()) {
                $access->created_by_user_id ??= auth()->id();
                $access->updated_by_user_id ??= auth()->id();
            }
        });

        static::updating(function (AiInsightUserAccess $access): void {
            if (auth()->id()) {
                $access->updated_by_user_id = auth()->id();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
