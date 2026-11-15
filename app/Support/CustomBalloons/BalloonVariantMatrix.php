<?php

namespace App\Support\CustomBalloons;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BalloonVariantMatrix
{
    public static function build(
        array $variants,
        array $relations,
        int $companyId = 3
    ): array {
        $attributes = [];

        foreach ($relations as $relation) {
            if ((int) $relation['company_id'] !== $companyId) {
                throw new RuntimeException(
                    'Relación perteneciente a otra empresa.'
                );
            }

            $id = (int) $relation['product_id'];
            $code = strtoupper((string) $relation['attribute_code']);
            $value = (string) $relation['value_name'];

            if (! in_array($code, ['TAMANO', 'INFLADO'], true)) {
                continue;
            }

            if (isset($attributes[$id][$code])) {
                throw new RuntimeException(
                    'Atributo duplicado para variante ' . $id
                );
            }

            $attributes[$id][$code] = $value;
        }

        $result = [];
        $keys = [];

        foreach ($variants as $variant) {
            if ((int) $variant['company_id'] !== $companyId) {
                throw new RuntimeException(
                    'Variante perteneciente a otra empresa.'
                );
            }

            if (
                ! $variant['is_active']
                || ! $variant['can_be_sold']
            ) {
                continue;
            }

            $id = (int) $variant['id'];
            $size = $attributes[$id]['TAMANO'] ?? null;
            $inflation = $attributes[$id]['INFLADO'] ?? null;

            if (! $size || ! $inflation) {
                continue;
            }

            $key = mb_strtolower($size)
                . '|'
                . mb_strtolower($inflation);

            if (isset($keys[$key])) {
                throw new RuntimeException(
                    'Combinación duplicada: ' . $key
                );
            }

            $keys[$key] = true;

            $result[] = [
                'variant_id' => $id,
                'reference' => $variant['internal_reference'],
                'size' => $size,
                'inflation' => $inflation,
                'sale_price' => $variant['sale_price'],
                'sale_tax_rate' => $variant['sale_tax_rate'],
            ];
        }

        return $result;
    }

    public static function forProduct(
        int $productId,
        int $companyId = 3
    ): array {
        if ($companyId !== 3 || $productId <= 0) {
            throw new RuntimeException(
                'Empresa o producto no autorizado.'
            );
        }

        $parent = DB::table('products')
            ->where('id', $productId)
            ->where('company_id', $companyId)
            ->where('is_variant', false)
            ->where('is_active', true)
            ->where('can_be_sold', true)
            ->where('available_in_custom_balloons', true)
            ->whereIn('product_category_id', function ($query) use ($companyId) {
                $query->select('id')
                    ->from('product_categories')
                    ->where('company_id', $companyId)
                    ->where('code', 'GLB-PERS')
                    ->where('is_active', true);
            })
            ->first();

        if (! $parent) {
            throw new RuntimeException(
                'Producto no habilitado para Globos.'
            );
        }

        $variants = DB::table('products')
            ->where('company_id', $companyId)
            ->where('parent_product_id', $productId)
            ->where('is_variant', true)
            ->get([
                'id',
                'company_id',
                'internal_reference',
                'is_active',
                'can_be_sold',
                'sale_price',
                'sale_tax_rate',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();

        if (! $variants) {
            return [];
        }

        $ids = array_column($variants, 'id');

        $relations = DB::table('product_variant_attribute_values as r')
            ->join(
                'product_attributes as a',
                function ($join) use ($companyId) {
                    $join->on(
                        'a.id',
                        '=',
                        'r.product_attribute_id'
                    )->where('a.company_id', '=', $companyId);
                }
            )
            ->join(
                'product_attribute_values as v',
                function ($join) use ($companyId) {
                    $join->on(
                        'v.id',
                        '=',
                        'r.product_attribute_value_id'
                    )->where('v.company_id', '=', $companyId);
                }
            )
            ->where('r.company_id', $companyId)
            ->whereIn('r.product_id', $ids)
            ->where('a.is_active', true)
            ->where('v.is_active', true)
            ->whereColumn(
                'v.product_attribute_id',
                'r.product_attribute_id'
            )
            ->whereIn('a.code', ['TAMANO', 'INFLADO'])
            ->get([
                'r.product_id',
                'r.company_id',
                'a.code as attribute_code',
                'v.name as value_name',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();

        return self::build($variants, $relations, $companyId);
    }
}
