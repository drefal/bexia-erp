<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreasuryAccount extends Model
{
    protected $fillable = [
        'company_id',
        'bank_id',
        'accounting_account_id',
        'type',
        'name',
        'account_number',
        'clabe',
        'currency_code',
        'opening_balance',
        'current_balance',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:6',
        'current_balance' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function accountingAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(TreasuryMovement::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }


}
