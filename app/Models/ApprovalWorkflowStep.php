<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflowStep extends Model
{
    protected $fillable = [
        'approval_workflow_id',
        'sort_order',
        'name',
        'is_active',
        'approver_type',
        'approver_user_id',
        'approver_role_name',
        'require_all',
        'amount_min',
        'amount_max',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'require_all' => 'boolean',
        'amount_min' => 'decimal:4',
        'amount_max' => 'decimal:4',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }
}
