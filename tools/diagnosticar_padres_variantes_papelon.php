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

function decodeExtra(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (is_object($value)) {
        return (array) $value;
    }

    if (is_string($value) && $value !== '') {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    return [];
}

titleLine('Leyendo SQL');

$products = parseInsertRows($base . '/products.sql', 'products');
$variations = parseInsertRows($base . '/variations.sql', 'variations');

$productsByOldId = [];

foreach ($products as $row) {
    $productsByOldId[toInt($row['id'] ?? 0)] = $row;
}

$activeRealVariations = array_values(array_filter($variations, function (array $row): bool {
    return empty($row['deleted_at'])
        && strtoupper(trim((string) ($row['name'] ?? ''))) !== 'DUMMY';
}));

$variationProductIds = [];

foreach ($activeRealVariations as $row) {
    $oldProductId = toInt($row['product_id'] ?? 0);

    if ($oldProductId > 0) {
        $variationProductIds[$oldProductId] = true;
    }
}

$bexiaProducts = DB::table('products')
    ->where('company_id', $companyId)
    ->select('id', 'name', 'internal_reference', 'sku', 'extra_attributes')
    ->get();

$bexiaByOldId = [];

foreach ($bexiaProducts as $product) {
    $extra = decodeExtra($product->extra_attributes);

    if (isset($extra['old_product_id'])) {
        $bexiaByOldId[(int) $extra['old_product_id']] = $product;
    }
}

$missingInBexia = [];
$missingInSql = [];
$foundInSqlButMissingInBexia = [];

foreach (array_keys($variationProductIds) as $oldProductId) {
    $existsInBexia = isset($bexiaByOldId[$oldProductId]);
    $existsInSql = isset($productsByOldId[$oldProductId]);

    if (! $existsInBexia) {
        $missingInBexia[] = $oldProductId;
    }

    if (! $existsInSql) {
        $missingInSql[] = $oldProductId;
    }

    if ($existsInSql && ! $existsInBexia) {
        $foundInSqlButMissingInBexia[] = $oldProductId;
    }
}

titleLine('Resumen');

echo 'Products SQL rows: ' . count($products) . PHP_EOL;
echo 'Variantes reales activas: ' . count($activeRealVariations) . PHP_EOL;
echo 'Productos padre distintos requeridos por variantes: ' . count($variationProductIds) . PHP_EOL;
echo 'Productos Bexia con old_product_id: ' . count($bexiaByOldId) . PHP_EOL;
echo 'Padres faltantes en Bexia: ' . count($missingInBexia) . PHP_EOL;
echo 'Padres faltantes en products.sql: ' . count($missingInSql) . PHP_EOL;
echo 'Padres sí están en products.sql pero no en Bexia: ' . count($foundInSqlButMissingInBexia) . PHP_EOL;

titleLine('Rango old_product_id en products.sql');

$ids = array_keys($productsByOldId);
sort($ids);

echo 'Min old_product_id: ' . ($ids[0] ?? 'NA') . PHP_EOL;
echo 'Max old_product_id: ' . (end($ids) ?: 'NA') . PHP_EOL;

titleLine('Primeros 80 padres faltantes en Bexia');

foreach (array_slice($missingInBexia, 0, 80) as $oldProductId) {
    $old = $productsByOldId[$oldProductId] ?? null;

    echo sprintf(
        "old_product_id=%s | en_products_sql=%s | name=%s | sku=%s | type=%s",
        $oldProductId,
        $old ? 'SI' : 'NO',
        $old['name'] ?? 'NO_EN_SQL',
        $old['sku'] ?? '',
        $old['type'] ?? ''
    ) . PHP_EOL;
}

titleLine('Primeros 80 productos SQL sin Bexia');

foreach (array_slice($foundInSqlButMissingInBexia, 0, 80) as $oldProductId) {
    $old = $productsByOldId[$oldProductId] ?? [];

    echo sprintf(
        "old_product_id=%s | name=%s | sku=%s | type=%s | category=%s | sub_category=%s",
        $oldProductId,
        $old['name'] ?? '',
        $old['sku'] ?? '',
        $old['type'] ?? '',
        $old['category_id'] ?? '',
        $old['sub_category_id'] ?? ''
    ) . PHP_EOL;
}

titleLine('Revisión puntual 1 al 12');

for ($oldProductId = 1; $oldProductId <= 12; $oldProductId++) {
    $old = $productsByOldId[$oldProductId] ?? null;
    $bexia = $bexiaByOldId[$oldProductId] ?? null;

    echo sprintf(
        "old=%s | sql=%s | bexia=%s | sql_name=%s | bexia_name=%s",
        $oldProductId,
        $old ? 'SI' : 'NO',
        $bexia ? 'SI' : 'NO',
        $old['name'] ?? '',
        $bexia?->name ?? ''
    ) . PHP_EOL;
}

titleLine('Conclusión');

if (count($foundInSqlButMissingInBexia) > 0) {
    echo "Hay productos padre en products.sql que no quedaron en Bexia. Primero hay que reimportar/corregir esos padres antes de crear variantes." . PHP_EOL;
} elseif (count($missingInSql) > 0) {
    echo "Hay variantes que apuntan a productos padre que no están en products.sql. Necesitamos un dump products.sql más completo o crear padres mínimos." . PHP_EOL;
} else {
    echo "Todos los padres existen. Ya podemos importar variantes." . PHP_EOL;
}
