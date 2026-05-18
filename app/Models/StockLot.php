<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockLot extends Model
{
    protected $fillable = [
        'company_id',
        'product_id',
        'product_variant_id',
        'lot_number',
        'expiration_date',
        'supplier_contact_id',
        'purchase_order_id',
        'purchase_receipt_id',
        'status',
        'metadata',
        'import_document_reference',
        'imported_color',
        'imported_model',
        'customs_office',
        'customs_entry_date',
        'customs_entry_number',
        'motor_number',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'metadata' => 'array',
            'customs_entry_date' => 'date',
];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_variant_id');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(StockSerialNumber::class, 'lot_id');
    }

    public function getDisplayNameAttribute(): string
    {
        $number = trim((string) $this->lot_number);

        if ($number === '') {
            return 'Lote #' . $this->id;
        }

        if ($this->expiration_date) {
            return $number . ' - Cad. ' . $this->expiration_date->format('d/m/Y');
        }

        return $number;
    }
}
