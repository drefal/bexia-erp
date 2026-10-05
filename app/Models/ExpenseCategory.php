<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'requires_receipt',
        'allows_without_receipt',
        'is_active',
    ];

    protected $casts = [
        'requires_receipt' => 'boolean',
        'allows_without_receipt' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseReportLine::class);
    }
}
