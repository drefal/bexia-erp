<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAdvanceSetting extends Model
{
    public const RULE_OFF = 'off';
    public const RULE_WARN = 'warn';
    public const RULE_BLOCK = 'block';

    protected $fillable = [
        'company_id',
        'pending_rule',
        'due_days',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'due_days' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function defaults(): array
    {
        return [
            'pending_rule' => self::RULE_WARN,
            'due_days' => 5,
            'is_active' => true,
        ];
    }
}
