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
        throw new RuntimeException("No existe la tabla {$table}.");
    }
}

if (! class_exists(ZipArchive::class)) {
    throw new RuntimeException('ZipArchive no está disponible.');
}

function cfdi_v2_xml_text(SimpleXMLElement $node): string
{
    $parts = [];

    foreach ($node->xpath('.//*[local-name()="t"]') ?: [] as $t) {
        $parts[] = (string) $t;
    }

    return $parts === [] ? (string) $node : implode('', $parts);
}

function cfdi_v2_col(string $cellRef): string
{
    return preg_replace('/[^A-Z]/', '', strtoupper($cellRef));
}

function cfdi_v2_clean(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim($value);

    return $value === '' ? null : $value;
}

function cfdi_v2_normalize_key(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/(_parte_\d+|_part_\d+|_\d+)$/i', '', $value);
    $value = preg_replace('/^c_/i', '', $value);
    return trim(mb_strtolower(Str::snake($value)), '_');
}

function cfdi_v2_catalog_name(string $key): string
{
    return match ($key) {
        'forma_pago' => 'Forma de pago',
        'moneda' => 'Moneda',
        'tipo_de_comprobante' => 'Tipo de comprobante',
        'exportacion' => 'Exportación',
        'metodo_pago' => 'Método de pago',
        'periodicidad' => 'Periodicidad',
        'meses' => 'Meses',
        'objeto_imp' => 'Objeto de impuesto',
        'impuesto' => 'Impuesto',
        'tipo_factor' => 'Tipo factor',
        'tasa_o_cuota' => 'Tasa o cuota',
        'aduana' => 'Aduana',
        'num_pedimento_aduana' => 'Número de pedimento aduana',
        'patente_aduanal' => 'Patente aduanal',
        'estado' => 'Estado',
        'pais' => 'País',
        'uso_cfdi' => 'Uso CFDI',
        'regimen_fiscal' => 'Régimen fiscal',
        default => Str::headline(str_replace('_', ' ', $key)),
    };
}

function cfdi_v2_code_width(string $catalogKey): ?int
{
    return match ($catalogKey) {
        'forma_pago',
        'exportacion',
        'periodicidad',
        'meses',
        'aduana',
        'localidad',
        'objeto_imp' => 2,
        'impuesto',
        'municipio',
        'regimen_fiscal' => 3,
        'colonia',
        'patente_aduanal' => 4,
        'codigo_postal' => 5,
        default => null,
    };
}

function cfdi_v2_format_code(?string $code, string $catalogKey): ?string
{
    $code = cfdi_v2_clean($code);

    if ($code === null) {
        return null;
    }

    if (preg_match('/^\d+(\.0+)?$/', $code)) {
        $code = (string) (int) round((float) $code);
    }

    $width = cfdi_v2_code_width($catalogKey);

    if ($width && ctype_digit($code) && strlen($code) < $width) {
        return str_pad($code, $width, '0', STR_PAD_LEFT);
    }

    return $code;
}

function cfdi_v2_date(mixed $value): ?string
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

function cfdi_v2_is_header_or_empty(?string $code): bool
{
    if ($code === null || trim($code) === '') {
        return true;
    }

    $v = mb_strtolower(trim($code));

    return str_starts_with($v, 'c_')
        || str_contains($v, 'descripcion')
        || str_contains($v, 'descripción')
        || str_contains($v, 'fecha');
}

function cfdi_v2_find_col(array $headers, array $needles): ?string
{
    foreach ($headers as $col => $header) {
        $h = mb_strtolower(str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], (string) $header));

        foreach ($needles as $needle) {
            if (str_contains($h, $needle)) {
                return $col;
            }
        }
    }

    return null;
}

$skipKeys = [
    'codigo_postal',
    'colonia',
    'localidad',
    'municipio',
];

$skipSheets = [
    'c_ClaveProdServ',
    'c_ClaveUnidad',
];

$zip = new ZipArchive();

if ($zip->open($path) !== true) {
    throw new RuntimeException('No se pudo abrir el XLSX.');
}

$sharedStrings = [];
$sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

if ($sharedStringsXml !== false) {
    $shared = simplexml_load_string($sharedStringsXml);

    foreach ($shared->children() as $si) {
        $sharedStrings[] = cfdi_v2_xml_text($si);
    }
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
            'key' => cfdi_v2_normalize_key($name),
        ];
    }
}

echo "Hojas detectadas: " . count($sheets) . PHP_EOL;

$totalInserted = 0;
$totalSkipped = 0;

foreach ($sheets as $sheetInfo) {
    $sheetName = $sheetInfo['name'];
    $sheetPath = $sheetInfo['path'];
    $catalogKey = $sheetInfo['key'];

    if (in_array($sheetName, $skipSheets, true)) {
        echo "OMITIDA {$sheetName}: ya está en tabla dedicada." . PHP_EOL;
        continue;
    }

    if (in_array($catalogKey, $skipKeys, true)) {
        echo "OMITIDA {$sheetName} => {$catalogKey}: catálogo pesado, se importará después con streaming especial." . PHP_EOL;
        continue;
    }

    $xml = $zip->getFromName($sheetPath);

    if ($xml === false) {
        echo "WARN {$sheetName}: no se pudo leer." . PHP_EOL;
        continue;
    }

    $sheetXml = simplexml_load_string($xml);

    $rows = [];

    foreach ($sheetXml->sheetData->row ?? [] as $row) {
        $rowNumber = (int) ($row['r'] ?? 0);
        $cells = [];

        foreach ($row->c as $cell) {
            $col = cfdi_v2_col((string) ($cell['r'] ?? ''));
            $type = (string) ($cell['t'] ?? '');
            $raw = isset($cell->v) ? (string) $cell->v : null;

            if ($type === 's' && $raw !== null) {
                $value = $sharedStrings[(int) $raw] ?? null;
            } else {
                $value = $raw;
            }

            $cells[$col] = cfdi_v2_clean($value);
        }

        if ($cells !== []) {
            $rows[$rowNumber] = $cells;
        }
    }

    $headerRow = null;

    foreach ($rows as $rowNumber => $cells) {
        $joined = mb_strtolower(implode(' ', array_filter($cells)));

        if ((str_contains($joined, 'descripción') || str_contains($joined, 'descripcion')) && str_contains($joined, 'c_')) {
            $headerRow = (int) $rowNumber;
            break;
        }
    }

    if (! $headerRow || ! isset($rows[$headerRow])) {
        echo "WARN {$sheetName}: sin encabezado reconocido. Omitida." . PHP_EOL;
        continue;
    }

    $headers = $rows[$headerRow];
    $codeCol = array_key_first($headers);
    $descriptionCol = cfdi_v2_find_col($headers, ['descripcion', 'description']);
    $validFromCol = cfdi_v2_find_col($headers, ['inicio']);
    $validToCol = cfdi_v2_find_col($headers, ['fin']);

    if (! $codeCol || ! $descriptionCol) {
        echo "WARN {$sheetName}: sin columna código/descripción. Omitida." . PHP_EOL;
        continue;
    }

    $catalogName = cfdi_v2_catalog_name($catalogKey);

    $catalogId = DB::table('sat_billing_catalogs')
        ->updateOrInsert(
            ['catalog_key' => $catalogKey],
            [
                'name' => $catalogName,
                'description' => 'Catálogo CFDI importado desde hoja(s) SAT.',
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

    $catalog = DB::table('sat_billing_catalogs')
        ->where('catalog_key', $catalogKey)
        ->first();

    DB::table('sat_billing_catalog_items')
        ->where('source_sheet', $sheetName)
        ->delete();

    $dataRows = collect($rows)->filter(fn ($cells, $rowNumber) => (int) $rowNumber > $headerRow);
    $totalRows = $dataRows->count();
    $processed = 0;
    $inserted = 0;
    $skipped = 0;
    $batch = [];

    echo PHP_EOL . "Importando {$sheetName} => {$catalogKey} ({$totalRows} filas)" . PHP_EOL;

    foreach ($dataRows as $rowNumber => $cells) {
        $processed++;

        $code = cfdi_v2_format_code($cells[$codeCol] ?? null, $catalogKey);

        if (cfdi_v2_is_header_or_empty($code)) {
            $skipped++;
            continue;
        }

        $description = cfdi_v2_clean($cells[$descriptionCol] ?? null);
        $validFrom = $validFromCol ? cfdi_v2_date($cells[$validFromCol] ?? null) : null;
        $validTo = $validToCol ? cfdi_v2_date($cells[$validToCol] ?? null) : null;

        $isActive = true;

        if ($validTo) {
            $isActive = \Carbon\Carbon::parse($validTo)->endOfDay()->greaterThanOrEqualTo(now());
        }

        $batch[] = [
            'catalog_id' => $catalog->id,
            'catalog_key' => $catalogKey,
            'source_sheet' => $sheetName,
            'source_row' => (int) $rowNumber,
            'code' => $code,
            'name' => $description,
            'description' => $description,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'extra_attributes' => json_encode([
                'headers' => $headers,
                'values' => $cells,
            ], JSON_UNESCAPED_UNICODE),
            'external_key' => sha1($catalogKey . '|' . $sheetName . '|' . $rowNumber . '|' . $code),
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (count($batch) >= 500) {
            DB::table('sat_billing_catalog_items')->insert($batch);
            $inserted += count($batch);
            $batch = [];
        }

        if ($processed % 1000 === 0 || $processed === $totalRows) {
            $percent = $totalRows > 0 ? round(($processed / $totalRows) * 100, 2) : 100;
            echo "[{$percent}%] {$sheetName}: {$processed}/{$totalRows} | insertados {$inserted} | omitidos {$skipped}" . PHP_EOL;
        }
    }

    if ($batch !== []) {
        DB::table('sat_billing_catalog_items')->insert($batch);
        $inserted += count($batch);
    }

    $totalInserted += $inserted;
    $totalSkipped += $skipped;

    echo "OK {$sheetName}: insertados {$inserted}, omitidos {$skipped}" . PHP_EOL;
}

$zip->close();

echo PHP_EOL . "======================================" . PHP_EOL;
echo "RESUMEN V2" . PHP_EOL;
echo "======================================" . PHP_EOL;

DB::table('sat_billing_catalogs')
    ->orderBy('catalog_key')
    ->get(['catalog_key', 'name'])
    ->each(fn ($r) => dump((array) $r));

dump([
    'items_total' => DB::table('sat_billing_catalog_items')->count(),
    'insertados_esta_corrida' => $totalInserted,
    'omitidos_esta_corrida' => $totalSkipped,
]);

DB::table('sat_billing_catalog_items')
    ->select('catalog_key', DB::raw('count(*) as total'))
    ->groupBy('catalog_key')
    ->orderBy('catalog_key')
    ->get()
    ->each(fn ($r) => dump((array) $r));
