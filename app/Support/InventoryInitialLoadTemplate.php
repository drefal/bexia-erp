<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryInitialLoadTemplate
{
    protected const CHUNK_SIZE = 500;

    public static function download(?int $companyId, int $warehouseId, int $locationId): StreamedResponse
    {
        $warehouseLabel = static::warehouseLabel($warehouseId);
        $locationLabel = static::locationLabel($locationId);

        $headers = [
            'Almacén',
            'Ubicación',
            'Referencia interna',
            'Producto',
            'Variante',
            'Código de barras',
            'Cantidad actual',
            'Cantidad contada',
            'Costo promedio',
            'Notas',
            'Producto ID',
            'Variante ID',
            'Almacén ID',
            'Ubicación ID',
        ];

        $fileName = static::safeFileName(
            'plantilla-inventario-' . $locationLabel . '-' . now()->format('Ymd-His')
        ) . '.csv';

        return response()->streamDownload(function () use (
            $companyId,
            $warehouseId,
            $locationId,
            $warehouseLabel,
            $locationLabel,
            $headers
        ): void {
            $out = fopen('php://output', 'w');

            if (! $out) {
                return;
            }

            // BOM para que Excel abra UTF-8 correctamente.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $headers, ';');

            static::streamRows(
                out: $out,
                companyId: $companyId,
                warehouseId: $warehouseId,
                locationId: $locationId,
                warehouseLabel: $warehouseLabel,
                locationLabel: $locationLabel,
            );

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'public',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected static function streamRows(
        $out,
        ?int $companyId,
        int $warehouseId,
        int $locationId,
        string $warehouseLabel,
        string $locationLabel
    ): void {
        if (! Schema::hasTable('products')) {
            return;
        }

        $query = DB::table('products')
            ->select(static::productSelectColumns())
            ->orderBy('products.id');

        if (Schema::hasColumn('products', 'company_id') && $companyId) {
            $query->where('products.company_id', $companyId);
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('products.is_active', true);
        }

        if (Schema::hasColumn('products', 'is_variant')) {
            $query->where(function ($query): void {
                $query
                    ->where('products.is_variant', false)
                    ->orWhereNull('products.is_variant');
            });
        } elseif (Schema::hasColumn('products', 'parent_product_id')) {
            $query->whereNull('products.parent_product_id');
        }

        $written = 0;

        $query->chunkById(self::CHUNK_SIZE, function ($products) use (
            $out,
            $companyId,
            $warehouseId,
            $locationId,
            $warehouseLabel,
            $locationLabel,
            &$written
        ): void {
            $productIds = $products->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

            if (empty($productIds)) {
                return;
            }

            $variantsByParent = static::variantsByParent($companyId, $productIds);

            $variantIds = [];

            foreach ($variantsByParent as $variants) {
                foreach ($variants as $variant) {
                    $variantIds[] = (int) $variant->id;
                }
            }

            $quantMap = static::quantMap(
                companyId: $companyId,
                warehouseId: $warehouseId,
                locationId: $locationId,
                productIds: $productIds,
            );

            foreach ($products as $product) {
                $variants = $variantsByParent[(int) $product->id] ?? [];

                if (! empty($variants)) {
                    foreach ($variants as $variant) {
                        $key = static::quantKey((int) $product->id, (int) $variant->id);
                        $quant = $quantMap[$key] ?? null;

                        static::writeRow(
                            out: $out,
                            companyId: $companyId,
                            warehouseId: $warehouseId,
                            locationId: $locationId,
                            warehouseLabel: $warehouseLabel,
                            locationLabel: $locationLabel,
                            product: $product,
                            variant: $variant,
                            quantity: $quant ? (float) $quant->quantity : 0,
                            averageCost: $quant && $quant->average_cost !== null ? (float) $quant->average_cost : null,
                        );

                        $written++;
                    }
                } else {
                    $key = static::quantKey((int) $product->id, null);
                    $quant = $quantMap[$key] ?? null;

                    static::writeRow(
                        out: $out,
                        companyId: $companyId,
                        warehouseId: $warehouseId,
                        locationId: $locationId,
                        warehouseLabel: $warehouseLabel,
                        locationLabel: $locationLabel,
                        product: $product,
                        variant: null,
                        quantity: $quant ? (float) $quant->quantity : 0,
                        averageCost: $quant && $quant->average_cost !== null ? (float) $quant->average_cost : null,
                    );

                    $written++;
                }

                if ($written % 500 === 0) {
                    fflush($out);

                    if (function_exists('ob_flush')) {
                        @ob_flush();
                    }

                    flush();
                }
            }
        }, 'products.id', 'id');
    }

    protected static function writeRow(
        $out,
        ?int $companyId,
        int $warehouseId,
        int $locationId,
        string $warehouseLabel,
        string $locationLabel,
        object $product,
        ?object $variant,
        float $quantity,
        ?float $averageCost
    ): void {
        $cost = $averageCost;

        if ($cost === null) {
            $cost = static::productCost($product, $variant);
        }

        fputcsv($out, [
            $warehouseLabel,
            $locationLabel,
            static::productReference($product, $variant),
            static::productName($product),
            $variant ? static::variantLabel($variant) : '',
            static::barcode($product, $variant),
            $quantity,
            '',
            $cost,
            '',
            (int) $product->id,
            $variant ? (int) $variant->id : '',
            $warehouseId,
            $locationId,
        ], ';');
    }

    protected static function productSelectColumns(): array
    {
        $columns = ['products.id'];

        foreach ([
            'company_id',
            'parent_product_id',
            'is_variant',
            'name',
            'description',
            'internal_reference',
            'sku',
            'reference',
            'code',
            'barcode',
            'variant_group',
            'variant_value',
            'standard_cost',
            'purchase_price',
            'last_purchase_cost',
            'cost',
        ] as $column) {
            if (Schema::hasColumn('products', $column)) {
                $columns[] = 'products.' . $column;
            }
        }

        return $columns;
    }

    protected static function variantsByParent(?int $companyId, array $parentIds): array
    {
        if (
            empty($parentIds)
            || ! Schema::hasTable('products')
            || ! Schema::hasColumn('products', 'parent_product_id')
        ) {
            return [];
        }

        $query = DB::table('products')
            ->select(static::productSelectColumns())
            ->whereIn('products.parent_product_id', $parentIds)
            ->orderBy('products.parent_product_id')
            ->orderBy('products.id');

        if (Schema::hasColumn('products', 'company_id') && $companyId) {
            $query->where('products.company_id', $companyId);
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('products.is_active', true);
        }

        if (Schema::hasColumn('products', 'is_variant')) {
            $query->where('products.is_variant', true);
        }

        $map = [];

        foreach ($query->get() as $variant) {
            $parentId = (int) ($variant->parent_product_id ?? 0);

            if (! $parentId) {
                continue;
            }

            $map[$parentId] ??= [];
            $map[$parentId][] = $variant;
        }

        return $map;
    }

    protected static function quantMap(
        ?int $companyId,
        int $warehouseId,
        int $locationId,
        array $productIds
    ): array {
        if (! Schema::hasTable('stock_quants') || empty($productIds)) {
            return [];
        }

        $select = [
            'product_id',
            'product_variant_id',
            DB::raw('SUM(quantity) as quantity'),
        ];

        if (Schema::hasColumn('stock_quants', 'average_cost')) {
            $select[] = DB::raw('MAX(average_cost) as average_cost');
        } else {
            $select[] = DB::raw('NULL as average_cost');
        }

        $query = DB::table('stock_quants')
            ->select($select)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id', 'product_variant_id');

        if (Schema::hasColumn('stock_quants', 'company_id')) {
            $companyId
                ? $query->where('company_id', $companyId)
                : $query->whereNull('company_id');
        }

        $map = [];

        foreach ($query->get() as $row) {
            $map[static::quantKey((int) $row->product_id, $row->product_variant_id ? (int) $row->product_variant_id : null)] = $row;
        }

        return $map;
    }

    protected static function quantKey(int $productId, ?int $variantId): string
    {
        return $productId . '|' . ($variantId ?: 0);
    }

    protected static function productCost(object $product, ?object $variant): ?float
    {
        foreach ([$variant, $product] as $row) {
            if (! $row) {
                continue;
            }

            foreach (['standard_cost', 'purchase_price', 'last_purchase_cost', 'cost'] as $column) {
                if (! Schema::hasColumn('products', $column)) {
                    continue;
                }

                $value = $row->{$column} ?? null;

                if ($value !== null && (float) $value > 0) {
                    return (float) $value;
                }
            }
        }

        return null;
    }

    protected static function warehouseLabel(int $warehouseId): string
    {
        return static::labelFromTable('warehouses', $warehouseId, ['code'], ['name']);
    }

    protected static function locationLabel(int $locationId): string
    {
        return static::labelFromTable('stock_locations', $locationId, ['code'], ['name']);
    }

    protected static function labelFromTable(string $table, int $id, array $codeColumns, array $nameColumns): string
    {
        if (! Schema::hasTable($table)) {
            return '#' . $id;
        }

        $row = DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '#' . $id;
        }

        $code = '';
        $name = '';

        foreach ($codeColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $value = trim((string) ($row->{$column} ?? ''));

                if ($value !== '') {
                    $code = $value;
                    break;
                }
            }
        }

        foreach ($nameColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $value = trim((string) ($row->{$column} ?? ''));

                if ($value !== '') {
                    $name = $value;
                    break;
                }
            }
        }

        if ($code !== '' && $name !== '') {
            return $code . ' - ' . $name;
        }

        return $name ?: ($code ?: ('#' . $id));
    }

    protected static function productReference(object $product, ?object $variant): string
    {
        foreach ([$variant, $product] as $row) {
            if (! $row) {
                continue;
            }

            foreach (['internal_reference', 'sku', 'reference', 'code'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $value = trim((string) ($row->{$column} ?? ''));

                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return '';
    }

    protected static function productName(object $product): string
    {
        foreach (['name', 'description'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                $value = trim((string) ($product->{$column} ?? ''));

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return 'Producto #' . $product->id;
    }

    protected static function variantLabel(object $variant): string
    {
        $group = Schema::hasColumn('products', 'variant_group')
            ? trim((string) ($variant->variant_group ?? ''))
            : '';

        $value = Schema::hasColumn('products', 'variant_value')
            ? trim((string) ($variant->variant_value ?? ''))
            : '';

        if ($group !== '' && $value !== '') {
            return $group . ': ' . $value;
        }

        if ($value !== '') {
            return $value;
        }

        if (Schema::hasColumn('products', 'name')) {
            $name = trim((string) ($variant->name ?? ''));

            if ($name !== '') {
                return $name;
            }
        }

        return 'Variante #' . $variant->id;
    }

    protected static function barcode(object $product, ?object $variant): string
    {
        foreach ([$variant, $product] as $row) {
            if (! $row) {
                continue;
            }

            foreach (['barcode', 'sku', 'internal_reference'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $value = trim((string) ($row->{$column} ?? ''));

                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return '';
    }

    protected static function safeFileName(string $value): string
    {
        $value = Str::ascii($value);
        $value = preg_replace('/[^A-Za-z0-9\-_\.]+/', '-', $value);
        $value = trim((string) $value, '-');

        return $value !== '' ? $value : 'plantilla-inventario';
    }
}
