<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingInventoryValuationLayer extends Model
{
    protected $fillable = [
        'company_id',
        'product_id',
        'accounting_entry_id',
        'operation_type',
        'direction',
        'movement_date',
        'source_type',
        'source_id',
        'source_line_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'remaining_quantity',
        'currency',
        'label',
        'metadata',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'quantity' => 'decimal:6',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
        'remaining_quantity' => 'decimal:6',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
