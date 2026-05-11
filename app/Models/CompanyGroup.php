<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyGroup extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'active',
        'free_trial',
        'max_companies',
        'max_branches',
        'max_users',
        'notes',
    ];

    protected $casts = [
        'active' => 'boolean',
        'free_trial' => 'boolean',
        'max_companies' => 'integer',
        'max_branches' => 'integer',
        'max_users' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Organization::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(\App\Models\Company::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class)
            ->withPivot('is_group_admin')
            ->withTimestamps();
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class)
            ->withPivot('is_group_admin')
            ->wherePivot('is_group_admin', true);
    }
}
