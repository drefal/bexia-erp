<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingJournal extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'default_account_id',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'default_account_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(AccountingJournalMapping::class, 'journal_id');
    }
}
