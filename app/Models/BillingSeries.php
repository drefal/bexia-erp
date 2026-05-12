<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingSeries extends Model
{
    protected $table = 'billing_series';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'is_default' => 'boolean',
        'last_assigned_at' => 'datetime',
        'year' => 'integer',
        'next_number' => 'integer',
        'last_number' => 'integer',
        'padding' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function previewNextNumber(): string
    {
        $folio = str_pad((string) ((int) $this->next_number), (int) ($this->padding ?: 1), '0', STR_PAD_LEFT);

        return trim((string) $this->series) . '/' . $folio;
    }
}
