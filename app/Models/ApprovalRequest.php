<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRequest extends Model
{
    protected $fillable = [
        'company_id',
        'approval_workflow_id',
        'approvable_type',
        'approvable_id',
        'document_type',
        'document_number',
        'requester_user_id',
        'requester_name',
        'status',
        'current_step_order',
        'amount_total',
        'sent_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'amount_total' => 'decimal:4',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalRequestStep::class);
    }
}
