<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountPayablePayment extends Model
{
    protected $fillable = [
        'company_id',
        'account_payable_id',
        'treasury_account_id',
        'payment_form_id',
        'treasury_movement_id',
        'accounting_entry_id',
        'amount',
        'payment_date',
        'currency',
        'reference',
        'status',
        'posted_at',
        'cancelled_at',
        'notes',
        'metadata',
        'created_by_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'payment_date' => 'date',
        'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function accountPayable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class);
    }

    public function treasuryAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class);
    }

    public function paymentForm(): BelongsTo
    {
        return $this->belongsTo(PaymentForm::class);
    }

    public function treasuryMovement(): BelongsTo
    {
        return $this->belongsTo(TreasuryMovement::class);
    }

    public function accountingEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class);
    }
}
