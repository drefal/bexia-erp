<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiInsightMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'company_id',
        'user_id',
        'role',
        'content',
        'metadata',
        'tokens_prompt',
        'tokens_completion',
        'estimated_cost',
    ];

    protected $casts = [
        'metadata' => 'array',
        'estimated_cost' => 'decimal:6',
    ];

    public function conversation()
    {
        return $this->belongsTo(AiInsightConversation::class, 'conversation_id');
    }
}
