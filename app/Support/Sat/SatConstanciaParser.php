<?php

namespace App\Support\Sat;

use Smalot\PdfParser\Parser;

class SatConstanciaParser
{
    public function parseFile(string $path): array
    {
        if (! is_file($path)) {
            throw new \InvalidArgumentException("No existe el archivo PDF: {$path}");
        }

        $parser = new Parser();
        $pdf = $parser->parseFile($path);

        $rawText = $pdf->getText();
        $text = $this->normalizeText($rawText);
        $flat = $this->flatText($text);

        $rfc = $this->extractRfc($flat);
        $regimeName = $this->extractRegime($flat);

        return [
            'source_file' => $path,
            'raw_text_length' => mb_strlen($rawText),

            'rfc' => $rfc,
            'business_name' => $this->readable($this->extractBusinessName($flat, $rfc)),
            'capital_regime' => $this->readable($this->between($flat, 'Régimen Capital:', 'Nombre Comercial:')),
            'commercial_name' => $this->readable($this->between($flat, 'Nombre Comercial:', 'Fecha inicio de operaciones:')),
            'operation_start_date_text' => $this->readableDateText($this->between($flat, 'Fecha inicio de operaciones:', 'Estatus en el padrón:')),
            'taxpayer_status' => $this->readable($this->between($flat, 'Estatus en el padrón:', 'Fecha de último cambio de estado:')),

            'fiscal_postal_code' => $this->extractPostalCode($flat),
            'street_type' => $this->readable($this->between($flat, 'Tipo de Vialidad:', 'Nombre de Vialidad:')),
            'street' => $this->readable($this->between($flat, 'Nombre de Vialidad:', 'Número Exterior:')),
            'ext_number' => $this->readable($this->between($flat, 'Número Exterior:', 'Número Interior:')),
            'int_number' => $this->readable($this->between($flat, 'Número Interior:', 'Nombre de la Colonia:')),
            'neighborhood' => $this->readable($this->between($flat, 'Nombre de la Colonia:', 'Nombre de la Localidad:')),
            'locality' => $this->readable($this->between($flat, 'Nombre de la Localidad:', 'Nombre del Municipio o Demarcación Territorial:')),
            'municipality' => $this->readable($this->between($flat, 'Nombre del Municipio o Demarcación Territorial:', 'Nombre de la Entidad Federativa:')),
            'state' => $this->readable($this->between($flat, 'Nombre de la Entidad Federativa:', 'Entre Calle:')),
            'between_street' => $this->readable($this->between($flat, 'Entre Calle:', 'Y Calle:')),
            'and_street' => $this->readable($this->between($flat, 'Y Calle:', 'Actividades Económicas:')),

            'tax_regime_name' => $regimeName,
            'tax_regime_code' => $this->mapTaxRegimeCode($regimeName),
            'activities' => $this->extractActivities($flat),

        ];
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\n[ \t]+/u', "\n", $text);
        $text = preg_replace('/[ \t]+\n/u', "\n", $text);
        $text = preg_replace('/\n{2,}/u', "\n", $text);

        return trim((string) $text);
    }

    private function flatText(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text);

        $compactLabels = [
            'Denominación/RazónSocial:' => 'Denominación/Razón Social:',
            'RégimenCapital:' => 'Régimen Capital:',
            'NombreComercial:' => 'Nombre Comercial:',
            'Fechainiciodeoperaciones:' => 'Fecha inicio de operaciones:',
            'Estatusenelpadrón:' => 'Estatus en el padrón:',
            'Fechadeúltimocambiodeestado:' => 'Fecha de último cambio de estado:',
            'CódigoPostal:' => 'Código Postal:',
            'TipodeVialidad:' => 'Tipo de Vialidad:',
            'NombredeVialidad:' => 'Nombre de Vialidad:',
            'NúmeroExterior:' => 'Número Exterior:',
            'NúmeroInterior:' => 'Número Interior:',
            'NombredelaColonia:' => 'Nombre de la Colonia:',
            'NombredelaLocalidad:' => 'Nombre de la Localidad:',
            'NombredelMunicipiooDemarcaciónTerritorial:' => 'Nombre del Municipio o Demarcación Territorial:',
            'NombredelaEntidadFederativa:' => 'Nombre de la Entidad Federativa:',
            'EntreCalle:' => 'Entre Calle:',
            'YCalle:' => 'Y Calle:',
            'ActividadesEconómicas:' => 'Actividades Económicas:',
        ];

        foreach ($compactLabels as $compact => $canonical) {
            $text = str_replace($compact, ' ' . $canonical . ' ', $text);
        }

        $labels = [
            'RFC:',
            'Denominación/Razón Social:',
            'Régimen Capital:',
            'Nombre Comercial:',
            'Fecha inicio de operaciones:',
            'Estatus en el padrón:',
            'Fecha de último cambio de estado:',
            'Código Postal:',
            'Tipo de Vialidad:',
            'Nombre de Vialidad:',
            'Número Exterior:',
            'Número Interior:',
            'Nombre de la Colonia:',
            'Nombre de la Localidad:',
            'Nombre del Municipio o Demarcación Territorial:',
            'Nombre de la Entidad Federativa:',
            'Entre Calle:',
            'Y Calle:',
            'Actividades Económicas:',
            'Regímenes:',
            'Obligaciones:',
        ];

        foreach ($labels as $label) {
            $text = str_replace($label, ' ' . $label . ' ', $text);
        }

        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string) $text);
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim((string) $value));
        $value = trim((string) $value, " \t\n\r\0\x0B:");

        return $value !== '' ? $value : null;
    }

    private function readable(?string $value): ?string
    {
        $value = $this->clean($value);

        if ($value === null) {
            return null;
        }

        $replacements = [
            'CEDISGRUPOL7' => 'CEDIS GRUPO L7',
            'CEDIS GRUPO L 7' => 'CEDIS GRUPO L7',
            'GRUPO L 7' => 'GRUPO L7',
            'GRUPOL7' => 'GRUPO L7',
            'DISTRIBUIDORAEIMPORTADORA' => 'DISTRIBUIDORA E IMPORTADORA',
            'GRUPOLINEA7' => 'GRUPO LINEA 7',
            'LINEA7' => 'LINEA 7',
            'SADECV' => 'SA DE CV',
            'SOCIEDADANONIMADECAPITALVARIABLE' => 'SOCIEDAD ANONIMA DE CAPITAL VARIABLE',
            'CIUDADDEMEXICO' => 'CIUDAD DE MEXICO',
            'LOMASESTRELLA1ASECCION' => 'LOMAS ESTRELLA 1A SECCION',
            'LOMAS ESTRELLA 1 A SECCION' => 'LOMAS ESTRELLA 1A SECCION',
            'GRANJASDESANANTONIO' => 'GRANJAS DE SAN ANTONIO',
            'CALLE2' => 'CALLE 2',
            'AÑODEJUAREZ' => 'AÑO DE JUAREZ',
            'TERCERANILLODECIRCUNVALACION' => 'TERCER ANILLO DE CIRCUNVALACION',
            'CALLESANLUIS' => 'CALLE SAN LUIS',
            'CALLECIRCUITOBAHAMAS' => 'CALLE CIRCUITO BAHAMAS',
            'AVENIDA(AV.)' => 'AVENIDA (AV.)',
        ];

        foreach ($replacements as $from => $to) {
            $value = str_replace($from, $to, $value);
        }

        $value = preg_replace('/([A-ZÁÉÍÓÚÑ])([0-9])/u', '$1 $2', $value);
        $value = preg_replace('/([0-9])([A-ZÁÉÍÓÚÑ])/u', '$1 $2', $value);
        $value = preg_replace('/\s*,\s*/u', ', ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        // Correcciones finales despues de normalizar espacios entre letras/numeros.
        $finalReplacements = [
            'GRUPO L 7' => 'GRUPO L7',
            'CEDIS GRUPO L 7' => 'CEDIS GRUPO L7',
            'LINEA 7' => 'LINEA 7',
            '1 A SECCION' => '1A SECCION',
            'LOMAS ESTRELLA 1 A SECCION' => 'LOMAS ESTRELLA 1A SECCION',
        ];

        foreach ($finalReplacements as $from => $to) {
            $value = str_replace($from, $to, $value);
        }

        $value = preg_replace('/\s+/u', ' ', $value);

        return $this->clean($value);
    }

    private function readableDateText(?string $value): ?string
    {
        $value = $this->readable($value);

        if ($value === null) {
            return null;
        }

        $value = str_replace('DE', ' DE ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return $this->clean($value);
    }

    private function between(string $text, string $start, string $end): ?string
    {
        $pattern = '/' . preg_quote($start, '/') . '\s*(.*?)\s*' . preg_quote($end, '/') . '/su';

        if (preg_match($pattern, $text, $m)) {
            return $this->clean($m[1] ?? null);
        }

        return null;
    }

    private function extractRfc(string $text): ?string
    {
        if (preg_match('/RFC:\s*([A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3})/u', $text, $m)) {
            return $this->clean($m[1]);
        }

        if (preg_match('/\b([A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3})\b/u', $text, $m)) {
            return $this->clean($m[1]);
        }

        return null;
    }

    private function extractBusinessName(string $text, ?string $rfc): ?string
    {
        $value = $this->between($text, 'Denominación/Razón Social:', 'Régimen Capital:');

        if ($value) {
            return $value;
        }

        if ($rfc && preg_match('/Datos de Identificación del Contribuyente:\s*RFC:\s*' . preg_quote($rfc, '/') . '\s*(.*?)\s*Régimen Capital:/su', $text, $m)) {
            return $this->clean($m[1] ?? null);
        }

        return null;
    }

    private function extractPostalCode(string $text): ?string
    {
        if (preg_match('/Código Postal:\s*([0-9]{5})/u', $text, $m)) {
            return $this->clean($m[1]);
        }

        return null;
    }

    private function extractRegime(string $text): ?string
    {
        if (preg_match('/Regímenes:\s*Régimen\s+Fecha Inicio\s+Fecha Fin\s*(.*?)\s+[0-9]{2}\/[0-9]{2}\/[0-9]{4}/su', $text, $m)) {
            return $this->readable($m[1] ?? null);
        }

        if (preg_match('/(Régimen General de Ley Personas Morales)/u', $text, $m)) {
            return $this->clean($m[1] ?? null);
        }

        return null;
    }

    private function mapTaxRegimeCode(?string $regimeName): ?string
    {
        if (! $regimeName) {
            return null;
        }

        $normalized = mb_strtolower($this->clean($regimeName));

        $map = [
            'régimen general de ley personas morales' => '601',
            'regimen general de ley personas morales' => '601',
            'personas morales con fines no lucrativos' => '603',
            'sueldos y salarios e ingresos asimilados a salarios' => '605',
            'arrendamiento' => '606',
            'régimen de enajenación o adquisición de bienes' => '607',
            'demás ingresos' => '608',
            'residentes en el extranjero sin establecimiento permanente en méxico' => '610',
            'ingresos por dividendos' => '611',
            'personas físicas con actividades empresariales y profesionales' => '612',
            'ingresos por intereses' => '614',
            'régimen de los ingresos por obtención de premios' => '615',
            'sin obligaciones fiscales' => '616',
            'sociedades cooperativas de producción que optan por diferir sus ingresos' => '620',
            'incorporación fiscal' => '621',
            'actividades agrícolas, ganaderas, silvícolas y pesqueras' => '622',
            'opcional para grupos de sociedades' => '623',
            'coordinados' => '624',
            'régimen de las actividades empresariales con ingresos a través de plataformas tecnológicas' => '625',
            'régimen simplificado de confianza' => '626',
        ];

        return $map[$normalized] ?? null;
    }

    private function extractActivities(string $text): array
    {
        $activities = [];

        if (! preg_match('/Actividades Económicas:\s*(.*?)\s*Regímenes:/su', $text, $m)) {
            return $activities;
        }

        $section = $this->clean($m[1] ?? '') ?? '';

        preg_match_all(
            '/([0-9]+)\s+(.+?)\s+([0-9]{1,3})\s+([0-9]{2}\/[0-9]{2}\/[0-9]{4})/u',
            $section,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $activities[] = [
                'order' => (int) $match[1],
                'activity' => $this->readable($match[2]),
                'percentage' => (int) $match[3],
                'start_date' => $match[4],
            ];
        }

        return $activities;
    }
}
