<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ExpenseReport extends Model
{
    public const TYPE_PETTY_CASH = 'petty_cash';
    public const TYPE_REIMBURSEMENT = 'reimbursement';

    protected $fillable = [
        'company_id',
        'employee_id',
        'petty_cash_fund_id',
        'expense_advance_id',
        'number',
        'type',
        'status',
        'report_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'currency_code',
        'purpose',
        'notes',
        'rejection_reason',
        'approval_request_id',
        'approval_status',
        'payment_treasury_account_id',
        'treasury_movement_id',
        'created_by_user_id',
        'submitted_by_user_id',
        'approved_by_user_id',
        'rejected_by_user_id',
        'paid_by_user_id',
        'submitted_at',
        'approval_requested_at',
        'approved_at',
        'rejected_at',
        'paid_at',
        'closed_at',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'report_date' => 'date',
        'subtotal' => 'decimal:6',
        'tax_amount' => 'decimal:6',
        'total_amount' => 'decimal:6',
        'submitted_at' => 'datetime',
        'approval_requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'paid_at' => 'datetime',
        'closed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function pettyCashFund(): BelongsTo
    {
        return $this->belongsTo(PettyCashFund::class);
    }

    public function expenseAdvance(): BelongsTo
    {
        return $this->belongsTo(
            ExpenseAdvance::class,
            'expense_advance_id'
        );
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function paymentTreasuryAccount(): BelongsTo
    {
        return $this->belongsTo(
            TreasuryAccount::class,
            'payment_treasury_account_id'
        );
    }

    public function treasuryMovement(): BelongsTo
    {
        return $this->belongsTo(TreasuryMovement::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseReportLine::class);
    }

    public function fundMovements(): HasMany
    {
        return $this->hasMany(PettyCashFundMovement::class);
    }

    public function recalculateTotals(): self
    {
        $subtotal = (float) $this->lines()->sum('subtotal');
        $tax = (float) $this->lines()->sum('tax_amount');
        $total = (float) $this->lines()->sum('total_amount');

        $this->forceFill([
            'subtotal' => round($subtotal, 6),
            'tax_amount' => round($tax, 6),
            'total_amount' => round($total, 6),
        ])->save();

        return $this->refresh();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
