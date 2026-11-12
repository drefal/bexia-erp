<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceAccountSetting extends Model
{
    protected $fillable = [
        'commerce_account_id',
        'setting_key',
        'setting_value',
    ];

    protected $casts = [
        'setting_value' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            CommerceAccount::class,
            'commerce_account_id'
        );
    }
}
