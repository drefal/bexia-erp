<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$relativePath = 'storage/app/imports/sat/Catalogo_SAT_Clave_Unidad.xlsx';
$path = base_path($relativePath);

if (! file_exists($path)) {
    throw new RuntimeException("No existe {$relativePath}");
}

if (! class_exists(ZipArchive::class)) {
    throw new RuntimeException('ZipArchive no está disponible en PHP.');
}

if (! Schema::hasTable('sat_unit_codes')) {
    throw new RuntimeException('No existe la tabla sat_unit_codes.');
}

function xml_text_content(SimpleXMLElement $node): string
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

function column_letters(string $cellRef): string
{
    return preg_replace('/[^A-Z]/', '', strtoupper($cellRef));
}

function excel_date_value(mixed $value): ?string
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

function clean_excel_value(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    if (is_float($value) || is_int($value)) {
        if (abs($value - round($value)) < 0.000001) {
            return (string) (int) round($value);
        }

        return (string) $value;
    }

    $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim($value);

    return $value === '' ? null : $value;
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
        $sharedStrings[] = xml_text_content($si);
    }
}

$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

if ($sheetXml === false) {
    throw new RuntimeException('No se encontró xl/worksheets/sheet1.xml.');
}

$sheet = simplexml_load_string($sheetXml);
$rows = $sheet->sheetData->row ?? [];

$inserted = 0;
$updated = 0;
$skipped = 0;
$processedCodes = [];

foreach ($rows as $row) {
    $rowNumber = (int) ($row['r'] ?? 0);

    if ($rowNumber < 7) {
        continue;
    }

    $cells = [];

    foreach ($row->c as $cell) {
        $ref = (string) ($cell['r'] ?? '');
        $col = column_letters($ref);
        $type = (string) ($cell['t'] ?? '');
        $raw = isset($cell->v) ? (string) $cell->v : null;

        if ($type === 's' && $raw !== null) {
            $value = $sharedStrings[(int) $raw] ?? null;
        } else {
            $value = $raw;
        }

        $cells[$col] = clean_excel_value($value);
    }

    $code = $cells['A'] ?? null;
    $name = $cells['B'] ?? null;

    if (! $code || ! $name) {
        $skipped++;
        continue;
    }

    $description = $cells['C'] ?? null;
    $note = $cells['D'] ?? null;
    $validFrom = excel_date_value($cells['E'] ?? null);
    $validTo = excel_date_value($cells['F'] ?? null);
    $symbol = $cells['G'] ?? null;

    $isActive = true;

    if ($validTo) {
        $isActive = \Carbon\Carbon::parse($validTo)->endOfDay()->greaterThanOrEqualTo(now());
    }

    $payload = [
        'code' => $code,
        'name' => $name,
        'symbol' => $symbol,
        'is_active' => $isActive,
        'updated_at' => now(),
    ];

    foreach ([
        'description' => $description,
        'note' => $note,
        'valid_from' => $validFrom,
        'valid_to' => $validTo,
    ] as $column => $value) {
        if (Schema::hasColumn('sat_unit_codes', $column)) {
            $payload[$column] = $value;
        }
    }

    $exists = DB::table('sat_unit_codes')
        ->where('code', $code)
        ->exists();

    if ($exists) {
        DB::table('sat_unit_codes')
            ->where('code', $code)
            ->update($payload);

        $updated++;
    } else {
        $payload['created_at'] = now();

        DB::table('sat_unit_codes')->insert($payload);

        $inserted++;
    }

    $processedCodes[] = $code;
}

$zip->close();

dump([
    'archivo' => $relativePath,
    'insertados' => $inserted,
    'actualizados' => $updated,
    'omitidos' => $skipped,
    'codigos_procesados' => count(array_unique($processedCodes)),
    'total_sat_unit_codes' => DB::table('sat_unit_codes')->count(),
]);

DB::table('sat_unit_codes')
    ->whereIn('code', ['H87', 'E48', 'KGM', 'MTR', 'LTR', 'PZA', 'ZZ'])
    ->orderBy('code')
    ->get()
    ->each(fn ($r) => dump((array) $r));
