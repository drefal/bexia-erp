<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PettyCashFund extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'treasury_account_id',
        'funding_treasury_account_id',
        'number',
        'name',
        'authorized_amount',
        'operational_balance',
        'currency_code',
        'status',
        'is_active',
        'assigned_at',
        'closed_at',
        'notes',
        'created_by_user_id',
        'closed_by_user_id',
    ];

    protected $casts = [
        'authorized_amount' => 'decimal:6',
        'operational_balance' => 'decimal:6',
        'is_active' => 'boolean',
        'assigned_at' => 'date',
        'closed_at' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function treasuryAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class);
    }

    public function fundingTreasuryAccount(): BelongsTo
    {
        return $this->belongsTo(
            TreasuryAccount::class,
            'funding_treasury_account_id'
        );
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ExpenseReport::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(PettyCashFundMovement::class);
    }
}
