<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreasuryCashTransferRequest extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'pos_point_id',
        'pos_session_id',
        'pos_cash_movement_id',
        'source_treasury_account_id',
        'destination_treasury_account_id',
        'number',
        'type',
        'status',
        'amount',
        'currency_code',
        'reason',
        'notes',
        'rejection_reason',
        'requested_by_user_id',
        'approved_by_user_id',
        'rejected_by_user_id',
        'delivered_by_user_id',
        'received_by_user_id',
        'requested_at',
        'approved_at',
        'rejected_at',
        'delivered_at',
        'received_at',
        'cancelled_at',
        'posted_at',
        'outflow_treasury_movement_id',
        'inflow_treasury_movement_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:6',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'delivered_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'posted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(TreasuryCashTransferApprovalLog::class);
    }
}
