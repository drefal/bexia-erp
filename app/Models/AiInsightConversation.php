<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiInsightConversation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'company_group_id',
        'user_id',
        'title',
        'allowed_company_ids',
        'status',
        'last_message_at',
    ];

    protected $casts = [
        'allowed_company_ids' => 'array',
        'last_message_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(AiInsightMessage::class, 'conversation_id');
    }
}
