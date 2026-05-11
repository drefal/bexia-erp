<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$base = storage_path('app/imports/papelon');
$companyId = 3;

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

function indexBy(array $rows, string $key): array
{
    $out = [];

    foreach ($rows as $row) {
        $out[toInt($row[$key] ?? 0)] = $row;
    }

    return $out;
}

function decodeExtra(mixed $value): array
{
    if (is_string($value) && $value !== '') {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    return [];
}

titleLine('Leyendo dumps');

$products = parseInsertRows($base . '/products.sql', 'products');
$productVariations = parseInsertRows($base . '/product_variations.sql', 'product_variations');
$variations = parseInsertRows($base . '/variations.sql', 'variations');
$variationLocations = parseInsertRows($base . '/variation_location_details.sql', 'variation_location_details');
$brands = parseInsertRows($base . '/brands.sql', 'brands');

echo 'products.sql products: ' . count($products) . PHP_EOL;
echo 'product_variations.sql product_variations: ' . count($productVariations) . PHP_EOL;
echo 'variations.sql variations: ' . count($variations) . PHP_EOL;
echo 'variation_location_details.sql rows: ' . count($variationLocations) . PHP_EOL;
echo 'brands.sql brands: ' . count($brands) . PHP_EOL;

titleLine('Productos Bexia por source_product_id');

$sourceProductToBexia = [];

DB::table('products')
    ->where('company_id', $companyId)
    ->select('id', 'name', 'internal_reference', 'image_path', 'extra_attributes')
    ->orderBy('id')
    ->get()
    ->each(function ($product) use (&$sourceProductToBexia): void {
        $extra = decodeExtra($product->extra_attributes);
        $sourceProductId = isset($extra['source_product_id']) ? (int) $extra['source_product_id'] : 0;

        if ($sourceProductId > 0) {
            $sourceProductToBexia[$sourceProductId] = $product;
        }
    });

echo 'Productos empresa 3 con source_product_id: ' . count($sourceProductToBexia) . PHP_EOL;

titleLine('Variantes');

$activeVariations = array_values(array_filter($variations, fn ($row) => empty($row['deleted_at'])));
$dummyVariations = array_values(array_filter($activeVariations, fn ($row) => strtoupper(trim((string) ($row['name'] ?? ''))) === 'DUMMY'));
$realVariations = array_values(array_filter($activeVariations, fn ($row) => strtoupper(trim((string) ($row['name'] ?? ''))) !== 'DUMMY'));

echo 'Variantes activas: ' . count($activeVariations) . PHP_EOL;
echo 'DUMMY activas: ' . count($dummyVariations) . PHP_EOL;
echo 'Variantes reales activas: ' . count($realVariations) . PHP_EOL;

$productVariationById = indexBy($productVariations, 'id');

$requiredSourceProducts = [];
$missingParents = [];
$groups = [];

foreach ($realVariations as $variation) {
    $sourceProductId = toInt($variation['product_id'] ?? 0);
    $requiredSourceProducts[$sourceProductId] = true;

    if (! isset($sourceProductToBexia[$sourceProductId])) {
        $missingParents[$sourceProductId] = true;
    }

    $sourceProductVariationId = toInt($variation['product_variation_id'] ?? 0);
    $group = $productVariationById[$sourceProductVariationId]['name'] ?? 'SIN_GRUPO';

    $groups[$group] = ($groups[$group] ?? 0) + 1;
}

echo 'Productos padre requeridos por variantes: ' . count($requiredSourceProducts) . PHP_EOL;
echo 'Productos padre faltantes: ' . count($missingParents) . PHP_EOL;

echo PHP_EOL . 'Grupos detectados:' . PHP_EOL;
ksort($groups);

foreach ($groups as $group => $count) {
    echo "- {$group}: {$count}" . PHP_EOL;
}

titleLine('Stock por variante');

$stockByVariation = [];

foreach ($variationLocations as $row) {
    $sourceVariationId = toInt($row['variation_id'] ?? 0);

    if ($sourceVariationId <= 0) {
        continue;
    }

    $stockByVariation[$sourceVariationId] = ($stockByVariation[$sourceVariationId] ?? 0) + toFloat($row['qty_available'] ?? 0);
}

$positive = 0;
$negative = 0;
$zero = 0;

foreach ($activeVariations as $variation) {
    $sourceVariationId = toInt($variation['id'] ?? 0);
    $stock = $stockByVariation[$sourceVariationId] ?? 0;

    if ($stock > 0) {
        $positive++;
    } elseif ($stock < 0) {
        $negative++;
    } else {
        $zero++;
    }
}

echo 'Variantes con stock positivo: ' . $positive . PHP_EOL;
echo 'Variantes con stock negativo: ' . $negative . PHP_EOL;
echo 'Variantes con stock cero/sin fila: ' . $zero . PHP_EOL;

titleLine('Primeras 50 variantes reales');

foreach (array_slice($realVariations, 0, 50) as $variation) {
    $sourceProductId = toInt($variation['product_id'] ?? 0);
    $sourceVariationId = toInt($variation['id'] ?? 0);
    $sourceProductVariationId = toInt($variation['product_variation_id'] ?? 0);

    $parent = $sourceProductToBexia[$sourceProductId] ?? null;
    $group = $productVariationById[$sourceProductVariationId]['name'] ?? 'SIN_GRUPO';
    $stock = $stockByVariation[$sourceVariationId] ?? 0;

    echo sprintf(
        "source_product_id=%s | parent=%s | source_variation_id=%s | grupo=%s | valor=%s | sub_sku=%s | costo=%s | venta=%s | stock=%s",
        $sourceProductId,
        $parent?->name ?? 'NO_ENCONTRADO',
        $sourceVariationId,
        $group,
        $variation['name'] ?? '',
        $variation['sub_sku'] ?? '',
        $variation['default_purchase_price'] ?? '0',
        $variation['sell_price_inc_tax'] ?? ($variation['default_sell_price'] ?? '0'),
        $stock
    ) . PHP_EOL;
}

titleLine('Padres faltantes');

if (count($missingParents) === 0) {
    echo 'OK: Todos los productos padre requeridos existen en Bexia.' . PHP_EOL;
} else {
    foreach (array_slice(array_keys($missingParents), 0, 80) as $sourceProductId) {
        echo 'source_product_id=' . $sourceProductId . PHP_EOL;
    }
}

titleLine('Conclusión');

if (count($missingParents) === 0) {
    echo 'Listo para importar variantes usando source_product_id y source_variation_id.' . PHP_EOL;
} else {
    echo 'No importar variantes todavía. Faltan productos padre.' . PHP_EOL;
}
