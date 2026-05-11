<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingEntryLine extends Model
{
    protected $fillable = [
        'company_id',
        'accounting_entry_id',
        'account_id',
        'line_number',
        'label',
        'partner_contact_id',
        'debit',
        'credit',
        'currency',
        'source_type',
        'source_id',
        'metadata',
    ];

    protected $casts = [
        'debit' => 'decimal:6',
        'credit' => 'decimal:6',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_id');
    }
}
