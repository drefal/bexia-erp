<?php

namespace App\Models;

use App\Models\SaleOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SaleOrderLine extends Model
{
    protected $table = 'sales_order_lines';

    protected $fillable = [
        'sales_order_id',
        'company_id',
        'product_id',
        'product_variant_id',
        'product_label',
        'variant_label',
        'quantity',
        'delivered_quantity',
        'unit_price_without_tax',
        'margin_status',
        'gross_margin_percent',
        'gross_margin_amount',
        'estimated_unit_cost_without_tax',
        'tax_rate',
        'unit_price_with_tax',
        'line_total_without_tax',
        'line_tax',
        'line_total_with_tax',
        'delivery_status',
        'notes',
    ];

    protected $casts = [
        'gross_margin_percent' => 'decimal:4',
        'gross_margin_amount' => 'decimal:6',
        'estimated_unit_cost_without_tax' => 'decimal:6',
        'quantity' => 'decimal:6',
        'delivered_quantity' => 'decimal:6',
        'unit_price_without_tax' => 'decimal:6',
        'tax_rate' => 'decimal:4',
        'unit_price_with_tax' => 'decimal:6',
        'line_total_without_tax' => 'decimal:6',
        'line_tax' => 'decimal:6',
        'line_total_with_tax' => 'decimal:6',
    ];

    protected static function booted(): void
    {
        static::saving(function (SaleOrderLine $line): void {
            $qty = (float) ($line->quantity ?? 0);
            $price = (float) ($line->unit_price_without_tax ?? 0);
            $taxRate = (float) ($line->tax_rate ?? 0);

            $subtotal = round($qty * $price, 6);
            $tax = round($subtotal * ($taxRate / 100), 6);
            $total = round($subtotal + $tax, 6);

            $line->unit_price_with_tax = round($price * (1 + ($taxRate / 100)), 6);
            $line->line_total_without_tax = $subtotal;
            $line->line_tax = $tax;
            $line->line_total_with_tax = $total;

            $requested = (float) ($line->quantity ?? 0);
            $delivered = (float) ($line->delivered_quantity ?? 0);

            if ($requested > 0 && $delivered >= $requested) {
                $line->delivery_status = 'delivered';
            } elseif ($delivered > 0) {
                $line->delivery_status = 'partial';
            } else {
                $line->delivery_status = 'pending';
            }

            $unitCost = static::productCostWithoutTax(
                (int) ($line->product_id ?? 0),
                (int) ($line->product_variant_id ?? 0)
            );

            $unitMargin = round($price - $unitCost, 6);
            $marginAmount = round($unitMargin * $qty, 6);
            $marginPercent = $price > 0 ? round(($unitMargin / $price) * 100, 4) : 0;

            $marginStatus = 'no_cost';

            if ($unitCost > 0) {
                if ($price <= $unitCost) {
                    $marginStatus = 'danger';
                } elseif ($marginPercent < 15) {
                    $marginStatus = 'warning';
                } else {
                    $marginStatus = 'success';
                }
            }

            $line->estimated_unit_cost_without_tax = $unitCost;
            $line->gross_margin_amount = $marginAmount;
            $line->gross_margin_percent = $marginPercent;
            $line->margin_status = $marginStatus;

        });

        static::saved(function (SaleOrderLine $line): void {
            $line->saleOrder?->recalculateTotals();
        });

        static::deleted(function (SaleOrderLine $line): void {
            $line->saleOrder?->recalculateTotals();
        });
    }


    protected static function productCostWithoutTax(int $productId, int $variantId = 0): float
    {
        if (! Schema::hasTable('products')) {
            return 0.0;
        }

        $product = $productId > 0
            ? DB::table('products')->where('id', $productId)->first()
            : null;

        $variant = $variantId > 0
            ? DB::table('products')->where('id', $variantId)->first()
            : null;

        $source = $variant ?: $product;

        if (! $source) {
            return 0.0;
        }

        foreach ([
            'average_cost_without_tax',
            'standard_cost',
            'purchase_price',
            'last_purchase_cost',
        ] as $column) {
            if (property_exists($source, $column) && $source->{$column} !== null && (float) $source->{$column} > 0) {
                return (float) $source->{$column};
            }
        }

        if ($variant && $product) {
            foreach ([
                'average_cost_without_tax',
                'standard_cost',
                'purchase_price',
                'last_purchase_cost',
            ] as $column) {
                if (property_exists($product, $column) && $product->{$column} !== null && (float) $product->{$column} > 0) {
                    return (float) $product->{$column};
                }
            }
        }

        return 0.0;
    }

    public function saleOrder(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class, 'sales_order_id');
    }
}
