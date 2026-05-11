<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SatConstanciaParser
{
    public function parse(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el PDF de la Constancia SAT.');
        }

        $text = $this->extractText($path);

        if ($text === '') {
            throw new RuntimeException('No pude leer texto del PDF. Puede ser una constancia escaneada; esa requerirá OCR después.');
        }

        if (! str_contains($this->normalizeForCompare($text), 'constancia de situacion fiscal')) {
            throw new RuntimeException('El PDF no parece ser una Constancia de Situación Fiscal.');
        }

        $rfc = $this->cleanUpper($this->field($text, ['RFC:'], [
            'CURP:',
            'Denominación/Razón Social:',
            'Régimen Capital:',
            'Nombre (s):',
            'Primer Apellido:',
        ]));

        $curp = $this->cleanUpper($this->field($text, ['CURP:'], [
            'Nombre (s):',
            'Primer Apellido:',
            'Fecha inicio de operaciones:',
        ]));

        $companyName = $this->cleanValue($this->field($text, ['Denominación/Razón Social:'], [
            'Régimen Capital:',
            'Nombre Comercial:',
            'Fecha inicio de operaciones:',
        ]));

        $regimenCapital = $this->cleanValue($this->field($text, ['Régimen Capital:'], [
            'Nombre Comercial:',
            'Fecha inicio de operaciones:',
            'Estatus en el padrón:',
        ]));

        $firstName = $this->cleanValue($this->field($text, ['Nombre (s):'], [
            'Primer Apellido:',
            'Segundo Apellido:',
            'Fecha inicio de operaciones:',
        ]));

        $firstLastName = $this->cleanValue($this->field($text, ['Primer Apellido:'], [
            'Segundo Apellido:',
            'Fecha inicio de operaciones:',
        ]));

        $secondLastName = $this->cleanValue($this->field($text, ['Segundo Apellido:'], [
            'Fecha inicio de operaciones:',
            'Estatus en el padrón:',
        ]));

        $personName = trim(implode(' ', array_filter([
            $firstName,
            $firstLastName,
            $secondLastName,
        ])));

        $isPerson = filled($curp) || filled($firstName);
        $name = $isPerson ? $personName : $companyName;

        $commercialName = $this->cleanValue($this->field($text, ['Nombre Comercial:'], [
            'Fecha inicio de operaciones:',
            'Datos del domicilio registrado',
            'Estatus en el padrón:',
        ]));

        $status = $this->cleanValue($this->field($text, ['Estatus en el padrón:'], [
            'Fecha de último cambio de estado:',
            'Nombre Comercial:',
            'Datos del domicilio registrado',
        ]));

        $postalCode = $this->digitsOnly($this->field($text, ['Código Postal:'], [
            'Tipo de Vialidad:',
            'Nombre de Vialidad:',
        ]));

        $streetType = $this->cleanValue($this->field($text, ['Tipo de Vialidad:'], [
            'Nombre de Vialidad:',
            'Número Exterior:',
        ]));

        $street = $this->cleanValue($this->field($text, ['Nombre de Vialidad:'], [
            'Número Exterior:',
        ]));

        $exteriorNumber = $this->cleanValue($this->field($text, ['Número Exterior:'], [
            'Número Interior:',
            'Nombre de la Colonia:',
        ]));

        $interiorNumber = $this->cleanValue($this->field($text, ['Número Interior:'], [
            'Nombre de la Colonia:',
        ]));

        $neighborhood = $this->cleanValue($this->field($text, ['Nombre de la Colonia:'], [
            'Nombre de la Localidad:',
        ]));

        $locality = $this->cleanValue($this->field($text, ['Nombre de la Localidad:'], [
            'Nombre del Municipio o Demarcación Territorial:',
        ]));

        $municipality = $this->cleanValue($this->field($text, ['Nombre del Municipio o Demarcación Territorial:'], [
            'Nombre de la Entidad Federativa:',
        ]));

        $state = $this->formatStateName($this->field($text, ['Nombre de la Entidad Federativa:'], [
            'Entre Calle:',
            'Y Calle:',
            'Actividades Económicas:',
        ]));

        $betweenStreet = $this->cleanValue($this->field($text, ['Entre Calle:'], [
            'Y Calle:',
            'Actividades Económicas:',
        ]));

        $andStreet = $this->cleanValue($this->field($text, ['Y Calle:'], [
            'Actividades Económicas:',
            'Orden Actividad Económica',
            'Regímenes:',
        ]));

        $street2 = trim(implode(' y ', array_filter([$betweenStreet, $andStreet])));

        $taxRegimeName = $this->extractTaxRegimeName($text);
        $taxRegimeCode = $this->resolveTaxRegimeCode($taxRegimeName);

        $activities = $this->extractSection($text, 'Actividades Económicas:', 'Regímenes:');
        $issuePlaceAndDate = $this->extractIssuePlaceAndDate($text);

        $notes = [];
        $notes[] = 'Importado desde Constancia de Situación Fiscal.';
        if ($issuePlaceAndDate) {
            $notes[] = 'Lugar y fecha de emisión: ' . $issuePlaceAndDate;
        }
        if ($status) {
            $notes[] = 'Estatus SAT: ' . $status;
        }
        if ($regimenCapital) {
            $notes[] = 'Régimen capital: ' . $regimenCapital;
        }
        if ($taxRegimeName) {
            $notes[] = 'Régimen fiscal detectado: ' . $taxRegimeName;
        }
        if ($activities) {
            $notes[] = 'Actividades económicas detectadas: ' . $activities;
        }

        return array_filter([
            'contact_type' => $isPerson ? 'person' : 'company',
            'address_type' => 'main',
            'name' => $name,
            'fiscal_name' => $name,
            'commercial_name' => $commercialName ?: $name,
            'rfc' => $rfc,
            'curp' => $curp,
            'postal_code' => $postalCode,
            'fiscal_zip' => $postalCode,
            'street' => $street,
            'street2' => $street2,
            'exterior_number' => $exteriorNumber,
            'interior_number' => $interiorNumber,
            'neighborhood' => $neighborhood,
            'locality' => $locality,
            'municipality' => $municipality,
            'city' => $locality,
            'state' => $state,
            'country' => 'México',
            'sat_country_code' => 'MEX',
            'sat_tax_regime_code' => $taxRegimeCode,
            'internal_notes' => implode("\n", array_filter($notes)),
        ], fn ($value) => $value !== null && $value !== '');
    }

    protected function extractText(string $path): string
    {
        $command = 'pdftotext -layout -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null';
        $text = shell_exec($command);

        if (! is_string($text) || trim($text) === '') {
            $command = 'pdftotext -raw -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null';
            $text = shell_exec($command);
        }

        return $this->normalizeText((string) $text);
    }

    protected function normalizeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\n+/u', "\n", $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string) $text);
    }

    protected function field(string $text, array $labels, array $nextLabels): ?string
    {
        $nextPattern = implode('|', array_map(
            fn (string $label): string => preg_quote($label, '/'),
            $nextLabels
        ));

        foreach ($labels as $label) {
            $pattern = '/' . preg_quote($label, '/') . '\s*(.*?)\s*(?=' . $nextPattern . '|$)/iu';

            if (preg_match($pattern, $text, $matches)) {
                return $this->cleanValue($matches[1] ?? null);
            }
        }

        return null;
    }

    protected function extractTaxRegimeName(string $text): ?string
    {
        if (preg_match('/Regímenes:\s*Régimen\s+Fecha Inicio\s+Fecha Fin\s+(.*?)\s+\d{2}\/\d{2}\/\d{4}/iu', $text, $matches)) {
            return $this->cleanValue($matches[1] ?? null);
        }

        if (preg_match('/Regimenes:\s*Régimen\s+Fecha Inicio\s+Fecha Fin\s+(.*?)\s+\d{2}\/\d{2}\/\d{4}/iu', $text, $matches)) {
            return $this->cleanValue($matches[1] ?? null);
        }

        return null;
    }

    protected function extractIssuePlaceAndDate(string $text): ?string
    {
        if (preg_match('/Lugar y Fecha de Emisión\s+(.*?)\s+[A-Z0-9]{12,13}\s+Datos de Identificación/iu', $text, $matches)) {
            return $this->cleanValue($matches[1] ?? null);
        }

        return null;
    }

    protected function extractSection(string $text, string $startLabel, string $endLabel): ?string
    {
        $pattern = '/' . preg_quote($startLabel, '/') . '\s*(.*?)\s*' . preg_quote($endLabel, '/') . '/iu';

        if (! preg_match($pattern, $text, $matches)) {
            return null;
        }

        return $this->cleanValue($matches[1] ?? null);
    }

    protected function resolveTaxRegimeCode(?string $regimeName): ?string
    {
        $regimeName = $this->cleanValue($regimeName);

        if (! $regimeName) {
            return null;
        }

        $normalized = $this->normalizeForCompare($regimeName);

        $known = [
            'regimen general de ley personas morales' => '601',
            'general de ley personas morales' => '601',
            'personas morales con fines no lucrativos' => '603',
            'sueldos y salarios e ingresos asimilados a salarios' => '605',
            'arrendamiento' => '606',
            'demas ingresos' => '608',
            'personas fisicas con actividades empresariales y profesionales' => '612',
            'incorporacion fiscal' => '621',
            'regimen simplificado de confianza' => '626',
            'simplificado de confianza' => '626',
            'plataformas tecnologicas' => '625',
        ];

        foreach ($known as $label => $code) {
            if (str_contains($normalized, $label)) {
                return $code;
            }
        }

        if (! Schema::hasTable('sat_billing_catalog_items')) {
            return null;
        }

        $rows = DB::table('sat_billing_catalog_items')
            ->where('catalog_key', 'regimen_fiscal')
            ->where('is_active', true)
            ->get(['code', 'name', 'description']);

        foreach ($rows as $row) {
            $label = $this->normalizeForCompare(trim((string) ($row->name ?: $row->description)));

            if ($label !== '' && (str_contains($normalized, $label) || str_contains($label, $normalized))) {
                return (string) $row->code;
            }
        }

        return null;
    }

    protected function cleanValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim($value));

        return $value !== '' ? $value : null;
    }

    protected function cleanUpper(?string $value): ?string
    {
        $value = $this->cleanValue($value);

        return $value ? strtoupper($value) : null;
    }

    protected function digitsOnly(?string $value): ?string
    {
        $value = preg_replace('/\D+/', '', (string) $value);

        return $value !== '' ? $value : null;
    }

    protected function normalizeForCompare(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'u', 'n'], $value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    protected function formatStateName(?string $value): ?string
    {
        $value = $this->cleanValue($value);

        if (! $value) {
            return null;
        }

        $key = $this->normalizeForCompare($value);

        return [
            'ciudad de mexico' => 'Ciudad de México',
            'queretaro' => 'Querétaro',
            'mexico' => 'México',
            'nuevo leon' => 'Nuevo León',
            'san luis potosi' => 'San Luis Potosí',
            'michoacan' => 'Michoacán',
            'yucatan' => 'Yucatán',
        ][$key] ?? mb_convert_case(mb_strtolower($value), MB_CASE_TITLE, 'UTF-8');
    }
}
