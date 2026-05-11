<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingJournalMapping extends Model
{
    protected $fillable = [
        'company_id',
        'module',
        'operation_type',
        'journal_id',
        'is_active',
        'options',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'options' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'journal_id');
    }
}
