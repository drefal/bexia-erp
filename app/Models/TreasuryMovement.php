<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasuryMovement extends Model
{
    protected $fillable = [
        'company_id',
        'treasury_account_id',
        'payment_form_id',
        'accounting_entry_id',
        'type',
        'source_type',
        'source_id',
        'movement_date',
        'amount',
        'currency_code',
        'reference',
        'description',
        'status',
        'posted_at',
        'cancelled_at',
        'created_by_user_id',
        'metadata',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'amount' => 'decimal:6',
        'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function treasuryAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class);
    }

    public function paymentForm(): BelongsTo
    {
        return $this->belongsTo(PaymentForm::class);
    }

    public function accountingEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }


}
