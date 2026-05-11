<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestLine extends Model
{
    protected $fillable = [
        'purchase_request_id',
        'company_id',
        'warehouse_id',
        'location_id',
        'product_id',
        'product_variant_id',
        'product_label',
        'variant_label',
        'warehouse_label',
        'location_label',
        'available_quantity',
        'suggested_quantity',
        'requested_quantity',
        'pending_quantity',
        'unit_cost_without_tax',
        'tax_rate',
        'unit_cost_with_tax',
        'line_total_without_tax',
        'line_tax',
        'line_total_with_tax',
        'priority',
        'priority_label',
        'cost_source',
        'source_data',
    ];

    protected $casts = [
        'available_quantity' => 'decimal:6',
        'suggested_quantity' => 'decimal:6',
        'requested_quantity' => 'decimal:6',
        'pending_quantity' => 'decimal:6',
        'unit_cost_without_tax' => 'decimal:6',
        'tax_rate' => 'decimal:4',
        'unit_cost_with_tax' => 'decimal:6',
        'line_total_without_tax' => 'decimal:4',
        'line_tax' => 'decimal:4',
        'line_total_with_tax' => 'decimal:4',
        'source_data' => 'array',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    protected static function booted(): void
    {
        static::saving(function (PurchaseRequestLine $line): void {
            $quantity = (float) ($line->requested_quantity ?? 0);
            $unitWithoutTax = (float) ($line->unit_cost_without_tax ?? 0);
            $taxRate = (float) ($line->tax_rate ?? 0);

            $unitWithTax = $unitWithoutTax * (1 + ($taxRate / 100));
            $lineWithoutTax = $quantity * $unitWithoutTax;
            $lineWithTax = $quantity * $unitWithTax;

            $line->available_quantity = $line->available_quantity ?? 0;
            $line->suggested_quantity = $line->suggested_quantity ?? 0;
            $line->pending_quantity = $line->pending_quantity ?? 0;

            $line->unit_cost_with_tax = $line->unit_cost_with_tax ?? $unitWithTax;
            $line->line_total_without_tax = $line->line_total_without_tax ?? $lineWithoutTax;
            $line->line_tax = $line->line_tax ?? max(0, $lineWithTax - $lineWithoutTax);
            $line->line_total_with_tax = $line->line_total_with_tax ?? $lineWithTax;

            $line->variant_label = $line->variant_label ?: '—';
            $line->priority_label = $line->priority_label ?: 'Normal';
            $line->cost_source = $line->cost_source ?: 'Manual';
        });
    }

    public function normalizeLineAmountsForPurchaseRequest(): void
    {
        // Marker method para identificar que este fix ya fue aplicado.
    }


}
