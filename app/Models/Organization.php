<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(\App\Models\Company::class);
    }

    public function companyGroups(): HasMany
    {
        return $this->hasMany(\App\Models\CompanyGroup::class);
    }
}
