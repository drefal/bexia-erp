<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflow extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'document_type',
        'is_active',
        'priority',
        'amount_min',
        'amount_max',
        'applies_to_user_id',
        'applies_to_role_name',
        'applies_to_warehouse_id',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'amount_min' => 'decimal:4',
        'amount_max' => 'decimal:4',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class)->orderBy('sort_order');
    }
}
