<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    protected $table = 'purchase_order_lines';

    protected $fillable = [
        'purchase_order_id',
        'company_id',
        'product_id',
        'product_variant_id',
        'product_label',
        'variant_label',
        'purchase_unit_type',
        'purchase_unit_label',
        'purchase_unit_factor',
        'sat_unit_key',
        'sat_unit_name',
        'ordered_quantity',
        'base_quantity',
        'received_quantity',
        'received_base_quantity',
        'unit_cost_without_tax',
        'tax_rate',
        'unit_cost_with_tax',
        'line_total_without_tax',
        'line_tax',
        'line_total_with_tax',
        'notes',
    ];

    protected $casts = [
        'purchase_unit_factor' => 'decimal:6',
        'ordered_quantity' => 'decimal:6',
        'base_quantity' => 'decimal:6',
        'received_quantity' => 'decimal:6',
        'received_base_quantity' => 'decimal:6',
        'unit_cost_without_tax' => 'decimal:6',
        'tax_rate' => 'decimal:4',
        'unit_cost_with_tax' => 'decimal:6',
        'line_total_without_tax' => 'decimal:6',
        'line_tax' => 'decimal:6',
        'line_total_with_tax' => 'decimal:6',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
    }
}
