<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingAccount extends Model
{
    protected $fillable = [
        'company_id',
        'parent_id',
        'code',
        'name',
        'type',
        'normal_balance',
        'sat_grouping_code',
        'account_usage',
        'allow_manual_entries',
        'is_active',
        'is_system',
        'description',
    ];

    protected $casts = [
        'allow_manual_entries' => 'boolean',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function satGrouping(): BelongsTo
    {
        return $this->belongsTo(SatAccountGrouping::class, 'sat_grouping_code', 'code');
    }

    public function defaultForJournals(): HasMany
    {
        return $this->hasMany(AccountingJournal::class, 'default_account_id');
    }
}
