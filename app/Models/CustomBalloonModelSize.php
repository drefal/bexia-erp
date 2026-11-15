<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomBalloonModelSize extends Model
{
    protected $table = 'custom_balloon_model_sizes';

    protected $fillable = [
        'company_id',
        'model_id',
        'size_id',
        'price_adjustment',
        'is_active',
    ];

    protected $casts = [
        'price_adjustment' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function balloonModel(): BelongsTo
    {
        return $this->belongsTo(
            CustomBalloonModel::class,
            'model_id'
        );
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(
            CustomBalloonSize::class,
            'size_id'
        );
    }
}
