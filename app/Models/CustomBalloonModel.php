<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomBalloonModel extends Model
{
    protected $table = 'custom_balloon_models';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'image_path',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function modelSizes(): HasMany
    {
        return $this->hasMany(
            CustomBalloonModelSize::class,
            'model_id'
        );
    }
}
