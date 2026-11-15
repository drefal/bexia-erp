<?php

namespace App\Support\CustomBalloons;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CatalogProductResolver
{
    public const COMPANY_ID = 3;

    public static function resolve(
        int $productId,
        ?int $variantId = null,
        int $companyId = self::COMPANY_ID
    ): array {
        if (
            $companyId !== self::COMPANY_ID ||
            $productId <= 0 ||
            ($variantId !== null && $variantId <= 0)
        ) {
            self::reject();
        }

        $product = DB::table('products')
            ->where('id', $productId)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('can_be_sold', true)
            ->first();

        if (
            ! $product ||
            (bool) ($product->is_variant ?? false) ||
            ! empty($product->parent_product_id)
        ) {
            self::reject();
        }

        $hasVariants = DB::table('products')
            ->where('company_id', $companyId)
            ->where('parent_product_id', $productId)
            ->where('is_variant', true)
            ->exists();

        $variant = null;

        if ($variantId !== null) {
            $variant = DB::table('products')
                ->where('id', $variantId)
                ->where('company_id', $companyId)
                ->where('parent_product_id', $productId)
                ->where('is_variant', true)
                ->where('is_active', true)
                ->where('can_be_sold', true)
                ->first();

            if (! $variant) {
                self::reject();
            }
        } elseif ($hasVariants) {
            self::reject();
        }

        $source = $variant ?: $product;

        $price = $source->sale_price;

        if ($price === null && $variant) {
            $price = $product->sale_price;
        }

        $taxRate = $source->sale_tax_rate;

        if ($taxRate === null) {
            $taxRate = $product->sale_tax_rate;
        }

        if ($price === null || $taxRate === null) {
            self::reject();
        }

        $price = (float) $price;
        $taxRate = (float) $taxRate;

        if (
            ! is_finite($price) ||
            ! is_finite($taxRate) ||
            $price < 0 ||
            $taxRate < 0 ||
            $taxRate > 100
        ) {
            self::reject();
        }

        return [
            'company_id' => $companyId,
            'product_id' => (int) $product->id,
            'product_variant_id' => $variant
                ? (int) $variant->id
                : null,
            'product_name' => (string) $product->name,
            'variant_name' => $variant
                ? (string) $variant->name
                : null,
            'product_type' => (string) $source->product_type,
            'sale_price_without_tax' => $price,
            'sale_tax_rate' => $taxRate,
            'sale_price_with_tax' => round(
                $price * (1 + $taxRate / 100),
                2
            ),
        ];
    }

    private static function reject(): never
    {
        throw ValidationException::withMessages([
            'product_variant_id' =>
                'Producto o variante no disponible para esta empresa.',
        ]);
    }
}
