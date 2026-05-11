<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$companyId = 3;
$sqlFile = storage_path('app/imports/papelon/products.sql');

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

function parseInsertRows(string $sql, string $table): array
{
    $pattern = '/INSERT INTO\s+`' . preg_quote($table, '/') . '`\s*\((.*?)\)\s*VALUES\s*(.*?);/is';
    preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);

    $rows = [];

    foreach ($matches as $match) {
        preg_match_all('/`([^`]+)`/', $match[1], $columnMatches);
        $columns = $columnMatches[1] ?? [];

        foreach (parseSqlTuples($match[2]) as $tuple) {
            $values = parseSqlTuple($tuple);

            if (count($values) !== count($columns)) {
                echo "WARN: fila con columnas distintas. columns=" . count($columns) . " values=" . count($values) . PHP_EOL;
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

function productReference(array $row): string
{
    $oldId = toInt($row['id'] ?? 0);
    $oldSku = trim((string) ($row['sku'] ?? ''));

    return $oldSku !== '' ? $oldSku : 'IMP-' . $oldId;
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

titleLine('Validación inicial');

if (! file_exists($sqlFile)) {
    throw new RuntimeException("No existe {$sqlFile}");
}

foreach (['products', 'product_categories', 'product_images'] as $table) {
    echo $table . ': ' . (Schema::hasTable($table) ? 'OK' : 'FALTA') . PHP_EOL;
}

$sql = file_get_contents($sqlFile);
$rows = parseInsertRows($sql, 'products');

$activeRows = array_values(array_filter($rows, fn ($row) => empty($row['deleted_at'])));
$deletedRows = array_values(array_filter($rows, fn ($row) => ! empty($row['deleted_at'])));
$blankNameRows = array_values(array_filter($activeRows, fn ($row) => trim((string) ($row['name'] ?? '')) === ''));

$importableRows = array_values(array_filter($activeRows, function ($row) {
    return toInt($row['id'] ?? 0) > 0 && trim((string) ($row['name'] ?? '')) !== '';
}));

$byType = [];
$byReference = [];

foreach ($importableRows as $row) {
    $type = trim((string) ($row['type'] ?? 'SIN_TIPO'));
    $byType[$type] = ($byType[$type] ?? 0) + 1;

    $ref = productReference($row);
    $byReference[$ref][] = [
        'old_id' => toInt($row['id'] ?? 0),
        'name' => trim((string) ($row['name'] ?? '')),
        'type' => $type,
        'sku' => trim((string) ($row['sku'] ?? '')),
    ];
}

$duplicateRefs = array_filter($byReference, fn ($items) => count($items) > 1);
$uniqueRefs = count($byReference);

$importedProducts = DB::table('products')
    ->where('company_id', $companyId)
    ->select('id', 'name', 'internal_reference', 'extra_attributes')
    ->get();

$importedOldIds = [];
$importedFromSource = 0;

foreach ($importedProducts as $product) {
    $extra = decodeExtra($product->extra_attributes);

    if (($extra['source'] ?? null) === 'papelon_import') {
        $importedFromSource++;
    }

    if (isset($extra['old_product_id'])) {
        $importedOldIds[(int) $extra['old_product_id']] = [
            'bexia_id' => $product->id,
            'name' => $product->name,
            'internal_reference' => $product->internal_reference,
        ];
    }
}

$oldIds = [];

foreach ($importableRows as $row) {
    $oldIds[toInt($row['id'] ?? 0)] = [
        'name' => trim((string) ($row['name'] ?? '')),
        'ref' => productReference($row),
        'type' => trim((string) ($row['type'] ?? '')),
        'deleted_at' => $row['deleted_at'] ?? null,
    ];
}

$missingOldIds = array_diff_key($oldIds, $importedOldIds);

titleLine('Resumen SQL vs Bexia');

echo 'Total rows SQL products: ' . count($rows) . PHP_EOL;
echo 'Activos sin deleted_at: ' . count($activeRows) . PHP_EOL;
echo 'Con deleted_at omitidos: ' . count($deletedRows) . PHP_EOL;
echo 'Activos sin nombre omitibles: ' . count($blankNameRows) . PHP_EOL;
echo 'Importables por reglas actuales: ' . count($importableRows) . PHP_EOL;
echo 'Referencias únicas calculadas: ' . $uniqueRefs . PHP_EOL;
echo 'Referencias duplicadas: ' . count($duplicateRefs) . PHP_EOL;
echo 'Productos actuales empresa 3: ' . $importedProducts->count() . PHP_EOL;
echo 'Productos con source papelon_import: ' . $importedFromSource . PHP_EOL;
echo 'Old IDs detectados ya importados: ' . count($importedOldIds) . PHP_EOL;
echo 'Old IDs faltantes: ' . count($missingOldIds) . PHP_EOL;

titleLine('Conteo por type');

foreach ($byType as $type => $count) {
    echo "{$type}: {$count}" . PHP_EOL;
}

titleLine('Primeras 50 referencias duplicadas');

$shown = 0;

foreach ($duplicateRefs as $ref => $items) {
    echo PHP_EOL . "REF {$ref} => " . count($items) . " productos" . PHP_EOL;

    foreach (array_slice($items, 0, 8) as $item) {
        echo "- old_id={$item['old_id']} type={$item['type']} sku={$item['sku']} name={$item['name']}" . PHP_EOL;
    }

    $shown++;

    if ($shown >= 50) {
        break;
    }
}

titleLine('Primeros 80 old IDs faltantes');

foreach (array_slice($missingOldIds, 0, 80, true) as $oldId => $info) {
    echo "old_id={$oldId} | ref={$info['ref']} | type={$info['type']} | name={$info['name']}" . PHP_EOL;
}

titleLine('Conclusión rápida');

if (count($duplicateRefs) > 0) {
    echo "Hay referencias repetidas. El importador V1 buscaba por internal_reference, por eso pudo actualizar productos en lugar de crear todos." . PHP_EOL;
}

if (count($deletedRows) > 0) {
    echo "Hay productos con deleted_at; esos se omitieron por seguridad." . PHP_EOL;
}

echo "Para recuperar faltantes hay que importar por old_product_id y, si la referencia se repite, generar una referencia única tipo REF-OLDID." . PHP_EOL;
