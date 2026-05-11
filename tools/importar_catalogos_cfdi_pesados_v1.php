<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$relativePath = 'storage/app/imports/sat/catCFDI_V_4_20260422.xlsx';
$path = base_path($relativePath);

if (! file_exists($path)) {
    throw new RuntimeException("No existe {$relativePath}");
}

foreach (['sat_billing_catalogs', 'sat_billing_catalog_items'] as $table) {
    if (! Schema::hasTable($table)) {
        throw new RuntimeException("No existe la tabla {$table}. Ejecuta primero el importador genérico.");
    }
}

if (! class_exists(ZipArchive::class)) {
    throw new RuntimeException('ZipArchive no está disponible.');
}

if (! class_exists(XMLReader::class)) {
    throw new RuntimeException('XMLReader no está disponible.');
}

function cfdi_heavy_clean(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim($value);

    return $value === '' ? null : $value;
}

function cfdi_heavy_text_from_xml(SimpleXMLElement $node): string
{
    $parts = [];

    foreach ($node->xpath('.//*[local-name()="t"]') ?: [] as $t) {
        $parts[] = (string) $t;
    }

    return $parts === [] ? (string) $node : implode('', $parts);
}

function cfdi_heavy_col(string $cellRef): string
{
    return preg_replace('/[^A-Z]/', '', strtoupper($cellRef));
}

function cfdi_heavy_normalize_header(?string $value): string
{
    $value = mb_strtolower(trim((string) $value));
    $value = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $value);
    $value = preg_replace('/[^a-z0-9]+/u', '_', $value);

    return trim($value, '_');
}

function cfdi_heavy_normalize_key(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/(_parte_\d+|_part_\d+|_\d+)$/i', '', $value);
    $value = preg_replace('/^c_/i', '', $value);

    return trim(mb_strtolower(Str::snake($value)), '_');
}

function cfdi_heavy_catalog_name(string $key): string
{
    return match ($key) {
        'codigo_postal' => 'Código postal',
        'colonia' => 'Colonia',
        'localidad' => 'Localidad',
        'municipio' => 'Municipio',
        default => Str::headline(str_replace('_', ' ', $key)),
    };
}

function cfdi_heavy_code_width(string $catalogKey): ?int
{
    return match ($catalogKey) {
        'codigo_postal' => 5,
        'colonia' => 4,
        'localidad' => 2,
        'municipio' => 3,
        default => null,
    };
}

function cfdi_heavy_format_code(?string $code, string $catalogKey): ?string
{
    $code = cfdi_heavy_clean($code);

    if ($code === null) {
        return null;
    }

    if (preg_match('/^\d+(\.0+)?$/', $code)) {
        $code = (string) (int) round((float) $code);
    }

    $width = cfdi_heavy_code_width($catalogKey);

    if ($width && ctype_digit($code) && strlen($code) < $width) {
        return str_pad($code, $width, '0', STR_PAD_LEFT);
    }

    return $code;
}

function cfdi_heavy_date(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        $days = (int) floor((float) $value);

        if ($days < 20000 || $days > 90000) {
            return null;
        }

        return now()->setDate(1899, 12, 30)->startOfDay()->addDays($days)->toDateString();
    }

    try {
        return \Carbon\Carbon::parse((string) $value)->toDateString();
    } catch (Throwable $e) {
        return null;
    }
}

function cfdi_heavy_is_header_or_empty(?string $code): bool
{
    if ($code === null || trim($code) === '') {
        return true;
    }

    $v = mb_strtolower(trim($code));

    return str_starts_with($v, 'c_')
        || str_contains($v, 'codigo')
        || str_contains($v, 'código')
        || str_contains($v, 'descripcion')
        || str_contains($v, 'descripción')
        || str_contains($v, 'fecha');
}

function cfdi_heavy_find_col(array $headers, array $needles): ?string
{
    foreach ($headers as $col => $header) {
        $normalized = cfdi_heavy_normalize_header($header);

        foreach ($needles as $needle) {
            if (str_contains($normalized, $needle)) {
                return $col;
            }
        }
    }

    return null;
}

function cfdi_heavy_row_from_xml(string $rowXml, callable $sharedString): array
{
    $row = simplexml_load_string($rowXml);

    if (! $row) {
        return [];
    }

    $cells = [];

    foreach ($row->c as $cell) {
        $ref = (string) ($cell['r'] ?? '');
        $col = cfdi_heavy_col($ref);
        $type = (string) ($cell['t'] ?? '');

        $value = null;

        if ($type === 's') {
            $raw = isset($cell->v) ? (string) $cell->v : null;
            $value = $raw !== null ? $sharedString((int) $raw) : null;
        } elseif ($type === 'inlineStr') {
            $value = cfdi_heavy_text_from_xml($cell);
        } else {
            $value = isset($cell->v) ? (string) $cell->v : null;
        }

        $cells[$col] = cfdi_heavy_clean($value);
    }

    return $cells;
}

function cfdi_heavy_zip_uri(string $xlsxPath, string $entryPath): string
{
    return 'zip://' . realpath($xlsxPath) . '#' . ltrim($entryPath, '/');
}

function cfdi_heavy_get_sheets(string $xlsxPath): array
{
    $zip = new ZipArchive();

    if ($zip->open($xlsxPath) !== true) {
        throw new RuntimeException('No se pudo abrir XLSX.');
    }

    $workbookXml = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $relsXml = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));

    $rels = [];

    foreach ($relsXml->Relationship as $rel) {
        $id = (string) $rel['Id'];
        $target = (string) $rel['Target'];

        if (! str_starts_with($target, 'xl/')) {
            $target = 'xl/' . ltrim($target, '/');
        }

        $rels[$id] = $target;
    }

    $sheets = [];

    foreach ($workbookXml->sheets->sheet as $sheet) {
        $name = (string) $sheet['name'];
        $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];

        if (isset($rels[$rid])) {
            $sheets[] = [
                'name' => $name,
                'path' => $rels[$rid],
                'key' => cfdi_heavy_normalize_key($name),
            ];
        }
    }

    $zip->close();

    return $sheets;
}

function cfdi_heavy_load_shared_strings(string $xlsxPath): array
{
    $uri = cfdi_heavy_zip_uri($xlsxPath, 'xl/sharedStrings.xml');

    $reader = new XMLReader();

    if (! $reader->open($uri)) {
        return [];
    }

    $strings = [];
    $idx = 0;

    echo "Cargando sharedStrings..." . PHP_EOL;

    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
            $xml = $reader->readOuterXML();
            $node = simplexml_load_string($xml);
            $strings[$idx] = $node ? cfdi_heavy_text_from_xml($node) : '';
            $idx++;

            if ($idx % 50000 === 0) {
                echo "sharedStrings: {$idx}" . PHP_EOL;
            }
        }
    }

    $reader->close();

    echo "sharedStrings total: " . count($strings) . PHP_EOL;

    return $strings;
}

function cfdi_heavy_find_header(string $xlsxPath, string $sheetPath, callable $sharedString, string $catalogKey): ?array
{
    $reader = new XMLReader();
    $uri = cfdi_heavy_zip_uri($xlsxPath, $sheetPath);

    if (! $reader->open($uri)) {
        return null;
    }

    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
            continue;
        }

        $rowNumber = (int) ($reader->getAttribute('r') ?: 0);
        $cells = cfdi_heavy_row_from_xml($reader->readOuterXML(), $sharedString);

        if ($cells === []) {
            continue;
        }

        $joined = mb_strtolower(implode(' ', array_filter($cells)));
        $normalizedJoined = cfdi_heavy_normalize_header($joined);

        $hasExpectedHeader = match ($catalogKey) {
            'codigo_postal' => str_contains($normalizedJoined, 'codigo_postal') || str_contains($normalizedJoined, 'codigopostal'),
            'colonia' => str_contains($normalizedJoined, 'colonia'),
            'localidad' => str_contains($normalizedJoined, 'localidad'),
            'municipio' => str_contains($normalizedJoined, 'municipio'),
            default => false,
        };

        $hasCodeStyle = str_contains($joined, 'c_');

        if ($hasExpectedHeader || $hasCodeStyle) {
            $reader->close();

            return [
                'row_number' => $rowNumber,
                'headers' => $cells,
            ];
        }
    }

    $reader->close();

    return null;
}

function cfdi_heavy_select_code_col(array $headers, string $catalogKey): ?string
{
    $needles = match ($catalogKey) {
        'codigo_postal' => ['c_codigopostal', 'codigo_postal', 'codigopostal'],
        'colonia' => ['c_colonia', 'colonia'],
        'localidad' => ['c_localidad', 'localidad'],
        'municipio' => ['c_municipio', 'municipio'],
        default => [],
    };

    $col = cfdi_heavy_find_col($headers, $needles);

    return $col ?: array_key_first($headers);
}

$heavyKeys = [
    'codigo_postal',
    'colonia',
    'localidad',
    'municipio',
];

$sheets = collect(cfdi_heavy_get_sheets($path))
    ->filter(fn ($sheet) => in_array($sheet['key'], $heavyKeys, true))
    ->values()
    ->all();

echo "Hojas pesadas detectadas: " . count($sheets) . PHP_EOL;

foreach ($sheets as $sheet) {
    echo "- {$sheet['name']} => {$sheet['key']}" . PHP_EOL;
}

$sharedStrings = cfdi_heavy_load_shared_strings($path);

$sharedString = function (int $idx) use (&$sharedStrings): ?string {
    return $sharedStrings[$idx] ?? null;
};

$totalInserted = 0;
$totalSkipped = 0;

foreach ($sheets as $sheetInfo) {
    $sheetName = $sheetInfo['name'];
    $sheetPath = $sheetInfo['path'];
    $catalogKey = $sheetInfo['key'];
    $catalogName = cfdi_heavy_catalog_name($catalogKey);

    echo PHP_EOL . "======================================" . PHP_EOL;
    echo "Importando {$sheetName} => {$catalogKey}" . PHP_EOL;
    echo "======================================" . PHP_EOL;

    $headerInfo = cfdi_heavy_find_header($path, $sheetPath, $sharedString, $catalogKey);

    if (! $headerInfo) {
        echo "WARN: no se encontró encabezado para {$sheetName}. Omitida." . PHP_EOL;
        continue;
    }

    $headerRow = $headerInfo['row_number'];
    $headers = $headerInfo['headers'];

    $codeCol = cfdi_heavy_select_code_col($headers, $catalogKey);
    $descriptionCol = cfdi_heavy_find_col($headers, ['descripcion', 'description', 'nombre_asentamiento', 'asentamiento', 'nombre']);
    $validFromCol = cfdi_heavy_find_col($headers, ['inicio']);
    $validToCol = cfdi_heavy_find_col($headers, ['fin']);

    $estadoCol = cfdi_heavy_find_col($headers, ['c_estado', 'estado']);
    $municipioCol = cfdi_heavy_find_col($headers, ['c_municipio', 'municipio']);
    $localidadCol = cfdi_heavy_find_col($headers, ['c_localidad', 'localidad']);
    $codigoPostalCol = cfdi_heavy_find_col($headers, ['c_codigopostal', 'codigo_postal', 'codigopostal']);

    if (! $codeCol) {
        echo "WARN: no se encontró columna código en {$sheetName}. Omitida." . PHP_EOL;
        continue;
    }

    DB::table('sat_billing_catalogs')->updateOrInsert(
        ['catalog_key' => $catalogKey],
        [
            'name' => $catalogName,
            'description' => 'Catálogo CFDI pesado importado desde hoja(s) SAT.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $catalog = DB::table('sat_billing_catalogs')
        ->where('catalog_key', $catalogKey)
        ->first();

    DB::table('sat_billing_catalog_items')
        ->where('source_sheet', $sheetName)
        ->delete();

    $reader = new XMLReader();
    $uri = cfdi_heavy_zip_uri($path, $sheetPath);

    if (! $reader->open($uri)) {
        echo "WARN: no se pudo abrir {$sheetName}." . PHP_EOL;
        continue;
    }

    $processed = 0;
    $inserted = 0;
    $skipped = 0;
    $batch = [];

    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
            continue;
        }

        $rowNumber = (int) ($reader->getAttribute('r') ?: 0);

        if ($rowNumber <= $headerRow) {
            continue;
        }

        $processed++;

        $cells = cfdi_heavy_row_from_xml($reader->readOuterXML(), $sharedString);

        if ($cells === []) {
            $skipped++;
            continue;
        }

        $code = cfdi_heavy_format_code($cells[$codeCol] ?? null, $catalogKey);

        if (cfdi_heavy_is_header_or_empty($code)) {
            $skipped++;
            continue;
        }

        $description = $descriptionCol ? cfdi_heavy_clean($cells[$descriptionCol] ?? null) : null;

        if ($catalogKey === 'codigo_postal') {
            $descriptionParts = array_filter([
                'CP ' . $code,
                $estadoCol ? 'Estado ' . cfdi_heavy_clean($cells[$estadoCol] ?? '') : null,
                $municipioCol ? 'Municipio ' . cfdi_heavy_clean($cells[$municipioCol] ?? '') : null,
                $localidadCol ? 'Localidad ' . cfdi_heavy_clean($cells[$localidadCol] ?? '') : null,
            ]);

            $description = implode(' / ', $descriptionParts);
        }

        if ($description === null) {
            $description = $code;
        }

        $validFrom = $validFromCol ? cfdi_heavy_date($cells[$validFromCol] ?? null) : null;
        $validTo = $validToCol ? cfdi_heavy_date($cells[$validToCol] ?? null) : null;

        $isActive = true;

        if ($validTo) {
            $isActive = \Carbon\Carbon::parse($validTo)->endOfDay()->greaterThanOrEqualTo(now());
        }

        $externalKey = sha1($catalogKey . '|' . $sheetName . '|' . $rowNumber . '|' . $code . '|' . json_encode($cells, JSON_UNESCAPED_UNICODE));

        $batch[] = [
            'catalog_id' => $catalog->id,
            'catalog_key' => $catalogKey,
            'source_sheet' => $sheetName,
            'source_row' => $rowNumber,
            'code' => $code,
            'name' => $description,
            'description' => $description,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'extra_attributes' => json_encode([
                'headers' => $headers,
                'values' => $cells,
                'codigo_postal_col' => $codigoPostalCol,
                'estado_col' => $estadoCol,
                'municipio_col' => $municipioCol,
                'localidad_col' => $localidadCol,
            ], JSON_UNESCAPED_UNICODE),
            'external_key' => $externalKey,
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (count($batch) >= 1000) {
            DB::table('sat_billing_catalog_items')->insert($batch);
            $inserted += count($batch);
            $batch = [];
        }

        if ($processed % 5000 === 0) {
            echo "{$sheetName}: procesados {$processed} | insertados {$inserted} | omitidos {$skipped}" . PHP_EOL;
        }
    }

    $reader->close();

    if ($batch !== []) {
        DB::table('sat_billing_catalog_items')->insert($batch);
        $inserted += count($batch);
    }

    $totalInserted += $inserted;
    $totalSkipped += $skipped;

    echo "OK {$sheetName}: procesados {$processed}, insertados {$inserted}, omitidos {$skipped}" . PHP_EOL;
}

echo PHP_EOL . "======================================" . PHP_EOL;
echo "RESUMEN PESADOS" . PHP_EOL;
echo "======================================" . PHP_EOL;

dump([
    'insertados_esta_corrida' => $totalInserted,
    'omitidos_esta_corrida' => $totalSkipped,
    'items_total' => DB::table('sat_billing_catalog_items')->count(),
]);

DB::table('sat_billing_catalog_items')
    ->whereIn('catalog_key', ['codigo_postal', 'colonia', 'localidad', 'municipio'])
    ->select('catalog_key', DB::raw('count(*) as total'))
    ->groupBy('catalog_key')
    ->orderBy('catalog_key')
    ->get()
    ->each(fn ($r) => dump((array) $r));
