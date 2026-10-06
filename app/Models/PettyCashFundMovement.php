<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashFundMovement extends Model
{
    protected $fillable = [
        'company_id',
        'petty_cash_fund_id',
        'expense_report_id',
        'type',
        'funding_source_id',
        'movement_date',
        'amount',
        'currency_code',
        'balance_before',
        'balance_after',
        'reference',
        'description',
        'treasury_movement_id',
        'created_by_user_id',
        'metadata',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'amount' => 'decimal:6',
        'balance_before' => 'decimal:6',
        'balance_after' => 'decimal:6',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(
            PettyCashFund::class,
            'petty_cash_fund_id'
        );
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(
            FundingSource::class,
            'funding_source_id'
        );
    }

    public function expenseReport(): BelongsTo
    {
        return $this->belongsTo(ExpenseReport::class);
    }

    public function treasuryMovement(): BelongsTo
    {
        return $this->belongsTo(TreasuryMovement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
