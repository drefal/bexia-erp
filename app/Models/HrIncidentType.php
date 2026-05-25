<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrIncidentType extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'effect',
        'requires_approval',
        'affects_payroll',
        'is_active',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'affects_payroll' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
