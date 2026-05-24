<?php

namespace App\Support\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryCostingMethodResolver
{
    public const FALLBACK_METHOD = 'average';

    public const METHOD_INHERIT = 'inherit';
    public const METHOD_AVERAGE = 'average';
    public const METHOD_FIFO = 'fifo';
    public const METHOD_STANDARD = 'standard';

    public function resolveMethod(?int $companyId = null, ?int $productId = null, ?int $productVariantId = null): string
    {
        return $this->resolve($companyId, $productId, $productVariantId)['method'];
    }

    public function resolve(?int $companyId = null, ?int $productId = null, ?int $productVariantId = null): array
    {
        $trace = [];

        $variant = null;
        $product = null;

        if ($productVariantId && Schema::hasTable('products')) {
            $variant = DB::table('products')->where('id', $productVariantId)->first();
            $trace[] = [
                'level' => 'variant',
                'id' => $productVariantId,
                'found' => (bool) $variant,
                'raw_method' => $variant->costing_method ?? null,
            ];

            $method = $this->usableMethod($variant->costing_method ?? null);
            if ($method) {
                return $this->result($method, 'product_variant', $productVariantId, $trace);
            }

            if (! $productId && ! empty($variant->parent_product_id)) {
                $productId = (int) $variant->parent_product_id;
            }
        }

        if ($productId && Schema::hasTable('products')) {
            $product = DB::table('products')->where('id', $productId)->first();
            $trace[] = [
                'level' => 'product',
                'id' => $productId,
                'found' => (bool) $product,
                'raw_method' => $product->costing_method ?? null,
            ];

            $method = $this->usableMethod($product->costing_method ?? null);
            if ($method) {
                return $this->result($method, 'product', $productId, $trace);
            }
        }

        $categoryId = null;

        if ($variant && ! empty($variant->product_category_id)) {
            $categoryId = (int) $variant->product_category_id;
        }

        if (! $categoryId && $product && ! empty($product->product_category_id)) {
            $categoryId = (int) $product->product_category_id;
        }

        $categoryResult = $this->resolveCategoryMethod($categoryId, $trace);
        if ($categoryResult) {
            return $categoryResult;
        }

        $companyResult = $this->resolveCompanyDefault($companyId, $trace);
        if ($companyResult) {
            return $companyResult;
        }

        $trace[] = [
            'level' => 'system',
            'raw_method' => self::FALLBACK_METHOD,
        ];

        return $this->result(self::FALLBACK_METHOD, 'system', null, $trace);
    }

    public function allowedMethods(bool $includeInherit = false): array
    {
        $methods = [
            self::METHOD_AVERAGE,
            self::METHOD_FIFO,
            self::METHOD_STANDARD,
        ];

        if ($includeInherit) {
            array_unshift($methods, self::METHOD_INHERIT);
        }

        return $methods;
    }

    public function labels(bool $includeInherit = true): array
    {
        $labels = [
            self::METHOD_AVERAGE => 'Promedio',
            self::METHOD_FIFO => 'FIFO',
            self::METHOD_STANDARD => 'Costo estándar',
        ];

        if ($includeInherit) {
            return [self::METHOD_INHERIT => 'Heredar'] + $labels;
        }

        return $labels;
    }

    protected function resolveCategoryMethod(?int $categoryId, array $trace): ?array
    {
        if (! $categoryId || ! Schema::hasTable('product_categories')) {
            return null;
        }

        $visited = [];

        while ($categoryId && ! in_array($categoryId, $visited, true)) {
            $visited[] = $categoryId;

            $category = DB::table('product_categories')->where('id', $categoryId)->first();

            $trace[] = [
                'level' => 'category',
                'id' => $categoryId,
                'found' => (bool) $category,
                'raw_method' => $category->costing_method ?? null,
            ];

            if (! $category) {
                return null;
            }

            $method = $this->usableMethod($category->costing_method ?? null);
            if ($method) {
                return $this->result($method, 'category', $categoryId, $trace);
            }

            $categoryId = ! empty($category->parent_id) ? (int) $category->parent_id : null;
        }

        return null;
    }

    protected function resolveCompanyDefault(?int $companyId, array $trace): ?array
    {
        if (! $companyId || ! Schema::hasTable('companies')) {
            return null;
        }

        $company = DB::table('companies')->where('id', $companyId)->first();

        $trace[] = [
            'level' => 'company',
            'id' => $companyId,
            'found' => (bool) $company,
            'raw_method' => $company->default_costing_method ?? null,
        ];

        if (! $company) {
            return null;
        }

        $method = $this->usableMethod($company->default_costing_method ?? null);

        if (! $method && ! empty($company->costing_method)) {
            $method = $this->usableMethod($company->costing_method);
        }

        if ($method) {
            return $this->result($method, 'company', $companyId, $trace);
        }

        return null;
    }

    protected function usableMethod(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '' || $value === self::METHOD_INHERIT) {
            return null;
        }

        return in_array($value, $this->allowedMethods(false), true)
            ? $value
            : null;
    }

    protected function result(string $method, string $source, ?int $sourceId, array $trace): array
    {
        return [
            'method' => $method,
            'source' => $source,
            'source_id' => $sourceId,
            'trace' => $trace,
        ];
    }
}
