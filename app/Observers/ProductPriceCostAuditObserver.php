<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductPriceCostAudit;
use Illuminate\Support\Facades\Schema;

class ProductPriceCostAuditObserver
{
    public function updated(Product $product): void
    {
        if (! Schema::hasTable('product_price_cost_audits')) {
            return;
        }

        foreach (static::auditedFields() as $field => $label) {
            if (! $product->wasChanged($field)) {
                continue;
            }

            $oldValue = $product->getOriginal($field);
            $newValue = $product->{$field};

            if (static::normalize($oldValue) === static::normalize($newValue)) {
                continue;
            }

            ProductPriceCostAudit::create([
                'company_id' => static::companyId($product),
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'field_name' => $field,
                'field_label' => $label,
                'old_value' => static::stringValue($oldValue),
                'new_value' => static::stringValue($newValue),
                'old_numeric_value' => is_numeric($oldValue) ? (float) $oldValue : null,
                'new_numeric_value' => is_numeric($newValue) ? (float) $newValue : null,
                'source' => app()->runningInConsole() ? 'sistema' : 'manual',
                'product_reference' => static::productReference($product),
                'product_name' => (string) ($product->name ?? ''),
                'changed_at' => now(),
            ]);
        }
    }

    public static function auditedFields(): array
    {
        return [
            'sale_price' => 'Precio de venta sin IVA',
            'sale_tax_rate' => 'IVA venta %',

            'average_cost_without_tax' => 'Costo promedio actual sin IVA',
            'purchase_tax_rate' => 'IVA compra %',

            'purchase_price' => 'Precio de compra',
            'standard_cost' => 'Costo estándar',

            'purchase_pack_units' => 'UXES / unidades por empaque',
            'purchase_min_quantity' => 'Compra mínima',
            'purchase_multiple_quantity' => 'Múltiplo de compra',

            'purchase_lead_time_days' => 'Plazo compra / entrega',
            'purchase_delivery_days' => 'Plazo compra / entrega',
            'purchase_delay' => 'Plazo compra / entrega',
        ];
    }

    protected static function normalize($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 6, '.', '');
        }

        return trim((string) $value);
    }

    protected static function stringValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    protected static function companyId(Product $product): ?int
    {
        if (isset($product->company_id) && $product->company_id) {
            return (int) $product->company_id;
        }

        $user = auth()->user();

        if ($user && isset($user->company_id)) {
            return (int) $user->company_id;
        }

        return null;
    }

    protected static function productReference(Product $product): ?string
    {
        foreach (['internal_reference', 'sku', 'barcode', 'code'] as $field) {
            if (isset($product->{$field}) && trim((string) $product->{$field}) !== '') {
                return (string) $product->{$field};
            }
        }

        return null;
    }
}
