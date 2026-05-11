<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$relativePath = 'storage/app/imports/sat/catCFDI_V_4_20260422.xlsx';
$path = base_path($relativePath);

if (! file_exists($path)) {
    throw new RuntimeException("No existe {$relativePath}");
}

if (! class_exists(ZipArchive::class)) {
    throw new RuntimeException('ZipArchive no está disponible en PHP.');
}

foreach (['sat_billing_catalogs', 'sat_billing_catalog_items'] as $table) {
    if (! Schema::hasTable($table)) {
        throw new RuntimeException("No existe la tabla {$table}.");
    }
}

function cfdi_xml_text_content(SimpleXMLElement $node): string
{
    $parts = [];

    foreach ($node->xpath('.//*[local-name()="t"]') ?: [] as $t) {
        $parts[] = (string) $t;
    }

    if ($parts === []) {
        return (string) $node;
    }

    return implode('', $parts);
}

function cfdi_column_letters(string $cellRef): string
{
    return preg_replace('/[^A-Z]/', '', strtoupper($cellRef));
}

function cfdi_clean_value(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }

    $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim($value);

    return $value === '' ? null : $value;
}

function cfdi_excel_date_value(mixed $value): ?string
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

    $value = trim((string) $value);

    if ($value === '' || mb_strtolower($value) === 'nds') {
        return null;
    }

    try {
        return \Carbon\Carbon::parse($value)->toDateString();
    } catch (Throwable $e) {
        return null;
    }
}

function cfdi_normalize_key(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/(_parte_\d+|_part_\d+|_\d+)$/i', '', $value);
    $value = preg_replace('/^c_/i', '', $value);
    $value = str_replace(['C_', 'c_'], '', $value);
    $value = Str::snake($value);

    return trim(mb_strtolower($value), '_');
}

function cfdi_catalog_name(string $key): string
{
    return match ($key) {
        'forma_pago' => 'Forma de pago',
        'moneda' => 'Moneda',
        'tipo_de_comprobante' => 'Tipo de comprobante',
        'exportacion' => 'Exportación',
        'metodo_pago' => 'Método de pago',
        'codigo_postal' => 'Código postal',
        'periodicidad' => 'Periodicidad',
        'meses' => 'Meses',
        'objeto_imp' => 'Objeto de impuesto',
        'impuesto' => 'Impuesto',
        'tipo_factor' => 'Tipo factor',
        'tasa_o_cuota' => 'Tasa o cuota',
        'aduana' => 'Aduana',
        'num_pedimento_aduana' => 'Número de pedimento aduana',
        'patente_aduanal' => 'Patente aduanal',
        'colonia' => 'Colonia',
        'estado' => 'Estado',
        'localidad' => 'Localidad',
        'municipio' => 'Municipio',
        'pais' => 'País',
        'uso_cfdi' => 'Uso CFDI',
        'regimen_fiscal' => 'Régimen fiscal',
        default => Str::headline(str_replace('_', ' ', $key)),
    };
}

function cfdi_code_width(string $catalogKey): ?int
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

function cfdi_format_code(?string $code, string $catalogKey): ?string
{
    $code = cfdi_clean_value($code);

    if ($code === null) {
        return null;
    }

    if (preg_match('/^\d+(\.0+)?$/', $code)) {
        $code = (string) (int) round((float) $code);
    }

    $width = cfdi_code_width($catalogKey);

    if ($width && ctype_digit($code) && strlen($code) < $width) {
        return str_pad($code, $width, '0', STR_PAD_LEFT);
    }

    return $code;
}

function cfdi_find_header_row(array $rows): ?int
{
    foreach ($rows as $rowNumber => $cells) {
        $values = array_values(array_filter($cells, fn ($v) => filled($v)));
        $joined = mb_strtolower(implode(' ', $values));

        if (str_contains($joined, 'descripción') || str_contains($joined, 'descripcion')) {
            foreach ($values as $value) {
                $v = trim((string) $value);

                if (preg_match('/^c[_ ]/i', $v) || preg_match('/^C[_ ]/i', $v)) {
                    return (int) $rowNumber;
                }
            }
        }
    }

    return null;
}

function cfdi_row_is_header_like(?string $code): bool
{
    if ($code === null) {
        return true;
    }

    $v = mb_strtolower(trim($code));

    if ($v === '') {
        return true;
    }

    foreach ([
        'descripción',
        'descripcion',
        'versión',
        'version',
        'c_',
        'fecha',
        'mes_',
        'día_',
        'dia_',
        'diferencia_',
    ] as $needle) {
        if (str_starts_with($v, $needle)) {
            return true;
        }
    }

    return false;
}

function cfdi_find_col_by_keywords(array $headers, array $keywords): ?string
{
    foreach ($headers as $col => $header) {
        $normalized = mb_strtolower(str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], (string) $header));

        foreach ($keywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return $col;
            }
        }
    }

    return null;
}

$zip = new ZipArchive();

if ($zip->open($path) !== true) {
    throw new RuntimeException('No se pudo abrir el XLSX.');
}

$sharedStrings = [];
$sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

if ($sharedStringsXml !== false) {
    $shared = simplexml_load_string($sharedStringsXml);

    foreach ($shared->children() as $si) {
        $sharedStrings[] = cfdi_xml_text_content($si);
    }
}

$workbookXml = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
$workbookXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
$workbookXml->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

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

    if (! isset($rels[$rid])) {
        continue;
    }

    $sheets[] = [
        'name' => $name,
        'path' => $rels[$rid],
    ];
}

echo "Hojas detectadas: " . count($sheets) . PHP_EOL;

$totalInserted = 0;
$totalSkipped = 0;
$catalogSummary = [];

foreach ($sheets as $sheetInfo) {
    $sheetName = $sheetInfo['name'];
    $sheetPath = $sheetInfo['path'];

    if (in_array($sheetName, ['c_ClaveProdServ', 'c_ClaveUnidad'], true)) {
        echo "Omitiendo {$sheetName}: catálogo ya importado en tabla dedicada." . PHP_EOL;
        continue;
    }

    $catalogKey = cfdi_normalize_key($sheetName);
    $catalogName = cfdi_catalog_name($catalogKey);

    $xml = $zip->getFromName($sheetPath);

    if ($xml === false) {
        echo "WARN: no se pudo leer {$sheetName} ({$sheetPath})" . PHP_EOL;
        continue;
    }

    $sheetXml = simplexml_load_string($xml);
    $rows = [];

    foreach ($sheetXml->sheetData->row ?? [] as $row) {
        $rowNumber = (int) ($row['r'] ?? 0);
        $cells = [];

        foreach ($row->c as $cell) {
            $ref = (string) ($cell['r'] ?? '');
            $col = cfdi_column_letters($ref);
            $type = (string) ($cell['t'] ?? '');
            $raw = isset($cell->v) ? (string) $cell->v : null;

            if ($type === 's' && $raw !== null) {
                $value = $sharedStrings[(int) $raw] ?? null;
            } else {
                $value = $raw;
            }

            $cells[$col] = cfdi_clean_value($value);
        }

        if ($cells !== []) {
            $rows[$rowNumber] = $cells;
        }
    }

    $headerRow = cfdi_find_header_row($rows);

    if (! $headerRow || ! isset($rows[$headerRow])) {
        echo "WARN: {$sheetName} sin encabezado reconocido. Omitida." . PHP_EOL;
        continue;
    }

    $headers = $rows[$headerRow];

    $codeCol = array_key_first($headers);
    $descriptionCol = cfdi_find_col_by_keywords($headers, ['descripcion', 'description']);
    $validFromCol = cfdi_find_col_by_keywords($headers, ['fecha inicio', 'inicio de vigencia', 'inicio']);
    $validToCol = cfdi_find_col_by_keywords($headers, ['fecha fin', 'fin de vigencia']);
    $nameCol = $descriptionCol;

    $catalog = DB::table('sat_billing_catalogs')
        ->where('catalog_key', $catalogKey)
        ->first();

    if (! $catalog) {
        $catalogId = DB::table('sat_billing_catalogs')->insertGetId([
            'catalog_key' => $catalogKey,
            'name' => $catalogName,
            'description' => 'Catálogo CFDI importado desde hoja(s) SAT.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } else {
        $catalogId = $catalog->id;

        DB::table('sat_billing_catalogs')
            ->where('id', $catalogId)
            ->update([
                'name' => $catalogName,
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }

    DB::table('sat_billing_catalog_items')
        ->where('source_sheet', $sheetName)
        ->delete();

    $dataRows = collect($rows)
        ->filter(fn ($cells, $rowNumber) => (int) $rowNumber > $headerRow);

    $totalRows = $dataRows->count();
    $processed = 0;
    $inserted = 0;
    $skipped = 0;
    $batch = [];

    echo PHP_EOL . "Importando {$sheetName} => {$catalogKey} ({$totalRows} filas estimadas)" . PHP_EOL;

    foreach ($dataRows as $rowNumber => $cells) {
        $processed++;

        $code = cfdi_format_code($cells[$codeCol] ?? null, $catalogKey);

        if (cfdi_row_is_header_like($code)) {
            $skipped++;
            continue;
        }

        $description = $descriptionCol ? cfdi_clean_value($cells[$descriptionCol] ?? null) : null;
        $name = $nameCol ? cfdi_clean_value($cells[$nameCol] ?? null) : $description;

        $validFrom = $validFromCol ? cfdi_excel_date_value($cells[$validFromCol] ?? null) : null;
        $validTo = $validToCol ? cfdi_excel_date_value($cells[$validToCol] ?? null) : null;

        $extra = [
            'headers' => $headers,
            'values' => $cells,
        ];

        $externalKey = sha1($catalogKey . '|' . $sheetName . '|' . json_encode(array_values($cells), JSON_UNESCAPED_UNICODE));

        $isActive = true;

        if ($validTo) {
            $isActive = \Carbon\Carbon::parse($validTo)->endOfDay()->greaterThanOrEqualTo(now());
        }

        $batch[] = [
            'catalog_id' => $catalogId,
            'catalog_key' => $catalogKey,
            'source_sheet' => $sheetName,
            'source_row' => (int) $rowNumber,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'extra_attributes' => json_encode($extra, JSON_UNESCAPED_UNICODE),
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

        if ($processed % 5000 === 0 || $processed === $totalRows) {
            $percent = $totalRows > 0 ? round(($processed / $totalRows) * 100, 2) : 100;
            echo "[{$percent}%] {$sheetName}: procesados {$processed}/{$totalRows} | insertados {$inserted} | omitidos {$skipped}" . PHP_EOL;
        }
    }

    if ($batch !== []) {
        DB::table('sat_billing_catalog_items')->insert($batch);
        $inserted += count($batch);
    }

    $totalInserted += $inserted;
    $totalSkipped += $skipped;

    $catalogSummary[] = [
        'sheet' => $sheetName,
        'catalog_key' => $catalogKey,
        'inserted' => $inserted,
        'skipped' => $skipped,
    ];

    echo "OK {$sheetName}: insertados {$inserted}, omitidos {$skipped}" . PHP_EOL;
}

$zip->close();

echo PHP_EOL . "======================================" . PHP_EOL;
echo "RESUMEN IMPORTACIÓN CFDI" . PHP_EOL;
echo "======================================" . PHP_EOL;

foreach ($catalogSummary as $row) {
    echo "{$row['catalog_key']} | {$row['sheet']} | insertados: {$row['inserted']} | omitidos: {$row['skipped']}" . PHP_EOL;
}

dump([
    'catalogos' => DB::table('sat_billing_catalogs')->count(),
    'items' => DB::table('sat_billing_catalog_items')->count(),
    'insertados_en_esta_corrida' => $totalInserted,
    'omitidos_en_esta_corrida' => $totalSkipped,
]);

DB::table('sat_billing_catalogs')
    ->orderBy('catalog_key')
    ->get(['catalog_key', 'name'])
    ->each(fn ($r) => dump((array) $r));
