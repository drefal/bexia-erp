<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseAdvance extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PENDING_RECONCILIATION = 'pending_reconciliation';
    public const STATUS_PENDING_RETURN = 'pending_return';
    public const STATUS_PENDING_REIMBURSEMENT = 'pending_reimbursement';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'petty_cash_fund_id',
        'employee_id',
        'authorized_by_employee_id',
        'number',
        'status',
        'request_date',
        'due_date',
        'due_days',
        'amount',
        'reconciled_amount',
        'return_due_amount',
        'reimbursement_due_amount',
        'reimbursement_transfer_request_id',
        'reimbursement_approval_request_id',
        'reimbursement_treasury_movement_id',
        'reimbursement_petty_cash_movement_id',
        'reimbursed_at',
        'reimbursed_by_user_id',
        'return_treasury_movement_id',
        'return_petty_cash_fund_movement_id',
        'returned_at',
        'returned_by_user_id',
        'currency_code',
        'purpose',
        'notes',
        'treasury_cash_transfer_request_id',
        'treasury_movement_id',
        'petty_cash_fund_movement_id',
        'approval_request_id',
        'approval_status',
        'created_by_user_id',
        'submitted_by_user_id',
        'approved_by_user_id',
        'delivered_by_user_id',
        'submitted_at',
        'approved_at',
        'delivered_at',
        'closed_at',
        'reconciliation_finalized_at',
        'reconciliation_finalized_by_user_id',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'request_date' => 'date',
        'due_date' => 'date',
        'due_days' => 'integer',
        'amount' => 'decimal:6',
        'reconciled_amount' => 'decimal:6',
        'return_due_amount' => 'decimal:6',
        'reimbursement_due_amount' => 'decimal:6',
        'reimbursed_at' => 'datetime',
        'returned_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'delivered_at' => 'datetime',
        'closed_at' => 'datetime',
        'reconciliation_finalized_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function pettyCashFund(): BelongsTo
    {
        return $this->belongsTo(PettyCashFund::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function authorizedByEmployee(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'authorized_by_employee_id'
        );
    }

    public function treasuryCashTransferRequest(): BelongsTo
    {
        return $this->belongsTo(
            TreasuryCashTransferRequest::class
        );
    }

    public function treasuryMovement(): BelongsTo
    {
        return $this->belongsTo(TreasuryMovement::class);
    }

    public function pettyCashFundMovement(): BelongsTo
    {
        return $this->belongsTo(PettyCashFundMovement::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function expenseReports(): HasMany
    {
        return $this->hasMany(
            ExpenseReport::class,
            'expense_advance_id'
        );
    }

    public function approvedReconciledAmount(): float
    {
        return round(
            (float) $this->expenseReports()
                ->where('status', 'approved')
                ->sum('total_amount'),
            6
        );
    }

    public function pendingReconciledAmount(): float
    {
        return round(
            (float) $this->expenseReports()
                ->where('status', 'pending_approval')
                ->sum('total_amount'),
            6
        );
    }

    public function draftReconciledAmount(): float
    {
        return round(
            (float) $this->expenseReports()
                ->where('status', 'draft')
                ->sum('total_amount'),
            6
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'submitted_by_user_id'
        );
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by_user_id'
        );
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'delivered_by_user_id'
        );
    }

    public static function openStatuses(): array
    {
        return [
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_PENDING_RECONCILIATION,
            self::STATUS_PENDING_RETURN,
            self::STATUS_PENDING_REIMBURSEMENT,
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_PENDING_APPROVAL =>
                'Pendiente de aprobación',
            self::STATUS_APPROVED => 'Aprobado',
            self::STATUS_PENDING_RECONCILIATION =>
                'Pendiente de comprobar',
            self::STATUS_PENDING_RETURN =>
                'Pendiente de devolución',
            self::STATUS_PENDING_REIMBURSEMENT =>
                'Pendiente de reembolso',
            self::STATUS_CLOSED => 'Cerrado',
            self::STATUS_REJECTED => 'Rechazado',
            self::STATUS_CANCELLED => 'Cancelado',
            default => $status ?: '—',
        };
    }
}
