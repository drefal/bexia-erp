<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$companyId = 3;

function decodeExtra(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (is_string($value) && $value !== '') {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    return [];
}

function filterColumns(string $table, array $data): array
{
    return array_filter(
        $data,
        fn ($value, $column) => Schema::hasColumn($table, $column),
        ARRAY_FILTER_USE_BOTH
    );
}

echo PHP_EOL;
echo "======================================" . PHP_EOL;
echo " Sincronizar variantes formales Papelón" . PHP_EOL;
echo "======================================" . PHP_EOL;

foreach (['parent_product_id', 'has_variants', 'is_variant', 'variant_group', 'variant_value', 'variant_name', 'variant_signature'] as $column) {
    if (! Schema::hasColumn('products', $column)) {
        throw new RuntimeException("Falta columna products.{$column}");
    }
}

$products = DB::table('products')
    ->where('company_id', $companyId)
    ->select('id', 'name', 'internal_reference', 'extra_attributes')
    ->get();

$parentsBySourceProductId = [];
$variants = [];

foreach ($products as $product) {
    $extra = decodeExtra($product->extra_attributes);

    if (($extra['import_source'] ?? null) === 'papelon' && isset($extra['source_product_id'])) {
        $parentsBySourceProductId[(int) $extra['source_product_id']] = $product;
    }

    if (($extra['import_source'] ?? null) === 'papelon_variant' && isset($extra['source_variation_id'])) {
        $variants[] = [$product, $extra];
    }
}

$linked = 0;
$missingParent = 0;
$parentIds = [];
$now = now();

DB::transaction(function () use ($variants, $parentsBySourceProductId, &$linked, &$missingParent, &$parentIds, $now): void {
    foreach ($variants as [$variant, $extra]) {
        $sourceProductId = (int) ($extra['source_product_id'] ?? 0);
        $parent = $parentsBySourceProductId[$sourceProductId] ?? null;

        if (! $parent) {
            $missingParent++;
            continue;
        }

        $group = $extra['variant_group'] ?? null;
        $value = $extra['variant_value'] ?? null;

        $data = filterColumns('products', [
            'parent_product_id' => $parent->id,
            'is_variant' => true,
            'has_variants' => false,
            'variant_group' => $group,
            'variant_value' => $value,
            'variant_name' => $value,
            'variant_signature' => trim((string) $group) . ':' . trim((string) $value),
            'updated_at' => $now,
        ]);

        DB::table('products')->where('id', $variant->id)->update($data);

        $parentIds[(int) $parent->id] = true;
        $linked++;
    }

    if (count($parentIds) > 0) {
        DB::table('products')
            ->whereIn('id', array_keys($parentIds))
            ->update(filterColumns('products', [
                'is_variant' => false,
                'has_variants' => true,
                'parent_product_id' => null,
                'updated_at' => $now,
            ]));
    }
});

echo "Variantes ligadas: {$linked}" . PHP_EOL;
echo "Padres con variantes: " . count($parentIds) . PHP_EOL;
echo "Padres faltantes: {$missingParent}" . PHP_EOL;

echo PHP_EOL . "Ejemplo Papel Lustre:" . PHP_EOL;

$parent = DB::table('products')
    ->where('company_id', $companyId)
    ->where('internal_reference', '0005')
    ->first();

if ($parent) {
    dump([
        'parent_id' => $parent->id,
        'name' => $parent->name,
        'has_variants' => $parent->has_variants ?? null,
    ]);

    DB::table('products')
        ->where('company_id', $companyId)
        ->where('parent_product_id', $parent->id)
        ->select('id', 'name', 'internal_reference', 'variant_group', 'variant_value', 'is_variant')
        ->orderBy('variant_value')
        ->limit(20)
        ->get()
        ->each(fn ($row) => dump((array) $row));
}
