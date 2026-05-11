<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! Schema::hasTable('sat_billing_catalog_items')) {
    throw new RuntimeException('No existe sat_billing_catalog_items.');
}

if (! Schema::hasTable('sat_postal_code_locations')) {
    throw new RuntimeException('No existe sat_postal_code_locations. Ejecuta migración primero.');
}

function sat_decode_extra($extra): array
{
    if (is_array($extra)) {
        return $extra;
    }

    if (is_string($extra) && trim($extra) !== '') {
        $decoded = json_decode($extra, true);

        return is_array($decoded) ? $decoded : [];
    }

    return [];
}

function sat_values($row): array
{
    $extra = sat_decode_extra($row->extra_attributes ?? null);
    $values = $extra['values'] ?? [];

    return is_array($values) ? $values : [];
}

function sat_first_value(array $values, array $columns): ?string
{
    foreach ($columns as $column) {
        $value = $values[$column] ?? null;

        if ($value !== null && trim((string) $value) !== '') {
            return trim((string) $value);
        }
    }

    return null;
}

function sat_state_map(): array
{
    return [
        'AGU' => 'Aguascalientes',
        'BCN' => 'Baja California',
        'BCS' => 'Baja California Sur',
        'CAM' => 'Campeche',
        'CHP' => 'Chiapas',
        'CHH' => 'Chihuahua',
        'CMX' => 'Ciudad de México',
        'COA' => 'Coahuila',
        'COL' => 'Colima',
        'DUR' => 'Durango',
        'GUA' => 'Guanajuato',
        'GRO' => 'Guerrero',
        'HID' => 'Hidalgo',
        'JAL' => 'Jalisco',
        'MEX' => 'México',
        'MIC' => 'Michoacán',
        'MOR' => 'Morelos',
        'NAY' => 'Nayarit',
        'NLE' => 'Nuevo León',
        'OAX' => 'Oaxaca',
        'PUE' => 'Puebla',
        'QUE' => 'Querétaro',
        'ROO' => 'Quintana Roo',
        'SLP' => 'San Luis Potosí',
        'SIN' => 'Sinaloa',
        'SON' => 'Sonora',
        'TAB' => 'Tabasco',
        'TAM' => 'Tamaulipas',
        'TLA' => 'Tlaxcala',
        'VER' => 'Veracruz',
        'YUC' => 'Yucatán',
        'ZAC' => 'Zacatecas',
    ];
}

function sat_extract_state_code($row): ?string
{
    $values = sat_values($row);
    $states = array_keys(sat_state_map());

    foreach ($values as $value) {
        $value = strtoupper(trim((string) $value));

        if (in_array($value, $states, true)) {
            return $value;
        }
    }

    $text = (string) (($row->description ?? '') . ' ' . ($row->name ?? ''));

    if (preg_match('/Estado\s+([A-Z]{3})/iu', $text, $m)) {
        return strtoupper($m[1]);
    }

    return null;
}

function sat_extract_named_code($row, string $label): ?string
{
    $text = (string) (($row->description ?? '') . ' ' . ($row->name ?? ''));

    if (preg_match('/' . preg_quote($label, '/') . '\s+([^\/\s]+)/iu', $text, $m)) {
        return trim((string) $m[1]);
    }

    return null;
}

function sat_best_name($row, ?string $code = null): ?string
{
    $code = trim((string) $code);
    $values = sat_values($row);
    $states = array_keys(sat_state_map());

    foreach (['C', 'D', 'B', 'A'] as $column) {
        $value = trim((string) ($values[$column] ?? ''));

        if ($value === '') {
            continue;
        }

        if ($code !== '' && $value === $code) {
            continue;
        }

        if (in_array(strtoupper($value), $states, true)) {
            continue;
        }

        if (preg_match('/^\d+$/', $value)) {
            continue;
        }

        return $value;
    }

    foreach (['name', 'description'] as $field) {
        $value = trim((string) ($row->{$field} ?? ''));

        if ($value !== '' && $value !== $code && ! preg_match('/^\d+$/', $value)) {
            return $value;
        }
    }

    return null;
}

echo "Limpiando tabla sat_postal_code_locations..." . PHP_EOL;
DB::table('sat_postal_code_locations')->truncate();

$stateNames = sat_state_map();

echo "Construyendo mapa de municipios..." . PHP_EOL;

$municipalityMap = [];

DB::table('sat_billing_catalog_items')
    ->where('catalog_key', 'municipio')
    ->where('is_active', true)
    ->orderBy('id')
    ->chunk(1000, function ($rows) use (&$municipalityMap): void {
        foreach ($rows as $row) {
            $code = trim((string) $row->code);
            $stateCode = sat_extract_state_code($row);
            $name = sat_best_name($row, $code);

            if ($code === '' || ! $stateCode || ! $name) {
                continue;
            }

            $municipalityMap[$stateCode . '|' . $code] = $name;
        }
    });

echo "Municipios mapeados: " . count($municipalityMap) . PHP_EOL;

echo "Construyendo mapa de localidades..." . PHP_EOL;

$localityMap = [];

DB::table('sat_billing_catalog_items')
    ->where('catalog_key', 'localidad')
    ->where('is_active', true)
    ->orderBy('id')
    ->chunk(1000, function ($rows) use (&$localityMap): void {
        foreach ($rows as $row) {
            $code = trim((string) $row->code);
            $stateCode = sat_extract_state_code($row);
            $name = sat_best_name($row, $code);

            if ($code === '' || ! $stateCode || ! $name) {
                continue;
            }

            $localityMap[$stateCode . '|' . $code] = $name;
        }
    });

echo "Localidades mapeadas: " . count($localityMap) . PHP_EOL;

echo "Construyendo mapa de códigos postales..." . PHP_EOL;

$postalMap = [];

DB::table('sat_billing_catalog_items')
    ->where('catalog_key', 'codigo_postal')
    ->where('is_active', true)
    ->orderBy('id')
    ->chunk(5000, function ($rows) use (&$postalMap, $stateNames, $municipalityMap, $localityMap): void {
        foreach ($rows as $row) {
            $postalCode = trim((string) $row->code);

            if ($postalCode === '') {
                continue;
            }

            $stateCode = sat_extract_state_code($row);
            $municipalityCode = sat_extract_named_code($row, 'Municipio');
            $localityCode = sat_extract_named_code($row, 'Localidad');

            $stateName = $stateCode ? ($stateNames[$stateCode] ?? $stateCode) : null;
            $municipalityName = ($stateCode && $municipalityCode)
                ? ($municipalityMap[$stateCode . '|' . $municipalityCode] ?? null)
                : null;
            $localityName = ($stateCode && $localityCode)
                ? ($localityMap[$stateCode . '|' . $localityCode] ?? null)
                : null;

            $postalMap[$postalCode] = [
                'postal_code' => $postalCode,
                'state_code' => $stateCode,
                'state_name' => $stateName,
                'municipality_code' => $municipalityCode,
                'municipality_name' => $municipalityName,
                'locality_code' => $localityCode,
                'locality_name' => $localityName,
            ];
        }

        echo "CP procesados: " . count($postalMap) . PHP_EOL;
    });

echo "Códigos postales mapeados: " . count($postalMap) . PHP_EOL;

echo "Insertando colonias con datos completos..." . PHP_EOL;

$inserted = 0;
$skipped = 0;

DB::table('sat_billing_catalog_items')
    ->where('catalog_key', 'colonia')
    ->where('is_active', true)
    ->orderBy('id')
    ->chunk(1000, function ($rows) use (&$inserted, &$skipped, $postalMap): void {
        $batch = [];

        foreach ($rows as $row) {
            $values = sat_values($row);

            $neighborhoodCode = trim((string) ($values['A'] ?? $row->code ?? ''));
            $postalCode = trim((string) ($values['B'] ?? ''));
            $neighborhoodName = trim((string) ($values['C'] ?? ''));

            if ($postalCode === '' || $neighborhoodName === '') {
                $skipped++;
                continue;
            }

            $postal = $postalMap[$postalCode] ?? [
                'postal_code' => $postalCode,
                'state_code' => null,
                'state_name' => null,
                'municipality_code' => null,
                'municipality_name' => null,
                'locality_code' => null,
                'locality_name' => null,
            ];

            $batch[] = [
                'postal_code' => $postalCode,
                'state_code' => $postal['state_code'],
                'state_name' => $postal['state_name'],
                'municipality_code' => $postal['municipality_code'],
                'municipality_name' => $postal['municipality_name'],
                'locality_code' => $postal['locality_code'],
                'locality_name' => $postal['locality_name'],
                'neighborhood_code' => $neighborhoodCode,
                'neighborhood_name' => $neighborhoodName,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($batch !== []) {
            DB::table('sat_postal_code_locations')->insertOrIgnore($batch);
            $inserted += count($batch);
        }

        if (($inserted + $skipped) % 10000 < 1000) {
            echo "Colonias procesadas: " . ($inserted + $skipped) . " | insertadas: {$inserted} | omitidas: {$skipped}" . PHP_EOL;
        }
    });

echo "Agregando CP sin colonia, si existen..." . PHP_EOL;

$batch = [];

foreach ($postalMap as $postalCode => $postal) {
    $exists = DB::table('sat_postal_code_locations')
        ->where('postal_code', $postalCode)
        ->exists();

    if ($exists) {
        continue;
    }

    $batch[] = [
        'postal_code' => $postalCode,
        'state_code' => $postal['state_code'],
        'state_name' => $postal['state_name'],
        'municipality_code' => $postal['municipality_code'],
        'municipality_name' => $postal['municipality_name'],
        'locality_code' => $postal['locality_code'],
        'locality_name' => $postal['locality_name'],
        'neighborhood_code' => null,
        'neighborhood_name' => null,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    if (count($batch) >= 1000) {
        DB::table('sat_postal_code_locations')->insert($batch);
        $batch = [];
    }
}

if ($batch !== []) {
    DB::table('sat_postal_code_locations')->insert($batch);
}

dump([
    'sat_postal_code_locations_total' => DB::table('sat_postal_code_locations')->count(),
    'postal_codes_distinct' => DB::table('sat_postal_code_locations')->distinct('postal_code')->count('postal_code'),
    'sample_14360' => DB::table('sat_postal_code_locations')->where('postal_code', '14360')->limit(5)->get()->map(fn ($r) => (array) $r)->all(),
    'sample_05770' => DB::table('sat_postal_code_locations')->where('postal_code', '05770')->limit(5)->get()->map(fn ($r) => (array) $r)->all(),
]);
