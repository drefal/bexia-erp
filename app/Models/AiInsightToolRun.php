<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiInsightToolRun extends Model
{
    protected $fillable = [
        'conversation_id',
        'message_id',
        'company_id',
        'user_id',
        'tool_name',
        'allowed_company_ids',
        'input',
        'output_summary',
        'status',
        'error_message',
        'duration_ms',
    ];

    protected $casts = [
        'allowed_company_ids' => 'array',
        'input' => 'array',
        'output_summary' => 'array',
    ];
}
