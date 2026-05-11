<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductVariantSearch
{
    public static function options(string $search, int $companyId, bool $saleOnly = true, int $limit = 80): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $search = trim($search);

        $rows = self::baseQuery($companyId, $saleOnly)
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . mb_strtolower($search) . '%';

                $query->where(function ($q) use ($like) {
                    foreach ([
                        'internal_reference',
                        'sku',
                        'barcode',
                        'name',
                        'variant_name',
                        'variant_value',
                        'model',
                        'brand',
                        'color',
                        'product_line',
                    ] as $column) {
                        if (Schema::hasColumn('products', $column)) {
                            $q->orWhereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", [$like]);
                        }
                    }
                });
            })
            ->orderByRaw("COALESCE(internal_reference, sku, barcode, '')")
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $options = [];

        foreach ($rows as $row) {
            $isVariant = (bool) ($row->is_variant ?? false);

            if ($isVariant) {
                $parentId = (int) ($row->parent_product_id ?: 0);
                $variantId = (int) $row->id;

                if ($parentId <= 0) {
                    $parentId = $variantId;
                    $variantId = 0;
                }

                $options[$parentId . '|' . $variantId] = self::label($parentId, $variantId);
                continue;
            }

            $children = self::variantRowsForParent((int) $row->id, $companyId, $saleOnly);

            if ($children->isNotEmpty()) {
                foreach ($children as $variant) {
                    $options[(int) $row->id . '|' . (int) $variant->id] = self::label((int) $row->id, (int) $variant->id);
                }
            } else {
                $options[(int) $row->id . '|0'] = self::label((int) $row->id, 0);
            }
        }

        return $options;
    }

    protected static function baseQuery(int $companyId, bool $saleOnly)
    {
        $query = DB::table('products');

        if (Schema::hasColumn('products', 'company_id') && $companyId > 0) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            });
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        if ($saleOnly && Schema::hasColumn('products', 'can_be_sold')) {
            $query->where('can_be_sold', true);
        }

        return $query;
    }

    protected static function variantRowsForParent(int $parentId, int $companyId, bool $saleOnly)
    {
        if ($parentId <= 0) {
            return collect();
        }

        return self::baseQuery($companyId, $saleOnly)
            ->where('parent_product_id', $parentId)
            ->where('is_variant', true)
            ->orderByRaw("COALESCE(variant_group, '')")
            ->orderByRaw("COALESCE(variant_value, variant_name, name, '')")
            ->limit(80)
            ->get();
    }

    public static function labelFromKey(string $key, int $companyId = 0): ?string
    {
        if (trim($key) === '') {
            return null;
        }

        [$productId, $variantId] = array_pad(explode('|', $key), 2, 0);

        return self::label((int) $productId, (int) $variantId);
    }

    public static function infoFromKey(string $key, int $companyId = 0): array
    {
        [$productId, $variantId] = array_pad(explode('|', $key), 2, 0);

        $productId = (int) $productId;
        $variantId = (int) $variantId;

        $product = $productId > 0
            ? DB::table('products')->where('id', $productId)->first()
            : null;

        $variant = $variantId > 0
            ? DB::table('products')->where('id', $variantId)->first()
            : null;

        if (! $product && $variant && ! empty($variant->parent_product_id)) {
            $productId = (int) $variant->parent_product_id;
            $product = DB::table('products')->where('id', $productId)->first();
        }

        $salePrice = 0.0;
        $saleTaxRate = 16.0;

        if ($product) {
            $salePrice = (float) ($product->sale_price ?? 0);
            $saleTaxRate = (float) ($product->sale_tax_rate ?? 16);
        }

        if ($variant) {
            if (property_exists($variant, 'sale_price') && $variant->sale_price !== null) {
                $salePrice = (float) $variant->sale_price;
            }

            if (property_exists($variant, 'sale_tax_rate') && $variant->sale_tax_rate !== null) {
                $saleTaxRate = (float) $variant->sale_tax_rate;
            }
        }

        return [
            'product_id' => $productId ?: null,
            'product_variant_id' => $variantId ?: null,
            'product_label' => $product ? self::baseLabel($product) : null,
            'variant_label' => $variant ? self::variantLabel($variant) : null,
            'sale_price' => $salePrice,
            'sale_tax_rate' => $saleTaxRate,
        ];
    }

    public static function label(int $productId, int $variantId = 0): string
    {
        $product = $productId > 0
            ? DB::table('products')->where('id', $productId)->first()
            : null;

        $variant = $variantId > 0
            ? DB::table('products')->where('id', $variantId)->first()
            : null;

        if (! $product && $variant && ! empty($variant->parent_product_id)) {
            $product = DB::table('products')->where('id', (int) $variant->parent_product_id)->first();
            $productId = (int) $variant->parent_product_id;
        }

        if (! $product && $variant) {
            return self::baseLabel($variant);
        }

        if (! $product) {
            return 'Producto #' . $productId;
        }

        $label = self::baseLabel($product);

        if ($variant) {
            $variantText = self::variantLabel($variant);

            if ($variantText !== '—') {
                $label .= ' / ' . $variantText;
            }
        }

        return $label;
    }

    protected static function baseLabel(object $product): string
    {
        $ref = '';

        foreach (['internal_reference', 'sku', 'barcode'] as $column) {
            if (property_exists($product, $column) && trim((string) $product->{$column}) !== '') {
                $ref = trim((string) $product->{$column});
                break;
            }
        }

        $name = trim((string) ($product->name ?? ('Producto #' . $product->id)));

        return trim(($ref !== '' ? $ref . ' - ' : '') . $name);
    }

    protected static function variantLabel(object $variant): string
    {
        $group = trim((string) ($variant->variant_group ?? ''));
        $value = '';

        foreach (['variant_value', 'variant_name', 'color'] as $column) {
            if (property_exists($variant, $column) && trim((string) $variant->{$column}) !== '') {
                $value = trim((string) $variant->{$column});
                break;
            }
        }

        if ($value === '') {
            $value = trim((string) ($variant->name ?? ''));
        }

        if ($value === '') {
            return '—';
        }

        return $group !== '' ? $group . ': ' . $value : $value;
    }
}
