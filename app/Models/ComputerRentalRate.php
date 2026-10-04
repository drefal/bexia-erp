<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComputerRentalRate extends Model
{
    protected $table = 'computer_rental_rates';

    protected $guarded = [];

    protected $casts = [
        'hourly_rate' => 'decimal:4',
        'prepaid_price' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'cancellation_grace_minutes' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ComputerRentalSession::class, 'rate_id');
    }
}
