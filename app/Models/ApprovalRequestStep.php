<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequestStep extends Model
{
    protected $fillable = [
        'approval_request_id',
        'approval_workflow_step_id',
        'step_order',
        'step_name',
        'approver_type',
        'approver_user_id',
        'approver_role_name',
        'status',
        'acted_by_user_id',
        'acted_by_name',
        'acted_at',
        'comments',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }
}
