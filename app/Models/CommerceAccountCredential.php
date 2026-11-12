<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceAccountCredential extends Model
{
    protected $fillable = [
        'commerce_account_id',
        'field_key',
        'expires_at',
    ];

    protected $hidden = [
        'secret_value',
    ];

    protected $casts = [
        'secret_value' => 'encrypted',
        'expires_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            CommerceAccount::class,
            'commerce_account_id'
        );
    }
}
