<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$companyId = (int) (getenv('BEXIA_COMPANY_ID') ?: 3);
$apply = getenv('BEXIA_APPLY') === '1';
$sqlFile = getenv('BEXIA_SQL') ?: storage_path('app/imports/papelon/categories.sql');

function titleLine(string $title): void
{
    echo PHP_EOL;
    echo "======================================" . PHP_EOL;
    echo $title . PHP_EOL;
    echo "======================================" . PHP_EOL;
}

function requireTable(string $table): void
{
    if (! Schema::hasTable($table)) {
        throw new RuntimeException("Falta tabla requerida: {$table}");
    }
}

function hasColumn(string $table, string $column): bool
{
    return Schema::hasColumn($table, $column);
}

function categoryCode(int $oldId): string
{
    return 'CAT-' . $oldId;
}

function parseSqlTuples(string $sql): array
{
    preg_match_all('/INSERT INTO\s+`categories`.*?VALUES\s*(.*?);/is', $sql, $matches);

    $tuples = [];

    foreach ($matches[1] ?? [] as $body) {
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
            $values[] = normalizeSqlValue($token);
            $token = '';
            continue;
        }

        $token .= $char;
    }

    $values[] = normalizeSqlValue($token);

    return $values;
}

function normalizeSqlValue(string $value): mixed
{
    $value = trim($value);

    if (strcasecmp($value, 'NULL') === 0) {
        return null;
    }

    if ($value === '') {
        return '';
    }

    if (is_numeric($value)) {
        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    return $value;
}

function oldPath(int $oldId, array $rowsById): string
{
    $parts = [];
    $current = $rowsById[$oldId] ?? null;
    $safe = 0;

    while ($current && $safe < 30) {
        array_unshift($parts, $current['name']);

        $parentId = (int) ($current['parent_id'] ?? 0);

        if ($parentId <= 0) {
            break;
        }

        $current = $rowsById[$parentId] ?? null;
        $safe++;
    }

    return implode(' / ', $parts);
}

function oldLevel(int $oldId, array $rowsById): int
{
    $level = 0;
    $current = $rowsById[$oldId] ?? null;
    $safe = 0;

    while ($current && $safe < 30) {
        $parentId = (int) ($current['parent_id'] ?? 0);

        if ($parentId <= 0) {
            break;
        }

        $level++;
        $current = $rowsById[$parentId] ?? null;
        $safe++;
    }

    return $level;
}

titleLine('Importar categorías Papelón V2');

echo "Empresa destino: {$companyId}" . PHP_EOL;
echo "Modo APPLY=" . ($apply ? '1 aplica cambios' : '0 vista previa') . PHP_EOL;
echo "Archivo SQL: {$sqlFile}" . PHP_EOL;
echo "Código final: CAT-{id}" . PHP_EOL;

if (! file_exists($sqlFile)) {
    throw new RuntimeException("No existe archivo SQL: {$sqlFile}");
}

requireTable('companies');
requireTable('product_categories');

$company = DB::table('companies')->where('id', $companyId)->first();

if (! $company) {
    throw new RuntimeException("No existe company_id {$companyId}");
}

$sql = file_get_contents($sqlFile);
$tuples = parseSqlTuples($sql);

$rows = [];

foreach ($tuples as $tuple) {
    $values = parseSqlTuple($tuple);

    if (count($values) < 12) {
        continue;
    }

    $rows[] = [
        'id' => (int) $values[0],
        'name' => trim((string) $values[1]),
        'business_id' => $values[2],
        'short_code' => $values[3],
        'parent_id' => (int) $values[4],
        'created_by' => $values[5],
        'category_type' => $values[6],
        'description' => $values[7],
        'slug' => $values[8],
        'deleted_at' => $values[9],
        'created_at' => $values[10],
        'updated_at' => $values[11],
    ];
}

$allRows = $rows;

$rows = array_values(array_filter($rows, function (array $row): bool {
    return ($row['category_type'] === null || $row['category_type'] === 'product')
        && $row['deleted_at'] === null
        && $row['name'] !== '';
}));

$rowsById = [];

foreach ($rows as $row) {
    $rowsById[(int) $row['id']] = $row;
}

$roots = array_values(array_filter($rows, fn ($row) => (int) $row['parent_id'] === 0));
$children = array_values(array_filter($rows, fn ($row) => (int) $row['parent_id'] !== 0));
$deleted = array_values(array_filter($allRows, fn ($row) => $row['deleted_at'] !== null));

titleLine('Resumen SQL');

echo 'Total categorías SQL: ' . count($allRows) . PHP_EOL;
echo 'Importables activas: ' . count($rows) . PHP_EOL;
echo 'Raíz: ' . count($roots) . PHP_EOL;
echo 'Subcategorías: ' . count($children) . PHP_EOL;
echo 'Omitidas por deleted_at: ' . count($deleted) . PHP_EOL;

titleLine('Vista previa');

foreach ($rows as $row) {
    echo sprintf(
        "%s | old_id=%s | parent_old=%s | level=%s | path=%s",
        categoryCode((int) $row['id']),
        $row['id'],
        $row['parent_id'],
        oldLevel((int) $row['id'], $rowsById),
        oldPath((int) $row['id'], $rowsById)
    ) . PHP_EOL;
}

if (! $apply) {
    titleLine('Vista previa terminada');
    echo "No se aplicaron cambios." . PHP_EOL;
    echo "Para aplicar ejecuta:" . PHP_EOL;
    echo "BEXIA_APPLY=1 ./importar_categorias_papelon_empresa3_v2.sh" . PHP_EOL;
    return;
}

titleLine('Aplicando importación');

DB::transaction(function () use ($rows, $rowsById, $companyId): void {
    $map = [];
    $now = now();

    /*
     * Si por un intento anterior quedaron códigos OLD-{id}, los renombramos.
     */
    DB::table('product_categories')
        ->where('company_id', $companyId)
        ->where('code', 'like', 'OLD-%')
        ->orderBy('id')
        ->get()
        ->each(function ($category) use ($companyId): void {
            $newCode = preg_replace('/^OLD-/', 'CAT-', $category->code);

            if (! $newCode || $newCode === $category->code) {
                return;
            }

            $exists = DB::table('product_categories')
                ->where('company_id', $companyId)
                ->where('code', $newCode)
                ->exists();

            if ($exists) {
                echo "WARN: No se renombró {$category->code} porque ya existe {$newCode}" . PHP_EOL;
                return;
            }

            DB::table('product_categories')
                ->where('id', $category->id)
                ->update([
                    'code' => $newCode,
                    'updated_at' => now(),
                ]);

            echo "Renombrada: {$category->code} => {$newCode}" . PHP_EOL;
        });

    foreach ($rows as $index => $row) {
        $oldId = (int) $row['id'];
        $code = categoryCode($oldId);

        $data = [
            'company_id' => $companyId,
            'code' => $code,
            'name' => $row['name'],
            'parent_id' => null,
            'updated_at' => $now,
        ];

        if (hasColumn('product_categories', 'full_path')) {
            $data['full_path'] = oldPath($oldId, $rowsById);
        }

        if (hasColumn('product_categories', 'level')) {
            $data['level'] = oldLevel($oldId, $rowsById);
        }

        if (hasColumn('product_categories', 'sort_order')) {
            $data['sort_order'] = $index + 1;
        }

        if (hasColumn('product_categories', 'description')) {
            $data['description'] = $row['description'];
        }

        if (hasColumn('product_categories', 'is_active')) {
            $data['is_active'] = true;
        }

        $existing = DB::table('product_categories')
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->first();

        if ($existing) {
            DB::table('product_categories')->where('id', $existing->id)->update($data);
            $map[$oldId] = $existing->id;

            echo "Actualizada: {$code} => bexia {$existing->id} {$row['name']}" . PHP_EOL;
            continue;
        }

        $data['created_at'] = $now;

        $newId = DB::table('product_categories')->insertGetId($data);
        $map[$oldId] = $newId;

        echo "Creada: {$code} => bexia {$newId} {$row['name']}" . PHP_EOL;
    }

    foreach ($rows as $row) {
        $oldId = (int) $row['id'];
        $oldParentId = (int) $row['parent_id'];

        if ($oldParentId <= 0) {
            continue;
        }

        if (! isset($map[$oldId], $map[$oldParentId])) {
            echo "WARN: No se pudo asignar padre de CAT-{$oldId}. Padre viejo faltante: {$oldParentId}" . PHP_EOL;
            continue;
        }

        DB::table('product_categories')
            ->where('id', $map[$oldId])
            ->update([
                'parent_id' => $map[$oldParentId],
                'updated_at' => $now,
            ]);

        echo "Padre asignado: CAT-{$oldId} => CAT-{$oldParentId}" . PHP_EOL;
    }
});

titleLine('Resultado final');

DB::table('product_categories')
    ->where('company_id', $companyId)
    ->where('code', 'like', 'CAT-%')
    ->select('id', 'parent_id', 'code', 'name', 'full_path', 'level', 'sort_order', 'is_active')
    ->orderBy('level')
    ->orderBy('full_path')
    ->get()
    ->each(fn ($row) => dump((array) $row));

echo PHP_EOL . 'Total CAT importadas: ' . DB::table('product_categories')
    ->where('company_id', $companyId)
    ->where('code', 'like', 'CAT-%')
    ->count() . PHP_EOL;
