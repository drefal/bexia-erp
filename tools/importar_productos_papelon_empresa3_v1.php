<?php

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

$companyId = (int) (getenv('BEXIA_COMPANY_ID') ?: 3);
$apply = getenv('BEXIA_APPLY') === '1';
$sqlFile = getenv('BEXIA_SQL') ?: storage_path('app/imports/papelon/products.sql');
$imagesDir = getenv('BEXIA_IMAGES_DIR') ?: storage_path('app/imports/papelon/images_extracted');

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

function requireColumn(string $table, string $column): void
{
    if (! Schema::hasColumn($table, $column)) {
        throw new RuntimeException("Falta columna requerida: {$table}.{$column}");
    }
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

function toBool(mixed $value): bool
{
    return toInt($value) === 1;
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

function isBarcodeLike(?string $sku): bool
{
    $sku = trim((string) $sku);

    return preg_match('/^\d{8,14}$/', $sku) === 1;
}

function safeFilename(int $oldId, string $filename): string
{
    $filename = trim($filename);
    $info = pathinfo($filename);

    $base = $info['filename'] ?? ('producto_' . $oldId);
    $ext = strtolower($info['extension'] ?? 'jpg');

    $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base) ?: $base;
    $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', $base);
    $base = trim($base, '_');

    if ($base === '') {
        $base = 'producto';
    }

    return $oldId . '_' . $base . '.' . $ext;
}

function extractSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inString = false;
    $escape = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $buffer .= $char;

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
                if (($sql[$i + 1] ?? null) === "'") {
                    $buffer .= $sql[$i + 1];
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

        if ($char === ';') {
            $statements[] = trim($buffer);
            $buffer = '';
        }
    }

    $buffer = trim($buffer);

    if ($buffer !== '') {
        $statements[] = $buffer;
    }

    return $statements;
}

function parseInsertRows(string $sql, string $table): array
{
    $rows = [];

    foreach (extractSqlStatements($sql) as $statement) {
        if (! preg_match('/^\s*INSERT INTO\s+`' . preg_quote($table, '/') . '`\s*\((.*?)\)\s*VALUES\s*(.*)\s*;?\s*$/is', $statement, $match)) {
            continue;
        }

        preg_match_all('/`([^`]+)`/', $match[1], $columnMatches);
        $columns = $columnMatches[1] ?? [];
        $body = $match[2];

        foreach (parseSqlTuples($body) as $tuple) {
            $values = parseSqlTuple($tuple);

            if (count($values) !== count($columns)) {
                echo "WARN {$table}: fila con columnas distintas. columns=" . count($columns) . " values=" . count($values) . PHP_EOL;
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

function buildImageMap(string $imagesDir): array
{
    $map = [];

    if (! is_dir($imagesDir)) {
        return $map;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($imagesDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $ext = strtolower($file->getExtension());

        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            continue;
        }

        $map[strtolower($file->getFilename())] = $file->getPathname();
    }

    return $map;
}

function categoryIdForRow(array $row, int $companyId): ?int
{
    $oldCategoryId = toInt($row['category_id'] ?? 0);
    $oldSubCategoryId = toInt($row['sub_category_id'] ?? 0);

    $targetOldId = $oldSubCategoryId > 0 ? $oldSubCategoryId : $oldCategoryId;

    if ($targetOldId > 0) {
        $category = DB::table('product_categories')
            ->where('company_id', $companyId)
            ->where('code', 'CAT-' . $targetOldId)
            ->first();

        if ($category) {
            return (int) $category->id;
        }
    }

    return DB::table('product_categories')
        ->where('company_id', $companyId)
        ->where('code', 'GENERAL')
        ->value('id');
}

function unitIdForCompany(int $companyId): ?int
{
    return DB::table('inventory_units')
        ->where('company_id', $companyId)
        ->where('code', 'PZA')
        ->value('id')
        ?: DB::table('inventory_units')
            ->where('company_id', $companyId)
            ->value('id');
}

function findExistingProduct(int $companyId, string $internalReference): ?object
{
    return DB::table('products')
        ->where('company_id', $companyId)
        ->where('internal_reference', $internalReference)
        ->first();
}

titleLine('Importar productos Papelón');

echo "Empresa destino: {$companyId}" . PHP_EOL;
echo "Modo APPLY=" . ($apply ? '1 aplica cambios' : '0 vista previa') . PHP_EOL;
echo "SQL: {$sqlFile}" . PHP_EOL;
echo "Imágenes: {$imagesDir}" . PHP_EOL;

if (! file_exists($sqlFile)) {
    throw new RuntimeException("No existe SQL: {$sqlFile}");
}

requireTable('companies');
requireTable('products');
requireTable('product_categories');
requireTable('inventory_units');
requireTable('product_images');
requireColumn('products', 'image_path');
requireColumn('products', 'extra_attributes');

$company = DB::table('companies')->where('id', $companyId)->first();

if (! $company) {
    throw new RuntimeException("No existe company_id {$companyId}");
}

$sql = file_get_contents($sqlFile);
$rows = parseInsertRows($sql, 'products');
$imageMap = buildImageMap($imagesDir);
$unitId = unitIdForCompany($companyId);

$activeRows = array_values(array_filter($rows, function (array $row): bool {
    return empty($row['deleted_at']);
}));

$withImages = array_values(array_filter($activeRows, fn ($row) => ! empty(trim((string) ($row['image'] ?? '')))));
$stockable = array_values(array_filter($activeRows, fn ($row) => toBool($row['enable_stock'] ?? 0)));
$barcodeLike = array_values(array_filter($activeRows, fn ($row) => isBarcodeLike($row['sku'] ?? null)));

titleLine('Resumen preview');

echo 'Productos SQL: ' . count($rows) . PHP_EOL;
echo 'Productos activos importables: ' . count($activeRows) . PHP_EOL;
echo 'Con imagen en SQL: ' . count($withImages) . PHP_EOL;
echo 'Imágenes encontradas en ZIP: ' . count($imageMap) . PHP_EOL;
echo 'Con stock: ' . count($stockable) . PHP_EOL;
echo 'SKU con apariencia código de barras: ' . count($barcodeLike) . PHP_EOL;
echo 'Unidad interna destino id: ' . ($unitId ?: 'NO ENCONTRADA') . PHP_EOL;

titleLine('Primeros 25 productos como quedarían');

foreach (array_slice($activeRows, 0, 25) as $row) {
    $oldId = toInt($row['id'] ?? 0);
    $oldSku = trim((string) ($row['sku'] ?? ''));
    $internalReference = $oldSku !== '' ? $oldSku : 'IMP-' . $oldId;
    $barcode = isBarcodeLike($oldSku) ? $oldSku : null;
    $categoryId = categoryIdForRow($row, $companyId);
    $oldImage = trim((string) ($row['image'] ?? ''));
    $imageFound = $oldImage !== '' && isset($imageMap[strtolower(basename($oldImage))]);

    echo sprintf(
        "old_id=%s | ref=%s | sku=%s | cat_id=%s | image=%s | found=%s | name=%s",
        $oldId,
        $internalReference,
        $barcode ?: 'NULL',
        $categoryId ?: 'NULL',
        $oldImage ?: 'NULL',
        $imageFound ? 'YES' : 'NO',
        $row['name'] ?? ''
    ) . PHP_EOL;
}

if (! $apply) {
    titleLine('Vista previa terminada');
    echo "No se aplicaron cambios." . PHP_EOL;
    echo "Para aplicar ejecuta:" . PHP_EOL;
    echo "BEXIA_APPLY=1 ./importar_productos_papelon_empresa3_v1.sh" . PHP_EOL;
    return;
}

titleLine('Aplicando importación');

File::ensureDirectoryExists(storage_path('app/public/products/imports/papelon'), 0775, true);

$created = 0;
$updated = 0;
$imagesLinked = 0;
$imagesMissing = 0;
$skipped = 0;

DB::transaction(function () use (
    $activeRows,
    $companyId,
    $unitId,
    $imageMap,
    &$created,
    &$updated,
    &$imagesLinked,
    &$imagesMissing,
    &$skipped
): void {
    $now = now();

    foreach ($activeRows as $row) {
        $oldId = toInt($row['id'] ?? 0);

        if ($oldId <= 0) {
            $skipped++;
            continue;
        }

        $name = trim((string) ($row['name'] ?? ''));

        if ($name === '') {
            $skipped++;
            continue;
        }

        $oldSku = trim((string) ($row['sku'] ?? ''));
        $internalReference = $oldSku !== '' ? $oldSku : 'IMP-' . $oldId;
        $barcode = isBarcodeLike($oldSku) ? $oldSku : null;

        $categoryId = categoryIdForRow($row, $companyId);
        $enableStock = toBool($row['enable_stock'] ?? 0);
        $enableSerial = toBool($row['enable_sr_no'] ?? 0);
        $isInactive = toBool($row['is_inactive'] ?? 0);
        $notForSelling = toBool($row['not_for_selling'] ?? 0);

        $oldType = trim((string) ($row['type'] ?? ''));

        $extra = [
            'source' => 'papelon_import',
            'old_product_id' => $oldId,
            'old_type' => $oldType,
            'old_unit_id' => $row['unit_id'] ?? null,
            'old_brand_id' => $row['brand_id'] ?? null,
            'old_category_id' => $row['category_id'] ?? null,
            'old_sub_category_id' => $row['sub_category_id'] ?? null,
            'old_image' => $row['image'] ?? null,
            'alert_quantity' => $row['alert_quantity'] ?? null,
            'tax' => $row['tax'] ?? null,
            'tax_type' => $row['tax_type'] ?? null,
        ];

        $productData = [
            'company_id' => $companyId,
            'product_category_id' => $categoryId,
            'inventory_unit_id' => $unitId,
            'name' => $name,
            'internal_reference' => $internalReference,
            'sku' => $barcode,
            'barcode' => $barcode,
            'description' => cleanText($row['product_description'] ?? null),
            'product_type' => $enableStock ? 'stockable' : 'service',
            'tracking' => $enableSerial ? 'serial' : 'none',
            'costing_method' => 'average',
            'sale_price' => 0,
            'standard_cost' => 0,
            'purchase_price' => 0,
            'can_be_sold' => ! $notForSelling,
            'can_be_purchased' => true,
            'available_in_pos' => ! $notForSelling,
            'is_active' => ! $isInactive,
            'sat_unit_code' => $enableStock ? 'H87' : 'E48',
            'extra_attributes' => json_encode($extra, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ];

        $existing = findExistingProduct($companyId, $internalReference);

        if ($existing) {
            DB::table('products')->where('id', $existing->id)->update($productData);
            $productId = (int) $existing->id;
            $updated++;
        } else {
            $productData['created_at'] = $now;
            $productId = DB::table('products')->insertGetId($productData);
            $created++;
        }

        $oldImage = trim((string) ($row['image'] ?? ''));

        if ($oldImage === '') {
            continue;
        }

        $imageKey = strtolower(basename($oldImage));
        $sourcePath = $imageMap[$imageKey] ?? null;

        if (! $sourcePath || ! file_exists($sourcePath)) {
            $imagesMissing++;
            continue;
        }

        $safeName = safeFilename($oldId, basename($oldImage));
        $relativePath = 'products/imports/papelon/' . $safeName;
        $targetPath = storage_path('app/public/' . $relativePath);

        File::copy($sourcePath, $targetPath);
        @chmod($targetPath, 0664);

        DB::table('products')->where('id', $productId)->update([
            'image_path' => $relativePath,
            'updated_at' => $now,
        ]);

        $imageExists = DB::table('product_images')
            ->where('product_id', $productId)
            ->where('image_path', $relativePath)
            ->exists();

        if (! $imageExists) {
            DB::table('product_images')->insert([
                'company_id' => $companyId,
                'product_id' => $productId,
                'product_template_id' => null,
                'image_path' => $relativePath,
                'title' => $name,
                'alt_text' => $name,
                'is_primary' => true,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $imagesLinked++;
    }
});

titleLine('Resultado');

echo "Creados: {$created}" . PHP_EOL;
echo "Actualizados: {$updated}" . PHP_EOL;
echo "Omitidos: {$skipped}" . PHP_EOL;
echo "Imágenes ligadas: {$imagesLinked}" . PHP_EOL;
echo "Imágenes no encontradas: {$imagesMissing}" . PHP_EOL;
echo "Total productos empresa {$companyId}: " . DB::table('products')->where('company_id', $companyId)->count() . PHP_EOL;
echo "Total product_images empresa {$companyId}: " . DB::table('product_images')->where('company_id', $companyId)->count() . PHP_EOL;
