<?php

namespace App\Support\FiscalSat;

use App\Models\SatCfdiConcept;
use App\Models\SatCfdiDocument;
use App\Models\SatCfdiTax;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;

class SatCfdiXmlImportService
{
    public function importFromPath(
        string $path,
        int $companyId,
        string $direction,
        ?int $userId = null,
        string $source = 'manual'
    ): array {
        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el archivo XML.');
        }

        $content = file_get_contents($path);

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('El archivo XML está vacío.');
        }

        return $this->importXmlContent($content, $companyId, $direction, $userId, $source);
    }

    public function importXmlContent(
        string $xmlContent,
        int $companyId,
        string $direction,
        ?int $userId = null,
        string $source = 'manual'
    ): array {
        if (! in_array($direction, ['issued', 'received'], true)) {
            throw new RuntimeException('La dirección del CFDI debe ser issued o received.');
        }

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xmlContent, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);

        if (! $xml instanceof SimpleXMLElement) {
            $errors = collect(libxml_get_errors())
                ->map(fn ($error) => trim($error->message))
                ->filter()
                ->implode(' | ');

            libxml_clear_errors();

            throw new RuntimeException('XML inválido. ' . $errors);
        }

        $parsed = $this->parse($xml);

        $uuid = $parsed['uuid'];

        if ($uuid === '') {
            throw new RuntimeException('El XML no contiene UUID de timbrado fiscal.');
        }

        $this->validateDirectionAgainstCompanyRfc($companyId, $direction, $parsed);

        $sha256 = hash('sha256', $xmlContent);
        $xmlPath = 'fiscal-sat/cfdi/' . $companyId . '/' . $direction . '/' . $uuid . '.xml';

        return DB::transaction(function () use ($parsed, $xmlContent, $companyId, $direction, $userId, $source, $sha256, $xmlPath) {
            Storage::disk('local')->put($xmlPath, $xmlContent);

            $document = SatCfdiDocument::withTrashed()
                ->where('company_id', $companyId)
                ->where('uuid', $parsed['uuid'])
                ->where('direction', $direction)
                ->first();

            if (! $document) {
                $document = new SatCfdiDocument([
                    'company_id' => $companyId,
                    'uuid' => $parsed['uuid'],
                    'direction' => $direction,
                ]);
            }

            if (method_exists($document, 'trashed') && $document->trashed()) {
                $document->restore();
            }

            $document->fill([
                'imported_by_id' => $userId,
                'cfdi_type' => $parsed['cfdi_type'],
                'status' => $parsed['status'],
                'version' => $parsed['version'],
                'issuer_rfc' => $parsed['issuer_rfc'],
                'issuer_name' => $parsed['issuer_name'],
                'receiver_rfc' => $parsed['receiver_rfc'],
                'receiver_name' => $parsed['receiver_name'],
                'issued_at' => $parsed['issued_at'],
                'certified_at' => $parsed['certified_at'],
                'cancelled_at' => null,
                'currency' => $parsed['currency'],
                'exchange_rate' => $parsed['exchange_rate'],
                'subtotal' => $parsed['subtotal'],
                'discount' => $parsed['discount'],
                'total_transferred_taxes' => $parsed['total_transferred_taxes'],
                'total_withheld_taxes' => $parsed['total_withheld_taxes'],
                'total' => $parsed['total'],
                'payment_form' => $parsed['payment_form'],
                'payment_method' => $parsed['payment_method'],
                'usage_cfdi' => $parsed['usage_cfdi'],
                'export_status' => $parsed['export_status'],
                'xml_path' => $xmlPath,
                'xml_sha256' => $sha256,
                'source' => $source,
                'imported_at' => now(),
                'metadata' => $parsed['metadata'],
            ]);

            $document->save();

            SatCfdiConcept::query()
                ->where('sat_cfdi_document_id', $document->id)
                ->delete();

            SatCfdiTax::query()
                ->where('sat_cfdi_document_id', $document->id)
                ->delete();

            foreach ($parsed['concepts'] as $concept) {
                SatCfdiConcept::create([
                    'sat_cfdi_document_id' => $document->id,
                    'company_id' => $companyId,
                    'product_key' => $concept['product_key'],
                    'identification_number' => $concept['identification_number'],
                    'description' => $concept['description'],
                    'quantity' => $concept['quantity'],
                    'unit_key' => $concept['unit_key'],
                    'unit_name' => $concept['unit_name'],
                    'unit_price' => $concept['unit_price'],
                    'amount' => $concept['amount'],
                    'discount' => $concept['discount'],
                    'taxes' => $concept['taxes'],
                    'metadata' => $concept['metadata'],
                ]);
            }

            foreach ($parsed['taxes'] as $tax) {
                SatCfdiTax::create([
                    'sat_cfdi_document_id' => $document->id,
                    'company_id' => $companyId,
                    'tax_direction' => $tax['tax_direction'],
                    'tax' => $tax['tax'],
                    'factor_type' => $tax['factor_type'],
                    'rate_or_fee' => $tax['rate_or_fee'],
                    'base' => $tax['base'],
                    'amount' => $tax['amount'],
                    'metadata' => $tax['metadata'],
                ]);
            }

            DB::table('sat_cfdi_processing_logs')->insert([
                'company_id' => $companyId,
                'sat_cfdi_document_id' => $document->id,
                'user_id' => $userId,
                'event' => 'manual_xml_imported',
                'level' => 'info',
                'message' => 'CFDI XML importado manualmente.',
                'payload' => json_encode([
                    'uuid' => $parsed['uuid'],
                    'direction' => $direction,
                    'source' => $source,
                    'concepts_count' => count($parsed['concepts']),
                    'taxes_count' => count($parsed['taxes']),
                    'xml_sha256' => $sha256,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'ok' => true,
                'document_id' => $document->id,
                'uuid' => $document->uuid,
                'direction' => $document->direction,
                'direction_label' => $this->directionLabel($document->direction),
                'cfdi_type' => $document->cfdi_type,
                'cfdi_type_label' => $this->cfdiTypeLabel((string) $document->cfdi_type),
                'issuer_rfc' => $document->issuer_rfc,
                'receiver_rfc' => $document->receiver_rfc,
                'total' => (float) $document->total,
                'concepts_count' => count($parsed['concepts']),
                'global_taxes_count' => count($parsed['taxes']),
                'concept_taxes_count' => $this->countConceptTaxes($parsed['concepts']),
                'taxes_count' => count($parsed['taxes']),
                'xml_path' => $xmlPath,
            ];
        });
    }

    private function validateDirectionAgainstCompanyRfc(int $companyId, string $direction, array $parsed): void
    {
        $companyRfcs = $this->companyRfcs($companyId);

        if ($companyRfcs === []) {
            return;
        }

        $issuerRfc = $this->normalizeRfc((string) ($parsed['issuer_rfc'] ?? ''));
        $receiverRfc = $this->normalizeRfc((string) ($parsed['receiver_rfc'] ?? ''));

        $expectedDirection = null;

        if ($issuerRfc !== '' && in_array($issuerRfc, $companyRfcs, true)) {
            $expectedDirection = 'issued';
        }

        if ($receiverRfc !== '' && in_array($receiverRfc, $companyRfcs, true)) {
            $expectedDirection = 'received';
        }

        if ($expectedDirection === null) {
            return;
        }

        if ($expectedDirection !== $direction) {
            throw new RuntimeException(
                'La dirección fiscal seleccionada no coincide con el XML. '
                . 'Para esta empresa el CFDI parece ser "' . $this->directionLabel($expectedDirection) . '". '
                . 'RFC emisor: ' . ($parsed['issuer_rfc'] ?? '-') . '. '
                . 'RFC receptor: ' . ($parsed['receiver_rfc'] ?? '-') . '.'
            );
        }
    }

    private function companyRfcs(int $companyId): array
    {
        $rfcs = [];

        if (\Illuminate\Support\Facades\Schema::hasTable('sat_company_credentials')) {
            $rfcs = array_merge(
                $rfcs,
                \Illuminate\Support\Facades\DB::table('sat_company_credentials')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->pluck('rfc')
                    ->all()
            );
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('companies')) {
            foreach (['rfc', 'tax_id', 'tax_number', 'tax_identification_number', 'vat', 'vat_number'] as $column) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('companies', $column)) {
                    $value = \Illuminate\Support\Facades\DB::table('companies')
                        ->where('id', $companyId)
                        ->value($column);

                    if ($value) {
                        $rfcs[] = $value;
                    }
                }
            }
        }

        return collect($rfcs)
            ->map(fn ($rfc) => $this->normalizeRfc((string) $rfc))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeRfc(string $rfc): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9Ñ&]/u', '', trim($rfc)) ?? '');
    }

    private function directionLabel(?string $direction): string
    {
        return match ($direction) {
            'issued' => 'Emitido por la empresa',
            'received' => 'Recibido por la empresa',
            default => (string) $direction,
        };
    }

    private function cfdiTypeLabel(string $type): string
    {
        return match ($type) {
            'I' => 'Ingreso',
            'E' => 'Egreso',
            'P' => 'Pago',
            'N' => 'Nómina',
            'T' => 'Traslado',
            default => $type,
        };
    }

    private function countConceptTaxes(array $concepts): int
    {
        $count = 0;

        foreach ($concepts as $concept) {
            $count += count($concept['taxes'] ?? []);
        }

        return $count;
    }

    private function parse(SimpleXMLElement $xml): array
    {
        $namespaces = $xml->getNamespaces(true);
        $cfdiNs = $namespaces['cfdi'] ?? 'http://www.sat.gob.mx/cfd/4';
        $xml->registerXPathNamespace('cfdi', $cfdiNs);
        $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

        $cfdi = $xml->children($cfdiNs);

        $emisor = $cfdi->Emisor[0] ?? null;
        $receptor = $cfdi->Receptor[0] ?? null;
        $impuestos = $cfdi->Impuestos[0] ?? null;

        $timbres = $xml->xpath('//tfd:TimbreFiscalDigital') ?: [];
        $timbre = $timbres[0] ?? null;

        $uuid = strtoupper((string) $this->firstAttr($timbre, ['UUID'], ''));

        $concepts = [];
        $taxes = [];

        if (isset($cfdi->Conceptos)) {
            foreach ($cfdi->Conceptos->Concepto as $conceptNode) {
                $conceptTaxes = $this->extractConceptTaxes($conceptNode, $cfdiNs);

                $concepts[] = [
                    'product_key' => $this->firstAttr($conceptNode, ['ClaveProdServ']),
                    'identification_number' => $this->firstAttr($conceptNode, ['NoIdentificacion']),
                    'description' => $this->firstAttr($conceptNode, ['Descripcion']),
                    'quantity' => $this->decimal($this->firstAttr($conceptNode, ['Cantidad'], '0')),
                    'unit_key' => $this->firstAttr($conceptNode, ['ClaveUnidad']),
                    'unit_name' => $this->firstAttr($conceptNode, ['Unidad']),
                    'unit_price' => $this->decimal($this->firstAttr($conceptNode, ['ValorUnitario'], '0')),
                    'amount' => $this->decimal($this->firstAttr($conceptNode, ['Importe'], '0')),
                    'discount' => $this->decimal($this->firstAttr($conceptNode, ['Descuento'], '0')),
                    'taxes' => $conceptTaxes,
                    'metadata' => [
                        'objeto_imp' => $this->firstAttr($conceptNode, ['ObjetoImp']),
                    ],
                ];
            }
        }

        if ($impuestos instanceof SimpleXMLElement) {
            $impuestosChildren = $impuestos->children($cfdiNs);

            if (isset($impuestosChildren->Traslados)) {
                foreach ($impuestosChildren->Traslados->Traslado as $taxNode) {
                    $taxes[] = $this->taxArray($taxNode, 'transferred');
                }
            }

            if (isset($impuestosChildren->Retenciones)) {
                foreach ($impuestosChildren->Retenciones->Retencion as $taxNode) {
                    $taxes[] = $this->taxArray($taxNode, 'withheld');
                }
            }
        }

        return [
            'uuid' => $uuid,
            'version' => $this->firstAttr($xml, ['Version', 'version']),
            'cfdi_type' => $this->firstAttr($xml, ['TipoDeComprobante']),
            'status' => 'vigente',
            'issuer_rfc' => strtoupper((string) $this->firstAttr($emisor, ['Rfc'], '')),
            'issuer_name' => $this->firstAttr($emisor, ['Nombre']),
            'receiver_rfc' => strtoupper((string) $this->firstAttr($receptor, ['Rfc'], '')),
            'receiver_name' => $this->firstAttr($receptor, ['Nombre']),
            'issued_at' => $this->dateTime($this->firstAttr($xml, ['Fecha'])),
            'certified_at' => $this->dateTime($this->firstAttr($timbre, ['FechaTimbrado'])),
            'currency' => $this->firstAttr($xml, ['Moneda']),
            'exchange_rate' => $this->nullableDecimal($this->firstAttr($xml, ['TipoCambio'])),
            'subtotal' => $this->decimal($this->firstAttr($xml, ['SubTotal'], '0')),
            'discount' => $this->decimal($this->firstAttr($xml, ['Descuento'], '0')),
            'total_transferred_taxes' => $this->decimal($this->firstAttr($impuestos, ['TotalImpuestosTrasladados'], '0')),
            'total_withheld_taxes' => $this->decimal($this->firstAttr($impuestos, ['TotalImpuestosRetenidos'], '0')),
            'total' => $this->decimal($this->firstAttr($xml, ['Total'], '0')),
            'payment_form' => $this->firstAttr($xml, ['FormaPago']),
            'payment_method' => $this->firstAttr($xml, ['MetodoPago']),
            'usage_cfdi' => $this->firstAttr($receptor, ['UsoCFDI']),
            'export_status' => $this->firstAttr($xml, ['Exportacion']),
            'concepts' => $concepts,
            'taxes' => $taxes,
            'metadata' => [
                'serie' => $this->firstAttr($xml, ['Serie']),
                'folio' => $this->firstAttr($xml, ['Folio']),
                'lugar_expedicion' => $this->firstAttr($xml, ['LugarExpedicion']),
                'no_certificado' => $this->firstAttr($xml, ['NoCertificado']),
                'tfd_no_certificado_sat' => $this->firstAttr($timbre, ['NoCertificadoSAT']),
                'issuer_regimen_fiscal' => $this->firstAttr($emisor, ['RegimenFiscal']),
                'receiver_regimen_fiscal' => $this->firstAttr($receptor, ['RegimenFiscalReceptor']),
                'receiver_zip' => $this->firstAttr($receptor, ['DomicilioFiscalReceptor']),
            ],
        ];
    }

    private function extractConceptTaxes(SimpleXMLElement $conceptNode, string $cfdiNs): array
    {
        $taxes = [];
        $children = $conceptNode->children($cfdiNs);

        if (! isset($children->Impuestos)) {
            return $taxes;
        }

        $impuestosChildren = $children->Impuestos->children($cfdiNs);

        if (isset($impuestosChildren->Traslados)) {
            foreach ($impuestosChildren->Traslados->Traslado as $taxNode) {
                $taxes[] = $this->taxArray($taxNode, 'transferred');
            }
        }

        if (isset($impuestosChildren->Retenciones)) {
            foreach ($impuestosChildren->Retenciones->Retencion as $taxNode) {
                $taxes[] = $this->taxArray($taxNode, 'withheld');
            }
        }

        return $taxes;
    }

    private function taxArray(SimpleXMLElement $taxNode, string $direction): array
    {
        $taxCode = (string) $this->firstAttr($taxNode, ['Impuesto'], '');

        return [
            'tax_direction' => $direction,
            'tax' => $this->taxName($taxCode),
            'factor_type' => $this->firstAttr($taxNode, ['TipoFactor']),
            'rate_or_fee' => $this->nullableDecimal($this->firstAttr($taxNode, ['TasaOCuota'])),
            'base' => $this->decimal($this->firstAttr($taxNode, ['Base'], '0')),
            'amount' => $this->decimal($this->firstAttr($taxNode, ['Importe'], '0')),
            'metadata' => [
                'sat_tax_code' => $taxCode,
            ],
        ];
    }

    private function taxName(string $code): string
    {
        return match ($code) {
            '001' => 'ISR',
            '002' => 'IVA',
            '003' => 'IEPS',
            default => $code !== '' ? $code : 'NO_IDENTIFICADO',
        };
    }

    private function firstAttr(?SimpleXMLElement $node, array $names, ?string $default = null): ?string
    {
        if (! $node instanceof SimpleXMLElement) {
            return $default;
        }

        $attributes = $node->attributes();

        foreach ($names as $name) {
            if (isset($attributes[$name])) {
                $value = trim((string) $attributes[$name]);

                return $value !== '' ? $value : $default;
            }
        }

        return $default;
    }

    private function decimal(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '0';
        }

        return (string) round((float) str_replace(',', '', $value), 6);
    }

    private function nullableDecimal(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return $this->decimal($value);
    }

    private function dateTime(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
