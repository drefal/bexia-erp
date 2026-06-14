<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiInsightAuditLog extends Model
{
    protected $fillable = [
        'conversation_id',
        'message_id',
        'company_id',
        'company_group_id',
        'user_id',
        'event',
        'allowed_company_ids',
        'payload',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'allowed_company_ids' => 'array',
        'payload' => 'array',
    ];
}
