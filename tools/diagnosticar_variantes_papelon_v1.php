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

    $pattern = '/INSERT INTO\s+`' . preg_quote($table, '/') . '`\s*\((.*?)\)\s*VALUES\s*(.*?);/is';
    preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);

    $rows = [];

    foreach ($matches as $match) {
        preg_match_all('/`([^`]+)`/', $match[1], $columnMatches);
        $columns = $columnMatches[1] ?? [];

        foreach (parseSqlTuples($match[2]) as $tuple) {
            $values = parseSqlTuple($tuple);

            if (count($values) !== count($columns)) {
                echo "WARN {$table}: columnas=" . count($columns) . " valores=" . count($values) . PHP_EOL;
                continue;
            }

            $rows[] = array_combine($columns, $values);
        }
    }

    return $rows;
}

function parseSqlTuples(string $body): array
{
    $tuples = [];
    $depth = 0;
    $inString = false;
    $escape = false;
    $start = null;
    $length = strlen($body);

    for ($i = 0; $i < $length; $i++) {
        $char = $body[$i];

        if ($inString) {
            if ($escape) {
                $escape = false;
                continue;
            }

            if ($char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === "'") {
                if (($body[$i + 1] ?? null) === "'") {
                    $i++;
                    continue;
                }

                $inString = false;
            }

            continue;
        }

        if ($char === "'") {
            $inString = true;
            continue;
        }

        if ($char === '(') {
            if ($depth === 0) {
                $start = $i;
            }

            $depth++;
            continue;
        }

        if ($char === ')') {
            $depth--;

            if ($depth === 0 && $start !== null) {
                $tuples[] = substr($body, $start, $i - $start + 1);
                $start = null;
            }
        }
    }

    return $tuples;
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
        $out[(int) ($row[$key] ?? 0)] = $row;
    }

    return $out;
}

function oldProductIdFromBexia(object $product): ?int
{
    $extra = json_decode((string) $product->extra_attributes, true);

    if (! is_array($extra)) {
        return null;
    }

    return isset($extra['old_product_id']) ? (int) $extra['old_product_id'] : null;
}

titleLine('Leyendo dumps');

$products = parseInsertRows($base . '/products.sql', 'products');
$productVariations = parseInsertRows($base . '/product_variations.sql', 'product_variations');
$variations = parseInsertRows($base . '/variations.sql', 'variations');
$variationLocations = parseInsertRows($base . '/variation_location_details.sql', 'variation_location_details');
$brands = parseInsertRows($base . '/brands.sql', 'brands');
$taxRates = parseInsertRows($base . '/tax_rates.sql', 'tax_rates');
$businessLocations = parseInsertRows($base . '/business_locations.sql', 'business_locations');

echo 'products.sql products: ' . count($products) . PHP_EOL;
echo 'product_variations.sql product_variations: ' . count($productVariations) . PHP_EOL;
echo 'variations.sql variations: ' . count($variations) . PHP_EOL;
echo 'variation_location_details.sql rows: ' . count($variationLocations) . PHP_EOL;
echo 'brands.sql brands: ' . count($brands) . PHP_EOL;
echo 'tax_rates.sql taxes: ' . count($taxRates) . PHP_EOL;
echo 'business_locations.sql locations: ' . count($businessLocations) . PHP_EOL;

titleLine('Estado en Bexia');

$bexiaProducts = DB::table('products')
    ->where('company_id', $companyId)
    ->select('id', 'name', 'internal_reference', 'extra_attributes')
    ->get();

$oldProductToBexia = [];

foreach ($bexiaProducts as $product) {
    $oldId = oldProductIdFromBexia($product);

    if ($oldId) {
        $oldProductToBexia[$oldId] = $product;
    }
}

echo 'Productos empresa 3: ' . $bexiaProducts->count() . PHP_EOL;
echo 'Productos importados con old_product_id: ' . count($oldProductToBexia) . PHP_EOL;

titleLine('Variaciones');

$activeVariations = array_values(array_filter($variations, fn ($row) => empty($row['deleted_at'])));
$deletedVariations = array_values(array_filter($variations, fn ($row) => ! empty($row['deleted_at'])));
$dummyVariations = array_values(array_filter($activeVariations, fn ($row) => strtoupper(trim((string) ($row['name'] ?? ''))) === 'DUMMY'));
$realVariations = array_values(array_filter($activeVariations, fn ($row) => strtoupper(trim((string) ($row['name'] ?? ''))) !== 'DUMMY'));

echo 'Variations activas: ' . count($activeVariations) . PHP_EOL;
echo 'Variations deleted_at omitibles: ' . count($deletedVariations) . PHP_EOL;
echo 'DUMMY activas: ' . count($dummyVariations) . PHP_EOL;
echo 'Variantes reales activas: ' . count($realVariations) . PHP_EOL;

$productVariationById = indexBy($productVariations, 'id');

$groups = [];

foreach ($realVariations as $row) {
    $pvId = toInt($row['product_variation_id'] ?? 0);
    $pv = $productVariationById[$pvId] ?? null;
    $group = $pv['name'] ?? 'SIN_GRUPO';
    $groups[$group] = ($groups[$group] ?? 0) + 1;
}

echo PHP_EOL . 'Grupos detectados:' . PHP_EOL;
ksort($groups);

foreach ($groups as $group => $count) {
    echo "- {$group}: {$count}" . PHP_EOL;
}

titleLine('Stock por variation_id');

$stockByVariation = [];

foreach ($variationLocations as $row) {
    $variationId = toInt($row['variation_id'] ?? 0);

    if ($variationId <= 0) {
        continue;
    }

    $stockByVariation[$variationId] = ($stockByVariation[$variationId] ?? 0) + toFloat($row['qty_available'] ?? 0);
}

$withStock = 0;
$negativeStock = 0;
$zeroStock = 0;

foreach ($activeVariations as $row) {
    $variationId = toInt($row['id'] ?? 0);
    $stock = $stockByVariation[$variationId] ?? 0;

    if ($stock > 0) {
        $withStock++;
    } elseif ($stock < 0) {
        $negativeStock++;
    } else {
        $zeroStock++;
    }
}

echo 'Variaciones con stock positivo: ' . $withStock . PHP_EOL;
echo 'Variaciones con stock negativo: ' . $negativeStock . PHP_EOL;
echo 'Variaciones con stock cero/sin fila: ' . $zeroStock . PHP_EOL;

titleLine('Primeras 40 variantes reales como quedarían');

$shown = 0;

foreach ($realVariations as $row) {
    $oldProductId = toInt($row['product_id'] ?? 0);
    $oldVariationId = toInt($row['id'] ?? 0);

    $parent = $oldProductToBexia[$oldProductId] ?? null;

    $pvId = toInt($row['product_variation_id'] ?? 0);
    $pv = $productVariationById[$pvId] ?? null;
    $group = $pv['name'] ?? 'SIN_GRUPO';

    $name = trim((string) ($row['name'] ?? ''));
    $subSku = trim((string) ($row['sub_sku'] ?? ''));
    $stock = $stockByVariation[$oldVariationId] ?? 0;

    echo sprintf(
        "old_product=%s | parent=%s | old_variation=%s | grupo=%s | valor=%s | sub_sku=%s | costo=%s | venta=%s | stock=%s",
        $oldProductId,
        $parent?->name ?? 'NO ENCONTRADO EN BEXIA',
        $oldVariationId,
        $group,
        $name,
        $subSku,
        $row['default_purchase_price'] ?? '0',
        $row['sell_price_inc_tax'] ?? ($row['default_sell_price'] ?? '0'),
        $stock
    ) . PHP_EOL;

    $shown++;

    if ($shown >= 40) {
        break;
    }
}

titleLine('Marcas');

foreach (array_slice($brands, 0, 30) as $brand) {
    echo 'brand old_id=' . ($brand['id'] ?? '') . ' name=' . ($brand['name'] ?? '') . PHP_EOL;
}

titleLine('Impuestos / business');

foreach ($taxRates as $tax) {
    echo 'tax old_id=' . ($tax['id'] ?? '') . ' name=' . ($tax['name'] ?? '') . ' amount=' . ($tax['amount'] ?? '') . PHP_EOL;
}

titleLine('Conclusión');

echo "No importamos units.sql. Usaremos unidad interna por defecto y catálogo SAT de Bexia." . PHP_EOL;
echo "Los impuestos se deben convertir a configuración fiscal/business, no a producto directo." . PHP_EOL;
echo "El siguiente paso será importar variantes reales creando productos hijo y actualizar precios/stock de productos simples desde DUMMY." . PHP_EOL;
