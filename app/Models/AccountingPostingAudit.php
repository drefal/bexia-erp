<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPostingAudit extends Model
{
    protected $fillable = [
        'company_id',
        'source_type',
        'source_id',
        'accounting_entry_id',
        'event',
        'status',
        'message',
        'request_meta',
        'response_meta',
        'created_by_user_id',
    ];

    protected $casts = [
        'request_meta' => 'array',
        'response_meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }
}
