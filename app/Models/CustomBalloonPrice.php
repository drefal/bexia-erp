<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomBalloonPrice extends Model
{
    protected $fillable = [
        'company_id',
        'size_id',
        'price',
        'valid_from',
        'valid_to',
        'created_by_user_id',
        'notes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(CustomBalloonSize::class, 'size_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
