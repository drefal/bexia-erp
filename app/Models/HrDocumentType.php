<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrDocumentType extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'requires_expiration_date',
        'is_required_by_default',
        'is_active',
    ];

    protected $casts = [
        'requires_expiration_date' => 'boolean',
        'is_required_by_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
