<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

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

function toDecimal(mixed $value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }

    return (float) $value;
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

function safeFilename(int $sourceId, string $filename): string
{
    $filename = trim($filename);
    $info = pathinfo($filename);

    $base = $info['filename'] ?? ('producto_' . $sourceId);
    $ext = strtolower($info['extension'] ?? 'jpg');

    $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base) ?: $base;
    $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', $base);
    $base = trim($base, '_');

    if ($base === '') {
        $base = 'producto';
    }

    return $sourceId . '_' . $base . '.' . $ext;
}

function parseInsertRows(string $sql, string $table): array
{
    /*
     * Parser específico para dumps phpMyAdmin donde:
     * - La línea INSERT trae las columnas.
     * - Cada registro inicia en una línea con "(".
     * Esto evita errores por entidades HTML con punto y coma dentro de textos.
     */
    $rows = [];
    $columns = [];
    $insideInsert = false;

    $lines = preg_split('/\R/', $sql);

    foreach ($lines as $line) {
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

        if (! $insideInsert) {
            continue;
        }

        if ($trim[0] !== '(') {
            continue;
        }

        $isLastTuple = str_ends_with($trim, ';');

        $tuple = preg_replace('/[;,]\s*$/', '', $trim);

        $values = parseSqlTuple($tuple);

        if (count($values) !== count($columns)) {
            echo "WARN {$table}: columnas=" . count($columns) . " valores=" . count($values) . " linea=" . substr($trim, 0, 80) . PHP_EOL;

            if ($isLastTuple) {
                $insideInsert = false;
                $columns = [];
            }

            continue;
        }

        $rows[] = array_combine($columns, $values);

        if ($isLastTuple) {
            $insideInsert = false;
            $columns = [];
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

function existingProductsBySource(int $companyId): array
{
    $map = [];

    DB::table('products')
        ->where('company_id', $companyId)
        ->select('id', 'internal_reference', 'extra_attributes')
        ->orderBy('id')
        ->get()
        ->each(function ($product) use (&$map): void {
            $extra = decodeExtra($product->extra_attributes);

            $sourceProductId = $extra['source_product_id'] ?? $extra['old_product_id'] ?? null;

            if ($sourceProductId !== null && (int) $sourceProductId > 0) {
                $map[(int) $sourceProductId] = $product;
            }
        });

    return $map;
}

function categoryIdForRow(array $row, int $companyId): ?int
{
    $sourceCategoryId = toInt($row['category_id'] ?? 0);
    $sourceSubCategoryId = toInt($row['sub_category_id'] ?? 0);

    $targetCategorySourceId = $sourceSubCategoryId > 0 ? $sourceSubCategoryId : $sourceCategoryId;

    if ($targetCategorySourceId > 0) {
        $category = DB::table('product_categories')
            ->where('company_id', $companyId)
            ->where('code', 'CAT-' . $targetCategorySourceId)
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

function filterColumns(string $table, array $data): array
{
    return array_filter(
        $data,
        fn ($value, $column) => Schema::hasColumn($table, $column),
        ARRAY_FILTER_USE_BOTH
    );
}

titleLine('Importar productos Papelón V1.3');

echo "Empresa destino: {$companyId}" . PHP_EOL;
echo "Modo APPLY=" . ($apply ? '1 aplica cambios' : '0 vista previa') . PHP_EOL;
echo "SQL: {$sqlFile}" . PHP_EOL;
echo "Imágenes: {$imagesDir}" . PHP_EOL;
echo "Metadata de migración: source_product_id" . PHP_EOL;

if (! file_exists($sqlFile)) {
    throw new RuntimeException("No existe SQL: {$sqlFile}");
}

foreach (['companies', 'products', 'product_categories', 'inventory_units', 'product_images'] as $table) {
    if (! Schema::hasTable($table)) {
        throw new RuntimeException("Falta tabla requerida: {$table}");
    }
}

foreach (['image_path', 'extra_attributes'] as $column) {
    if (! Schema::hasColumn('products', $column)) {
        throw new RuntimeException("Falta columna requerida: products.{$column}");
    }
}

$company = DB::table('companies')->where('id', $companyId)->first();

if (! $company) {
    throw new RuntimeException("No existe company_id {$companyId}");
}

$sql = file_get_contents($sqlFile);
$rows = parseInsertRows($sql, 'products');
$imageMap = buildImageMap($imagesDir);
$unitId = unitIdForCompany($companyId);
$existingBySource = existingProductsBySource($companyId);

$activeRows = array_values(array_filter($rows, fn (array $row): bool => empty($row['deleted_at'])));
$validRows = array_values(array_filter($activeRows, fn (array $row): bool => toInt($row['id'] ?? 0) > 0 && trim((string) ($row['name'] ?? '')) !== ''));

$withImages = array_values(array_filter($validRows, fn ($row) => ! empty(trim((string) ($row['image'] ?? '')))));
$stockable = array_values(array_filter($validRows, fn ($row) => toBool($row['enable_stock'] ?? 0)));
$barcodeLike = array_values(array_filter($validRows, fn ($row) => isBarcodeLike($row['sku'] ?? null)));

$toCreate = 0;
$toUpdate = 0;

foreach ($validRows as $row) {
    $sourceProductId = toInt($row['id'] ?? 0);

    if (isset($existingBySource[$sourceProductId])) {
        $toUpdate++;
    } else {
        $toCreate++;
    }
}

titleLine('Resumen preview');

echo 'Productos SQL: ' . count($rows) . PHP_EOL;
echo 'Productos activos importables: ' . count($validRows) . PHP_EOL;
echo 'Ya existentes por source_product_id: ' . $toUpdate . PHP_EOL;
echo 'Por crear: ' . $toCreate . PHP_EOL;
echo 'Con imagen en SQL: ' . count($withImages) . PHP_EOL;
echo 'Imágenes encontradas en ZIP: ' . count($imageMap) . PHP_EOL;
echo 'Con stock: ' . count($stockable) . PHP_EOL;
echo 'SKU con apariencia código de barras: ' . count($barcodeLike) . PHP_EOL;
echo 'Unidad interna destino id: ' . ($unitId ?: 'NO ENCONTRADA') . PHP_EOL;

titleLine('Primeros 30 productos como quedarían');

foreach (array_slice($validRows, 0, 30) as $row) {
    $sourceProductId = toInt($row['id'] ?? 0);
    $sourceSku = trim((string) ($row['sku'] ?? ''));
    $internalReference = $sourceSku !== '' ? $sourceSku : 'IMP-' . $sourceProductId;
    $barcode = isBarcodeLike($sourceSku) ? $sourceSku : null;
    $categoryId = categoryIdForRow($row, $companyId);
    $sourceImage = trim((string) ($row['image'] ?? ''));
    $imageFound = $sourceImage !== '' && isset($imageMap[strtolower(basename($sourceImage))]);

    echo sprintf(
        "source_id=%s | ref=%s | sku=%s | cat_id=%s | image=%s | found=%s | name=%s",
        $sourceProductId,
        $internalReference,
        $barcode ?: 'NULL',
        $categoryId ?: 'NULL',
        $sourceImage ?: 'NULL',
        $imageFound ? 'YES' : 'NO',
        $row['name'] ?? ''
    ) . PHP_EOL;
}

if (! $apply) {
    titleLine('Vista previa terminada');
    echo "No se aplicaron cambios." . PHP_EOL;
    echo "Para aplicar ejecuta:" . PHP_EOL;
    echo "BEXIA_APPLY=1 ./importar_productos_papelon_empresa3_v1_3.sh" . PHP_EOL;
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
    $validRows,
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
    $existingBySource = existingProductsBySource($companyId);

    foreach ($validRows as $row) {
        $sourceProductId = toInt($row['id'] ?? 0);

        if ($sourceProductId <= 0) {
            $skipped++;
            continue;
        }

        $name = trim((string) ($row['name'] ?? ''));

        if ($name === '') {
            $skipped++;
            continue;
        }

        $sourceSku = trim((string) ($row['sku'] ?? ''));
        $internalReference = $sourceSku !== '' ? $sourceSku : 'IMP-' . $sourceProductId;
        $barcode = isBarcodeLike($sourceSku) ? $sourceSku : null;

        $categoryId = categoryIdForRow($row, $companyId);
        $enableStock = toBool($row['enable_stock'] ?? 0);
        $enableSerial = toBool($row['enable_sr_no'] ?? 0);
        $isInactive = toBool($row['is_inactive'] ?? 0);
        $notForSelling = toBool($row['not_for_selling'] ?? 0);

        $extra = [
            'import_source' => 'papelon',
            'source_product_id' => $sourceProductId,
            'source_type' => trim((string) ($row['type'] ?? '')),
            'source_unit_id' => $row['unit_id'] ?? null,
            'source_brand_id' => $row['brand_id'] ?? null,
            'source_category_id' => $row['category_id'] ?? null,
            'source_sub_category_id' => $row['sub_category_id'] ?? null,
            'source_image' => $row['image'] ?? null,
            'alert_quantity' => $row['alert_quantity'] ?? null,
            'tax' => $row['tax'] ?? null,
            'tax_type' => $row['tax_type'] ?? null,
        ];

        $productData = filterColumns('products', [
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
        ]);

        $existing = $existingBySource[$sourceProductId] ?? null;

        if ($existing) {
            DB::table('products')->where('id', $existing->id)->update($productData);
            $productId = (int) $existing->id;
            $updated++;
        } else {
            $productData['created_at'] = $now;
            $productId = DB::table('products')->insertGetId($productData);
            $created++;
        }

        $sourceImage = trim((string) ($row['image'] ?? ''));

        if ($sourceImage === '') {
            continue;
        }

        $imageKey = strtolower(basename($sourceImage));
        $sourcePath = $imageMap[$imageKey] ?? null;

        if (! $sourcePath || ! file_exists($sourcePath)) {
            $imagesMissing++;
            continue;
        }

        $safeName = safeFilename($sourceProductId, basename($sourceImage));
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
