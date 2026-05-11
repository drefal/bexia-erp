<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$companyId = (int) (getenv('BEXIA_COMPANY_ID') ?: 3);
$apply = getenv('BEXIA_APPLY') === '1';
$base = storage_path('app/imports/papelon');

function titleLine(string $title): void
{
    echo PHP_EOL;
    echo "======================================" . PHP_EOL;
    echo $title . PHP_EOL;
    echo "======================================" . PHP_EOL;
}

function sqlValue(string $value): mixed
{
    $value = trim($value);

    if (strcasecmp($value, 'NULL') === 0) {
        return null;
    }

    return $value;
}

function toInt(mixed $value): int
{
    if ($value === null || $value === '') {
        return 0;
    }

    return (int) $value;
}

function toFloat(mixed $value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }

    return (float) $value;
}

function isBarcodeLike(?string $value): bool
{
    $value = trim((string) $value);

    return preg_match('/^\d{8,14}$/', $value) === 1;
}

function cleanText(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strip_tags($value);
    $value = trim(preg_replace('/\s+/', ' ', $value));

    return $value !== '' ? $value : null;
}

function parseInsertRows(string $file, string $table): array
{
    if (! file_exists($file)) {
        return [];
    }

    $sql = file_get_contents($file);
    $rows = [];
    $columns = [];
    $insideInsert = false;

    foreach (preg_split('/\R/', $sql) as $line) {
        $trim = trim($line);

        if ($trim === '') {
            continue;
        }

        if (preg_match('/^INSERT INTO\s+`' . preg_quote($table, '/') . '`\s*\((.*?)\)\s*VALUES\s*$/i', $trim, $match)) {
            preg_match_all('/`([^`]+)`/', $match[1], $columnMatches);
            $columns = $columnMatches[1] ?? [];
            $insideInsert = true;
            continue;
        }

        if (! $insideInsert || $trim[0] !== '(') {
            continue;
        }

        $isLastTuple = str_ends_with($trim, ';');
        $tuple = preg_replace('/[;,]\s*$/', '', $trim);
        $values = parseSqlTuple($tuple);

        if (count($values) === count($columns)) {
            $rows[] = array_combine($columns, $values);
        } else {
            echo "WARN {$table}: columnas=" . count($columns) . " valores=" . count($values) . PHP_EOL;
        }

        if ($isLastTuple) {
            $insideInsert = false;
            $columns = [];
        }
    }

    return $rows;
}

function parseSqlTuple(string $tuple): array
{
    $tuple = trim($tuple);
    $tuple = trim($tuple, " \t\n\r\0\x0B()");

    $values = [];
    $token = '';
    $inString = false;
    $escape = false;
    $length = strlen($tuple);

    for ($i = 0; $i < $length; $i++) {
        $char = $tuple[$i];

        if ($inString) {
            if ($escape) {
                $token .= $char;
                $escape = false;
                continue;
            }

            if ($char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === "'") {
                if (($tuple[$i + 1] ?? null) === "'") {
                    $token .= "'";
                    $i++;
                    continue;
                }

                $inString = false;
                continue;
            }

            $token .= $char;
            continue;
        }

        if ($char === "'") {
            $inString = true;
            continue;
        }

        if ($char === ',') {
            $values[] = sqlValue($token);
            $token = '';
            continue;
        }

        $token .= $char;
    }

    $values[] = sqlValue($token);

    return $values;
}

function decodeExtra(mixed $value): array
{
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

function indexBy(array $rows, string $key): array
{
    $out = [];

    foreach ($rows as $row) {
        $out[toInt($row[$key] ?? 0)] = $row;
    }

    return $out;
}

function sourceProductMap(int $companyId): array
{
    $map = [];

    DB::table('products')
        ->where('company_id', $companyId)
        ->select('*')
        ->orderBy('id')
        ->get()
        ->each(function ($product) use (&$map): void {
            $extra = decodeExtra($product->extra_attributes ?? null);
            $sourceProductId = isset($extra['source_product_id']) ? (int) $extra['source_product_id'] : 0;

            if ($sourceProductId > 0 && ($extra['import_source'] ?? null) === 'papelon') {
                $map[$sourceProductId] = $product;
            }
        });

    return $map;
}

function existingVariantsBySource(int $companyId): array
{
    $map = [];

    DB::table('products')
        ->where('company_id', $companyId)
        ->select('id', 'internal_reference', 'extra_attributes')
        ->orderBy('id')
        ->get()
        ->each(function ($product) use (&$map): void {
            $extra = decodeExtra($product->extra_attributes ?? null);
            $sourceVariationId = isset($extra['source_variation_id']) ? (int) $extra['source_variation_id'] : 0;

            if ($sourceVariationId > 0 && ($extra['import_source'] ?? null) === 'papelon_variant') {
                $map[$sourceVariationId] = $product;
            }
        });

    return $map;
}

function referenceExists(int $companyId, string $reference, ?int $ignoreProductId = null): bool
{
    $query = DB::table('products')
        ->where('company_id', $companyId)
        ->where('internal_reference', $reference);

    if ($ignoreProductId) {
        $query->where('id', '!=', $ignoreProductId);
    }

    return $query->exists();
}

function variantReference(int $companyId, object $parent, array $variation, ?int $ignoreProductId = null): string
{
    $sourceVariationId = toInt($variation['id'] ?? 0);
    $subSku = trim((string) ($variation['sub_sku'] ?? ''));

    $reference = $subSku !== ''
        ? $subSku
        : trim((string) $parent->internal_reference) . '-V' . $sourceVariationId;

    if ($reference === '') {
        $reference = 'VAR-' . $sourceVariationId;
    }

    if (referenceExists($companyId, $reference, $ignoreProductId)) {
        $reference = $reference . '-V' . $sourceVariationId;
    }

    return $reference;
}

function insertOrUpdateProductImage(int $companyId, int $productId, ?string $imagePath, string $title): void
{
    if (! $imagePath || ! Schema::hasTable('product_images')) {
        return;
    }

    $exists = DB::table('product_images')
        ->where('product_id', $productId)
        ->where('image_path', $imagePath)
        ->exists();

    if ($exists) {
        return;
    }

    DB::table('product_images')->insert([
        'company_id' => $companyId,
        'product_id' => $productId,
        'product_template_id' => null,
        'image_path' => $imagePath,
        'title' => $title,
        'alt_text' => $title,
        'is_primary' => true,
        'sort_order' => 1,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

titleLine('Importar variantes Papelón V1');

echo "Empresa destino: {$companyId}" . PHP_EOL;
echo "Modo APPLY=" . ($apply ? '1 aplica cambios' : '0 vista previa') . PHP_EOL;
echo "Metadata: source_product_id / source_variation_id" . PHP_EOL;

foreach (['products', 'product_categories', 'inventory_units'] as $table) {
    if (! Schema::hasTable($table)) {
        throw new RuntimeException("Falta tabla requerida: {$table}");
    }
}

$productVariations = parseInsertRows($base . '/product_variations.sql', 'product_variations');
$variations = parseInsertRows($base . '/variations.sql', 'variations');
$variationLocations = parseInsertRows($base . '/variation_location_details.sql', 'variation_location_details');

$productVariationById = indexBy($productVariations, 'id');
$parentBySourceProduct = sourceProductMap($companyId);
$existingVariantBySource = existingVariantsBySource($companyId);

$stockByVariation = [];

foreach ($variationLocations as $row) {
    $sourceVariationId = toInt($row['variation_id'] ?? 0);

    if ($sourceVariationId <= 0) {
        continue;
    }

    $stockByVariation[$sourceVariationId] = ($stockByVariation[$sourceVariationId] ?? 0) + toFloat($row['qty_available'] ?? 0);
}

$activeVariations = array_values(array_filter($variations, fn ($row) => empty($row['deleted_at'])));
$realVariations = array_values(array_filter($activeVariations, fn ($row) => strtoupper(trim((string) ($row['name'] ?? ''))) !== 'DUMMY'));

$missingParents = [];
$toCreate = 0;
$toUpdate = 0;
$groups = [];

foreach ($realVariations as $variation) {
    $sourceProductId = toInt($variation['product_id'] ?? 0);
    $sourceVariationId = toInt($variation['id'] ?? 0);
    $sourceProductVariationId = toInt($variation['product_variation_id'] ?? 0);

    if (! isset($parentBySourceProduct[$sourceProductId])) {
        $missingParents[$sourceProductId] = true;
        continue;
    }

    if (isset($existingVariantBySource[$sourceVariationId])) {
        $toUpdate++;
    } else {
        $toCreate++;
    }

    $group = $productVariationById[$sourceProductVariationId]['name'] ?? 'SIN_GRUPO';
    $groups[$group] = ($groups[$group] ?? 0) + 1;
}

titleLine('Resumen preview');

echo 'Product variations grupos: ' . count($productVariations) . PHP_EOL;
echo 'Variations total: ' . count($variations) . PHP_EOL;
echo 'Variantes reales activas: ' . count($realVariations) . PHP_EOL;
echo 'Productos padre disponibles: ' . count($parentBySourceProduct) . PHP_EOL;
echo 'Padres faltantes: ' . count($missingParents) . PHP_EOL;
echo 'Variantes ya existentes: ' . $toUpdate . PHP_EOL;
echo 'Variantes por crear: ' . $toCreate . PHP_EOL;

echo PHP_EOL . 'Grupos:' . PHP_EOL;
ksort($groups);

foreach ($groups as $group => $count) {
    echo "- {$group}: {$count}" . PHP_EOL;
}

titleLine('Primeras 40 variantes');

$shown = 0;

foreach ($realVariations as $variation) {
    $sourceProductId = toInt($variation['product_id'] ?? 0);
    $sourceVariationId = toInt($variation['id'] ?? 0);
    $sourceProductVariationId = toInt($variation['product_variation_id'] ?? 0);
    $parent = $parentBySourceProduct[$sourceProductId] ?? null;

    if (! $parent) {
        continue;
    }

    $group = $productVariationById[$sourceProductVariationId]['name'] ?? 'SIN_GRUPO';
    $value = trim((string) ($variation['name'] ?? ''));
    $stock = $stockByVariation[$sourceVariationId] ?? 0;
    $existing = $existingVariantBySource[$sourceVariationId] ?? null;
    $reference = variantReference($companyId, $parent, $variation, $existing?->id ?? null);

    echo sprintf(
        "source_product_id=%s | parent=%s | source_variation_id=%s | grupo=%s | valor=%s | ref=%s | costo=%s | venta=%s | stock=%s",
        $sourceProductId,
        $parent->name,
        $sourceVariationId,
        $group,
        $value,
        $reference,
        $variation['default_purchase_price'] ?? '0',
        $variation['sell_price_inc_tax'] ?? ($variation['default_sell_price'] ?? '0'),
        $stock
    ) . PHP_EOL;

    $shown++;

    if ($shown >= 40) {
        break;
    }
}

if (count($missingParents) > 0) {
    titleLine('Padres faltantes');
    foreach (array_slice(array_keys($missingParents), 0, 80) as $sourceProductId) {
        echo "source_product_id={$sourceProductId}" . PHP_EOL;
    }
}

if (! $apply) {
    titleLine('Vista previa terminada');
    echo "No se aplicaron cambios." . PHP_EOL;
    echo "Para aplicar ejecuta:" . PHP_EOL;
    echo "BEXIA_APPLY=1 ./importar_variantes_papelon_empresa3_v1.sh" . PHP_EOL;
    return;
}

if (count($missingParents) > 0) {
    throw new RuntimeException('No se puede aplicar. Hay productos padre faltantes.');
}

titleLine('Aplicando variantes');

$created = 0;
$updated = 0;
$skipped = 0;
$imagesLinked = 0;

DB::transaction(function () use (
    $companyId,
    $realVariations,
    $productVariationById,
    $stockByVariation,
    &$created,
    &$updated,
    &$skipped,
    &$imagesLinked
): void {
    $parentBySourceProduct = sourceProductMap($companyId);
    $existingVariantBySource = existingVariantsBySource($companyId);
    $now = now();

    foreach ($realVariations as $variation) {
        $sourceProductId = toInt($variation['product_id'] ?? 0);
        $sourceVariationId = toInt($variation['id'] ?? 0);
        $sourceProductVariationId = toInt($variation['product_variation_id'] ?? 0);

        $parent = $parentBySourceProduct[$sourceProductId] ?? null;

        if (! $parent || $sourceVariationId <= 0) {
            $skipped++;
            continue;
        }

        $group = $productVariationById[$sourceProductVariationId]['name'] ?? 'SIN_GRUPO';
        $value = trim((string) ($variation['name'] ?? ''));

        if ($value === '') {
            $skipped++;
            continue;
        }

        $existing = $existingVariantBySource[$sourceVariationId] ?? null;
        $reference = variantReference($companyId, $parent, $variation, $existing?->id ?? null);
        $barcode = isBarcodeLike($reference) ? $reference : null;

        $stock = $stockByVariation[$sourceVariationId] ?? 0;
        $salePrice = toFloat($variation['sell_price_inc_tax'] ?? ($variation['default_sell_price'] ?? 0));
        $cost = toFloat($variation['default_purchase_price'] ?? 0);

        $variantName = trim((string) $parent->name) . ' - ' . $value;

        $extra = [
            'import_source' => 'papelon_variant',
            'source_product_id' => $sourceProductId,
            'source_variation_id' => $sourceVariationId,
            'source_product_variation_id' => $sourceProductVariationId,
            'parent_product_id' => (int) $parent->id,
            'variant_group' => $group,
            'variant_value' => $value,
            'source_sub_sku' => $variation['sub_sku'] ?? null,
            'source_stock_qty' => $stock,
            'source_default_purchase_price' => $variation['default_purchase_price'] ?? null,
            'source_default_sell_price' => $variation['default_sell_price'] ?? null,
            'source_sell_price_inc_tax' => $variation['sell_price_inc_tax'] ?? null,
        ];

        $data = filterColumns('products', [
            'company_id' => $companyId,
            'product_category_id' => $parent->product_category_id ?? null,
            'inventory_unit_id' => $parent->inventory_unit_id ?? null,
            'name' => $variantName,
            'internal_reference' => $reference,
            'sku' => $barcode,
            'barcode' => $barcode,
            'description' => cleanText($parent->description ?? null),
            'product_type' => $parent->product_type ?? 'stockable',
            'tracking' => $parent->tracking ?? 'none',
            'costing_method' => $parent->costing_method ?? 'average',
            'sale_price' => $salePrice,
            'standard_cost' => $cost,
            'purchase_price' => $cost,
            'can_be_sold' => true,
            'can_be_purchased' => true,
            'available_in_pos' => true,
            'is_active' => true,
            'sat_unit_code' => $parent->sat_unit_code ?? 'H87',
            'image_path' => $parent->image_path ?? null,
            'extra_attributes' => json_encode($extra, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);

        if ($existing) {
            DB::table('products')->where('id', $existing->id)->update($data);
            $productId = (int) $existing->id;
            $updated++;
        } else {
            $data['created_at'] = $now;
            $productId = DB::table('products')->insertGetId($data);
            $created++;
        }

        if (! empty($parent->image_path)) {
            insertOrUpdateProductImage($companyId, $productId, $parent->image_path, $variantName);
            $imagesLinked++;
        }
    }
});

titleLine('Resultado');

echo "Variantes creadas: {$created}" . PHP_EOL;
echo "Variantes actualizadas: {$updated}" . PHP_EOL;
echo "Omitidas: {$skipped}" . PHP_EOL;
echo "Imágenes heredadas/ligadas: {$imagesLinked}" . PHP_EOL;
echo "Total productos empresa {$companyId}: " . DB::table('products')->where('company_id', $companyId)->count() . PHP_EOL;
echo "Total variantes importadas: " . DB::table('products')
    ->where('company_id', $companyId)
    ->where('extra_attributes', 'like', '%source_variation_id%')
    ->count() . PHP_EOL;
