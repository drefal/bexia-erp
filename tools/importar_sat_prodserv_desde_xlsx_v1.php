<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$relativePath = 'storage/app/imports/sat/Clave_ProdServ.xlsx';
$path = base_path($relativePath);

if (! file_exists($path)) {
    throw new RuntimeException("No existe {$relativePath}");
}

if (! class_exists(ZipArchive::class)) {
    throw new RuntimeException('ZipArchive no está disponible en PHP.');
}

if (! Schema::hasTable('sat_product_service_codes')) {
    throw new RuntimeException('No existe la tabla sat_product_service_codes.');
}

function prodserv_xml_text_content(SimpleXMLElement $node): string
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

function prodserv_column_letters(string $cellRef): string
{
    return preg_replace('/[^A-Z]/', '', strtoupper($cellRef));
}

function prodserv_clean_value(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim($value);

    return $value === '' ? null : $value;
}

function prodserv_excel_date_value(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        $days = (int) floor((float) $value);
        return now()->setDate(1899, 12, 30)->startOfDay()->addDays($days)->toDateString();
    }

    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    try {
        return \Carbon\Carbon::parse($value)->toDateString();
    } catch (Throwable $e) {
        return null;
    }
}

function prodserv_bool_value(mixed $value): bool
{
    $value = mb_strtolower(trim((string) ($value ?? '')));

    return in_array($value, ['1', 'si', 'sí', 'yes', 'true', 'aplica', 'obligatorio'], true);
}

function prodserv_normalize_header(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $value);
    $value = preg_replace('/[^a-z0-9]+/u', '_', $value);
    return trim($value, '_');
}

$zip = new ZipArchive();

if ($zip->open($path) !== true) {
    throw new RuntimeException('No se pudo abrir el XLSX.');
}

$sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
$sharedStrings = [];

if ($sharedStringsXml !== false) {
    $shared = simplexml_load_string($sharedStringsXml);

    foreach ($shared->children() as $si) {
        $sharedStrings[] = prodserv_xml_text_content($si);
    }
}

$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

if ($sheetXml === false) {
    throw new RuntimeException('No se encontró xl/worksheets/sheet1.xml.');
}

$sheet = simplexml_load_string($sheetXml);
$rawRows = [];

foreach ($sheet->sheetData->row ?? [] as $row) {
    $rowNumber = (int) ($row['r'] ?? 0);
    $cells = [];

    foreach ($row->c as $cell) {
        $ref = (string) ($cell['r'] ?? '');
        $col = prodserv_column_letters($ref);
        $type = (string) ($cell['t'] ?? '');
        $raw = isset($cell->v) ? (string) $cell->v : null;

        if ($type === 's' && $raw !== null) {
            $value = $sharedStrings[(int) $raw] ?? null;
        } else {
            $value = $raw;
        }

        $cells[$col] = prodserv_clean_value($value);
    }

    $rawRows[$rowNumber] = $cells;
}

$headerRow = null;
$headerMap = [];

foreach ($rawRows as $rowNumber => $cells) {
    $normalizedCells = [];

    foreach ($cells as $col => $value) {
        if (! $value) {
            continue;
        }

        $normalizedCells[$col] = prodserv_normalize_header($value);
    }

    $hasCode = collect($normalizedCells)->contains(fn ($v) => str_contains($v, 'claveprodserv') || str_contains($v, 'clave_prodserv') || $v === 'c_claveprodserv');
    $hasDescription = collect($normalizedCells)->contains(fn ($v) => str_contains($v, 'descripcion'));

    if ($hasCode && $hasDescription) {
        $headerRow = $rowNumber;

        foreach ($normalizedCells as $col => $header) {
            $headerMap[$col] = $header;
        }

        break;
    }
}

if (! $headerRow) {
    throw new RuntimeException('No se encontró fila de encabezados con c_ClaveProdServ y Descripción.');
}

$map = [
    'code' => null,
    'description' => null,
    'include_iva' => null,
    'include_ieps' => null,
    'required_complement' => null,
    'valid_from' => null,
    'valid_to' => null,
    'border_stimulus' => null,
    'similar_words' => null,
];

foreach ($headerMap as $col => $header) {
    if (str_contains($header, 'claveprodserv') || str_contains($header, 'clave_prodserv')) {
        $map['code'] = $col;
    } elseif (str_contains($header, 'descripcion')) {
        $map['description'] = $col;
    } elseif (str_contains($header, 'iva')) {
        $map['include_iva'] = $col;
    } elseif (str_contains($header, 'ieps')) {
        $map['include_ieps'] = $col;
    } elseif (str_contains($header, 'complemento')) {
        $map['required_complement'] = $col;
    } elseif (str_contains($header, 'inicio') || str_contains($header, 'fecha_de_inicio')) {
        $map['valid_from'] = $col;
    } elseif (str_contains($header, 'fin') || str_contains($header, 'fecha_de_fin')) {
        $map['valid_to'] = $col;
    } elseif (str_contains($header, 'franja') || str_contains($header, 'fronteriza') || str_contains($header, 'estimulo')) {
        $map['border_stimulus'] = $col;
    } elseif (str_contains($header, 'palabras') || str_contains($header, 'similares')) {
        $map['similar_words'] = $col;
    }
}

if (! $map['code'] || ! $map['description']) {
    throw new RuntimeException('No se pudieron mapear las columnas obligatorias código y descripción.');
}

$inserted = 0;
$updated = 0;
$skipped = 0;
$processedCodes = [];

foreach ($rawRows as $rowNumber => $cells) {
    if ($rowNumber <= $headerRow) {
        continue;
    }

    $code = $cells[$map['code']] ?? null;
    $description = $cells[$map['description']] ?? null;

    if (! $code || ! $description) {
        $skipped++;
        continue;
    }

    $validTo = prodserv_excel_date_value($map['valid_to'] ? ($cells[$map['valid_to']] ?? null) : null);

    $isActive = true;

    if ($validTo) {
        $isActive = \Carbon\Carbon::parse($validTo)->endOfDay()->greaterThanOrEqualTo(now());
    }

    $payload = [
        'code' => $code,
        'description' => $description,
        'is_active' => $isActive,
        'updated_at' => now(),
    ];

    $optional = [
        'include_iva' => $map['include_iva'] ? prodserv_bool_value($cells[$map['include_iva']] ?? null) : false,
        'include_ieps' => $map['include_ieps'] ? prodserv_bool_value($cells[$map['include_ieps']] ?? null) : false,
        'required_complement' => $map['required_complement'] ? ($cells[$map['required_complement']] ?? null) : null,
        'valid_from' => prodserv_excel_date_value($map['valid_from'] ? ($cells[$map['valid_from']] ?? null) : null),
        'valid_to' => $validTo,
        'border_stimulus' => $map['border_stimulus'] ? prodserv_bool_value($cells[$map['border_stimulus']] ?? null) : false,
        'similar_words' => $map['similar_words'] ? ($cells[$map['similar_words']] ?? null) : null,
    ];

    foreach ($optional as $column => $value) {
        if (Schema::hasColumn('sat_product_service_codes', $column)) {
            $payload[$column] = $value;
        }
    }

    $exists = DB::table('sat_product_service_codes')
        ->where('code', $code)
        ->exists();

    if ($exists) {
        DB::table('sat_product_service_codes')
            ->where('code', $code)
            ->update($payload);

        $updated++;
    } else {
        $payload['created_at'] = now();

        DB::table('sat_product_service_codes')->insert($payload);

        $inserted++;
    }

    $processedCodes[] = $code;
}

$zip->close();

dump([
    'archivo' => $relativePath,
    'header_row' => $headerRow,
    'map' => $map,
    'insertados' => $inserted,
    'actualizados' => $updated,
    'omitidos' => $skipped,
    'codigos_procesados' => count(array_unique($processedCodes)),
    'total_sat_product_service_codes' => DB::table('sat_product_service_codes')->count(),
]);

DB::table('sat_product_service_codes')
    ->whereIn('code', ['01010101', '43231500', '43231513', '84111506'])
    ->orderBy('code')
    ->get()
    ->each(fn ($r) => dump((array) $r));
