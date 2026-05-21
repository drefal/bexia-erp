<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountReceivable extends Model
{
    protected $fillable = [
        'company_id',
        'number',
        'status',
        'source_type',
        'source_id',
        'sale_order_id',
        'invoice_id',
        'customer_contact_id',
        'customer_name',
        'customer_reference',
        'issue_date',
        'due_date',
        'currency',
        'subtotal',
        'tax_total',
        'total',
        'collected_total',
        'balance_total',
        'accounting_status',
        'accounting_entry_id',
        'accounting_posted_at',
        'accounting_error_message',
        'notes',
        'metadata',
        'created_by_user_id',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:4',
        'tax_total' => 'decimal:4',
        'total' => 'decimal:4',
        'collected_total' => 'decimal:4',
        'balance_total' => 'decimal:4',
        'accounting_posted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customerContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'customer_contact_id');
    }

    public function accountingEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AccountReceivablePayment::class);
    }
}
