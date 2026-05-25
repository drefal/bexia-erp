<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPeriodicity extends Model
{
    protected $table = 'payroll_periodicities';

    protected $fillable = [
        'company_id',
        'name',
        'sat_code',
        'days',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
