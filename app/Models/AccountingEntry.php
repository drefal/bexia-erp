<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingEntry extends Model
{
    protected $fillable = [
        'company_id',
        'journal_id',
        'entry_number',
        'entry_date',
        'status',
        'source_type',
        'source_id',
        'source_label',
        'currency',
        'total_debit',
        'total_credit',
        'posted_at',
        'cancelled_at',
        'cancelled_by_entry_id',
        'created_by_user_id',
        'posted_by_user_id',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'total_debit' => 'decimal:6',
        'total_credit' => 'decimal:6',
        'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'journal_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingEntryLine::class, 'accounting_entry_id');
    }

    public function cancelledByEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cancelled_by_entry_id');
    }
}
