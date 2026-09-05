<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AttendanceTerminal extends Model
{
    use SoftDeletes;

    protected $table = 'attendance_terminals';

    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'name',
        'uuid',
        'active',
        'blocked_at',
        'blocked_reason',
        'device_name',
        'device_model',
        'platform',
        'app_version',
        'mac_address',
        'last_seen_at',
        'last_ip_address',
        'last_user_agent',
        'capabilities',
        'settings',
    ];

    protected $hidden = [
        'token_hash',
        'pairing_code_hash',
    ];

    protected $casts = [
        'active' => 'boolean',
        'blocked_at' => 'datetime',
        'pairing_expires_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'capabilities' => 'array',
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (AttendanceTerminal $terminal): void {
            if (blank($terminal->uuid)) {
                $terminal->uuid = (string) Str::uuid();
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('active', true)
            ->whereNull('blocked_at');
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function isAvailable(): bool
    {
        return $this->active && ! $this->isBlocked();
    }
}
