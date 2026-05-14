<?php

namespace App\Support\Billing;

use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use CfdiUtils\CadenaOrigen\DOMBuilder;
use CfdiUtils\XmlResolver\XmlResolver;
use DOMDocument;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpCfdi\Credentials\Credential;
use RuntimeException;
use Throwable;

class InvoiceCfdiXmlBuilder
{
    public function generateSignedXml(Invoice $invoice, ?User $user = null): array
    {
        $invoice->refresh();

        $validator = app(InvoiceCfdiValidator::class);
        $validation = $validator->validate($invoice, $user);

        if (! $validation['success']) {
            return [
                'success' => false,
                'status' => 'validation_error',
                'message' => $validation['message'],
                'path' => null,
                'cadena_original_path' => null,
                'warnings' => $validation['warnings'] ?? [],
                'errors' => $validation['errors'] ?? [],
            ];
        }

        app(BillingSeriesResolver::class)->assignFiscalFolio($invoice, $user);
        $invoice->refresh();

        $company = DB::table('companies')->where('id', (int) $invoice->company_id)->first();

        if (! $company) {
            throw new RuntimeException('La factura no tiene empresa válida.');
        }

        $credential = $this->credential($company);
        $certificate = $this->certificateData($company, $credential);

        $lines = DB::table('invoice_lines')
            ->where('invoice_id', (int) $invoice->id)
            ->orderBy('id')
            ->get()
            ->filter(fn ($line) => (string) ($line->source_type ?? '') !== 'comment')
            ->values();

        if ($lines->count() === 0) {
            throw new RuntimeException('La factura no tiene líneas facturables.');
        }

        $date = $this->cfdiDate($invoice);
        $currency = strtoupper((string) (($invoice->currency_code ?? '') ?: 'MXN'));

        $paymentForm = trim((string) ($invoice->payment_form_code ?? ''));
        if ($paymentForm === '') {
            $paymentForm = '99';
        }

        $paymentMethod = trim((string) ($invoice->payment_method_code ?? ''));
        if ($paymentMethod === '') {
            $paymentMethod = ((float) ($invoice->balance_total ?? 0) <= 0.000001) ? 'PUE' : 'PPD';
        }

        [$concepts, $subtotal, $taxTotal] = $this->buildConcepts($lines);
        $total = round($subtotal + $taxTotal, 2);

        $series = trim((string) ($invoice->cfdi_series ?? ''));
        $folio = trim((string) ($invoice->cfdi_folio ?? ''));

        if ($series === '' || $folio === '') {
            throw new RuntimeException('La factura no tiene serie/folio CFDI asignado.');
        }

        $paymentTerms = trim((string) ($invoice->payment_terms ?? ''));

        $doc = $this->createUnsignedDocument(
            invoice: $invoice,
            company: $company,
            certificate: $certificate,
            concepts: $concepts,
            date: $date,
            currency: $currency,
            paymentForm: $paymentForm,
            paymentMethod: $paymentMethod,
            paymentTerms: $paymentTerms,
            series: $series,
            folio: $folio,
            subtotal: $subtotal,
            taxTotal: $taxTotal,
            total: $total
        );

        $unsignedXml = $doc->saveXML();

        if (! is_string($unsignedXml) || trim($unsignedXml) === '') {
            throw new RuntimeException('No se pudo generar el XML CFDI sin sello.');
        }

        $cadenaOriginal = $this->buildCadenaOriginal($unsignedXml);
        $sello = base64_encode($credential->sign($cadenaOriginal));

        if (! $credential->verify($cadenaOriginal, base64_decode($sello))) {
            throw new RuntimeException('El sello generado no pudo verificarse con el certificado CSD.');
        }

        $comprobante = $doc->documentElement;

        if (! $comprobante) {
            throw new RuntimeException('No se encontró el nodo Comprobante.');
        }

        $comprobante->setAttribute('Sello', $sello);

        $xml = $doc->saveXML();

        if (! is_string($xml) || trim($xml) === '') {
            throw new RuntimeException('No se pudo generar el XML CFDI firmado.');
        }

        $basePath = 'invoices/cfdi/company_'.$invoice->company_id.'/invoice_'.$invoice->id;
        $xmlPath = $basePath.'/cfdi_firmado.xml';
        $cadenaPath = $basePath.'/cadena_original.txt';

        Storage::disk('local')->put($xmlPath, $xml);
        Storage::disk('local')->put($cadenaPath, $cadenaOriginal);

        DB::table('invoices')
            ->where('id', (int) $invoice->id)
            ->update([
                'cfdi_xml_path' => $xmlPath,
                'cfdi_status' => InvoiceCfdiValidator::STATUS_READY_TO_STAMP,
                'cfdi_version' => '4.0',
                'cfdi_type' => 'I',
                'cfdi_series' => $series,
                'cfdi_folio' => $folio,
                'pac_provider' => (string) ($company->billing_pac_provider ?? 'sw'),
                'pac_environment' => (bool) ($company->billing_pac_test_env ?? true) ? 'test' : 'production',
                'pac_error_message' => null,
                'updated_at' => now(),
            ]);

        $validator->audit($invoice->refresh(), $user, [
            'action' => 'generate_signed_xml',
            'status' => 'success',
            'pac_provider' => (string) ($company->billing_pac_provider ?? 'sw'),
            'pac_environment' => (bool) ($company->billing_pac_test_env ?? true) ? 'test' : 'production',
            'message' => 'XML CFDI 4.0 generado con cadena original y sello real. Pendiente timbrar con SW.',
            'request_meta' => [
                'invoice_id' => (int) $invoice->id,
                'invoice_number' => (string) ($invoice->number ?? ''),
                'company_id' => (int) ($invoice->company_id ?? 0),
                'cfdi_series' => $series,
                'cfdi_folio' => $folio,
                'concepts_count' => count($concepts),
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $total,
            ],
            'response_meta' => [
                'xml_path' => $xmlPath,
                'cadena_original_path' => $cadenaPath,
                'cfdi_version' => '4.0',
                'signed' => true,
                'certificate_serial' => $certificate['serial'],
                'sello_preview' => substr($sello, 0, 24).'...',
                'next_step' => 'stamp_with_sw',
            ],
        ]);

        return [
            'success' => true,
            'status' => InvoiceCfdiValidator::STATUS_READY_TO_STAMP,
            'message' => 'XML CFDI firmado generado correctamente. Pendiente timbrar con SW.',
            'path' => $xmlPath,
            'cadena_original_path' => $cadenaPath,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $total,
            'series' => $series,
            'folio' => $folio,
            'certificate_serial' => $certificate['serial'],
            'sello_preview' => substr($sello, 0, 24).'...',
        ];
    }

    public function generatePreliminaryXml(Invoice $invoice, ?User $user = null): array
    {
        return $this->generateSignedXml($invoice, $user);
    }

    private function createUnsignedDocument(
        Invoice $invoice,
        object $company,
        array $certificate,
        array $concepts,
        string $date,
        string $currency,
        string $paymentForm,
        string $paymentMethod,
        string $paymentTerms,
        string $series,
        string $folio,
        float $subtotal,
        float $taxTotal,
        float $total
    ): DOMDocument {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $comprobante = $doc->createElementNS('http://www.sat.gob.mx/cfd/4', 'cfdi:Comprobante');
        $comprobante->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $comprobante->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 'http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd');

        $comprobante->setAttribute('Version', '4.0');
        $comprobante->setAttribute('Serie', $series);
        $comprobante->setAttribute('Folio', $folio);
        $comprobante->setAttribute('Fecha', $date);
        $comprobante->setAttribute('Sello', '');
        $comprobante->setAttribute('FormaPago', $paymentForm);
        $comprobante->setAttribute('NoCertificado', $certificate['serial']);
        $comprobante->setAttribute('Certificado', $certificate['certificate']);

        if ($paymentTerms !== '') {
            $comprobante->setAttribute('CondicionesDePago', $this->satText($paymentTerms, upper: false));
        }

        $comprobante->setAttribute('SubTotal', $this->money($subtotal));
        $comprobante->setAttribute('Moneda', $currency);
        $comprobante->setAttribute('Total', $this->money($total));
        $comprobante->setAttribute('TipoDeComprobante', 'I');
        $comprobante->setAttribute('Exportacion', '01');
        $comprobante->setAttribute('MetodoPago', $paymentMethod);
        $comprobante->setAttribute('LugarExpedicion', $this->onlyDigits((string) ($company->fiscal_postal_code ?? $company->postal_code ?? '')));

        $doc->appendChild($comprobante);

        /*
         * BEXIA_V5526C_CFDI_INFORMACION_GLOBAL_NODE
         * CFDI 4.0 factura global para publico en general.
         * Debe ir antes de Emisor/Receptor.
         */
        if ($this->isGlobalInvoice($invoice)) {
            [$periodicidad, $meses, $anio] = $this->globalInvoiceData($invoice);

            $informacionGlobal = $doc->createElement('cfdi:InformacionGlobal');
            $informacionGlobal->setAttribute('Periodicidad', $periodicidad);
            $informacionGlobal->setAttribute('Meses', $meses);
            $informacionGlobal->setAttribute('Año', $anio);

            $comprobante->appendChild($informacionGlobal);
        }

        $emisor = $doc->createElement('cfdi:Emisor');
        $emisor->setAttribute('Rfc', strtoupper(trim((string) ($company->tax_id ?? ''))));
        $emisor->setAttribute('Nombre', $this->satText((string) (($company->business_name ?? '') ?: ($company->name ?? ''))));
        $emisor->setAttribute('RegimenFiscal', trim((string) ($company->tax_regime ?? '')));
        $comprobante->appendChild($emisor);

        $receptor = $doc->createElement('cfdi:Receptor');
        $receptor->setAttribute('Rfc', strtoupper(trim((string) ($invoice->customer_rfc ?? ''))));
        $receptor->setAttribute('Nombre', $this->satText((string) ($invoice->customer_fiscal_name ?? $invoice->customer_name ?? '')));
        $receptor->setAttribute('DomicilioFiscalReceptor', $this->onlyDigits((string) ($invoice->customer_postal_code ?? '')));
        $receptor->setAttribute('RegimenFiscalReceptor', trim((string) ($invoice->customer_tax_regime_code ?? '')));
        $receptor->setAttribute('UsoCFDI', trim((string) ($invoice->customer_cfdi_use_code ?? '')));
        $comprobante->appendChild($receptor);

        $conceptosNode = $doc->createElement('cfdi:Conceptos');

        foreach ($concepts as $concept) {
            $concepto = $doc->createElement('cfdi:Concepto');
            $concepto->setAttribute('ClaveProdServ', $concept['sat_product_service_code']);

            if ($concept['identification'] !== '') {
                $concepto->setAttribute('NoIdentificacion', $concept['identification']);
            }

            $concepto->setAttribute('Cantidad', $this->quantity($concept['quantity']));
            $concepto->setAttribute('ClaveUnidad', $concept['sat_unit_code']);
            $concepto->setAttribute('Unidad', $this->satText($concept['unit_name']));
            $concepto->setAttribute('Descripcion', $this->satText($concept['description'], upper: false));
            $concepto->setAttribute('ValorUnitario', $this->money($concept['unit_price']));
            $concepto->setAttribute('Importe', $this->money($concept['subtotal']));
            $concepto->setAttribute('ObjetoImp', $concept['tax_object']);

            if ($concept['tax_total'] > 0) {
                $impuestos = $doc->createElement('cfdi:Impuestos');
                $traslados = $doc->createElement('cfdi:Traslados');

                $traslado = $doc->createElement('cfdi:Traslado');
                $traslado->setAttribute('Base', $this->money($concept['subtotal']));
                $traslado->setAttribute('Impuesto', '002');
                $traslado->setAttribute('TipoFactor', 'Tasa');
                $traslado->setAttribute('TasaOCuota', $this->rate($concept['tax_rate']));
                $traslado->setAttribute('Importe', $this->money($concept['tax_total']));

                $traslados->appendChild($traslado);
                $impuestos->appendChild($traslados);
                $concepto->appendChild($impuestos);
            }

            $conceptosNode->appendChild($concepto);
        }

        $comprobante->appendChild($conceptosNode);

        if ($taxTotal > 0) {
            $impuestosNode = $doc->createElement('cfdi:Impuestos');
            $impuestosNode->setAttribute('TotalImpuestosTrasladados', $this->money($taxTotal));

            $trasladosNode = $doc->createElement('cfdi:Traslados');

            foreach ($this->globalTaxGroups($concepts) as $taxGroup) {
                $trasladoNode = $doc->createElement('cfdi:Traslado');
                $trasladoNode->setAttribute('Base', $this->money($taxGroup['base']));
                $trasladoNode->setAttribute('Impuesto', '002');
                $trasladoNode->setAttribute('TipoFactor', 'Tasa');
                $trasladoNode->setAttribute('TasaOCuota', $this->rate($taxGroup['rate']));
                $trasladoNode->setAttribute('Importe', $this->money($taxGroup['amount']));

                $trasladosNode->appendChild($trasladoNode);
            }

            $impuestosNode->appendChild($trasladosNode);
            $comprobante->appendChild($impuestosNode);
        }

        return $doc;
    }


    private function isGlobalInvoice(Invoice $invoice): bool
    {
        if ((string) ($invoice->source_type ?? '') === 'pos_global_invoice') {
            return true;
        }

        $metadata = $this->metadataArray($invoice->metadata ?? null);

        return (bool) ($metadata['is_global_invoice'] ?? false)
            || (string) ($metadata['source'] ?? '') === 'pos_global_invoice';
    }

    private function globalInvoiceData(Invoice $invoice): array
    {
        $metadata = $this->metadataArray($invoice->metadata ?? null);
        $global = $metadata['global_invoice'] ?? [];

        if (! is_array($global)) {
            $global = [];
        }

        $periodicidad = trim((string) ($global['periodicity'] ?? '01'));
        $meses = trim((string) ($global['month'] ?? now()->format('m')));
        $anio = trim((string) ($global['year'] ?? now()->format('Y')));

        if ($periodicidad === '') {
            $periodicidad = '01';
        }

        if ($meses === '') {
            $meses = now()->format('m');
        }

        $meses = str_pad((string) ((int) $meses), 2, '0', STR_PAD_LEFT);

        if ($anio === '') {
            $anio = now()->format('Y');
        }

        return [$periodicidad, $meses, $anio];
    }

    private function metadataArray($metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && trim($metadata) !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }


    private function credential(object $company): Credential
    {
        $certificatePath = (string) ($company->billing_csd_certificate_path ?? '');
        $keyPath = (string) ($company->billing_csd_key_path ?? '');
        $encryptedPassword = (string) ($company->billing_csd_password ?? '');

        if ($certificatePath === '' || $keyPath === '' || $encryptedPassword === '') {
            throw new RuntimeException('La empresa no tiene CSD completo.');
        }

        $certificateFullPath = Storage::disk('local')->path($certificatePath);
        $keyFullPath = Storage::disk('local')->path($keyPath);

        if (! is_file($certificateFullPath)) {
            throw new RuntimeException('No se encontró el archivo .cer del CSD.');
        }

        if (! is_file($keyFullPath)) {
            throw new RuntimeException('No se encontró el archivo .key del CSD.');
        }

        try {
            $password = Crypt::decryptString($encryptedPassword);
        } catch (Throwable $e) {
            throw new RuntimeException('La contraseña CSD no se pudo desencriptar.');
        }

        try {
            return Credential::openFiles($certificateFullPath, $keyFullPath, $password);
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo abrir el CSD para firmar: '.$e->getMessage());
        }
    }

    private function certificateData(object $company, Credential $credential): array
    {
        $path = (string) ($company->billing_csd_certificate_path ?? '');
        $fullPath = Storage::disk('local')->path($path);
        $content = file_get_contents($fullPath);

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('No se pudo leer el certificado CSD.');
        }

        $serial = '';

        try {
            $serial = (string) $credential->certificate()->serialNumber()->bytes();
        } catch (Throwable $e) {
            $serial = $this->satSerial((string) ($company->billing_csd_serial_number ?? ''));
        }

        if ($serial === '') {
            throw new RuntimeException('No se pudo obtener el número de certificado CSD.');
        }

        return [
            'serial' => $serial,
            'certificate' => preg_replace('/\s+/', '', base64_encode($content)) ?: '',
        ];
    }

    private function buildCadenaOriginal(string $xml): string
    {
        $resolver = new XmlResolver();
        $location = $resolver->resolveCadenaOrigenLocation('4.0');

        $builder = new DOMBuilder();
        $cadena = $builder->build($xml, $location);

        $cadena = trim((string) $cadena);

        if ($cadena === '') {
            throw new RuntimeException('No se pudo generar la cadena original CFDI 4.0.');
        }

        return $cadena;
    }

    private function buildConcepts($lines): array
    {
        $concepts = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lines as $line) {
            $quantity = round((float) ($line->quantity ?? 0), 6);
            $unitPrice = round((float) ($line->unit_price_without_tax ?? $line->unit_price ?? 0), 6);
            $lineSubtotal = round($quantity * $unitPrice, 2);

            if (isset($line->subtotal) && is_numeric($line->subtotal)) {
                $lineSubtotal = round((float) $line->subtotal, 2);
            }

            $taxRate = round((float) ($line->tax_rate ?? 0), 6);

            if ($taxRate > 1) {
                $taxRate = $taxRate / 100;
            }

            $lineTax = round($lineSubtotal * $taxRate, 2);

            if (isset($line->tax_amount) && is_numeric($line->tax_amount)) {
                $lineTax = round((float) $line->tax_amount, 2);
            }

            // BEXIA_V5523P13_INFER_TAX_FROM_LINE_TOTAL
            // Algunas líneas históricas tienen subtotal sin IVA y total con IVA,
            // pero tax_rate/tax_amount vacíos. Para CFDI, inferimos el traslado.
            if ($lineTax <= 0 && isset($line->total) && is_numeric($line->total)) {
                $lineTotal = round((float) $line->total, 2);
                $inferredTax = round($lineTotal - $lineSubtotal, 2);

                if ($inferredTax > 0) {
                    $lineTax = $inferredTax;

                    // BEXIA_V5523P14_NORMALIZE_INFERRED_TAX_RATE
                    // Evita tasas como 0.159794 por redondeos históricos.
                    $inferredRate = $lineSubtotal > 0
                        ? round($lineTax / $lineSubtotal, 6)
                        : 0.0;

                    if (abs($inferredRate - 0.16) <= 0.005) {
                        $taxRate = 0.16;
                    } elseif (abs($inferredRate - 0.08) <= 0.005) {
                        $taxRate = 0.08;
                    } else {
                        $taxRate = $inferredRate;
                    }
                }
            }

            $taxObject = trim((string) ($line->sat_tax_object_code ?? ''));

            /*
             * SAT:
             * - ObjetoImp 02 implica que el concepto sí lleva impuestos.
             * - Si no tenemos traslado calculado, no debemos dejar 02 sin nodo cfdi:Impuestos.
             * - Exento/03 no lo soportamos todavía en esta primera versión.
             */
            if ($lineTax > 0 && $taxRate > 0) {
                $taxObject = '02';
            } else {
                $taxObject = '01';
                $taxRate = 0.0;
                $lineTax = 0.0;
            }

            $concepts[] = [
                'sat_product_service_code' => trim((string) ($line->sat_product_service_code ?? '')) ?: '01010101',
                'identification' => $this->lineIdentification($line),
                'sat_unit_code' => trim((string) ($line->sat_unit_code ?? '')) ?: 'H87',
                'unit_name' => trim((string) ($line->unit_name ?? $line->unit ?? '')) ?: 'PIEZA',
                'description' => trim((string) ($line->product_name ?? $line->description ?? 'Concepto')),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $lineSubtotal,
                'tax_rate' => $taxRate,
                'tax_total' => $lineTax,
                'tax_object' => $taxObject,
            ];

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
        }

        return [$concepts, round($subtotal, 2), round($taxTotal, 2)];
    }

    private function lineIdentification(object $line): string
    {
        foreach ([
            'product_sku',
            'sku',
            'product_code',
            'code',
            'barcode',
            'product_barcode',
        ] as $field) {
            $value = trim((string) ($line->{$field} ?? ''));

            if ($value !== '') {
                return mb_substr($value, 0, 100);
            }
        }

        if (! empty($line->product_id)) {
            return (string) $line->product_id;
        }

        return '';
    }

    private function globalTaxGroups(array $concepts): array
    {
        $groups = [];

        foreach ($concepts as $concept) {
            if ($concept['tax_total'] <= 0) {
                continue;
            }

            $key = number_format((float) $concept['tax_rate'], 6, '.', '');

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'rate' => (float) $concept['tax_rate'],
                    'base' => 0.0,
                    'amount' => 0.0,
                ];
            }

            $groups[$key]['base'] += $concept['subtotal'];
            $groups[$key]['amount'] += $concept['tax_total'];
        }

        return array_values(array_map(function (array $row): array {
            $row['base'] = round($row['base'], 2);
            $row['amount'] = round($row['amount'], 2);

            return $row;
        }, $groups));
    }

    private function cfdiDate(Invoice $invoice): string
    {
        /*
         * Para CFDI, la Fecha debe representar el momento real de emisión/firma.
         * invoice_date queda como fecha administrativa de Bexia.
         * La regla de 72h se valida antes en InvoiceCfdiValidator.
         */
        return now()->format('Y-m-d\TH:i:s');
    }

    private function satText(string $value, bool $upper = true): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;

        return $upper ? mb_strtoupper($value) : $value;
    }

    private function onlyDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }

    private function quantity(float $value): string
    {
        return number_format(round($value, 6), 6, '.', '');
    }

    private function rate(float $value): string
    {
        return number_format(round($value, 6), 6, '.', '');
    }

    private function satSerial(string $serial): string
    {
        $serial = trim($serial);

        if ($serial !== '' && ctype_xdigit($serial) && strlen($serial) % 2 === 0) {
            $decoded = @hex2bin($serial);

            if (is_string($decoded) && preg_match('/^[0-9]+$/', $decoded)) {
                return $decoded;
            }
        }

        return $serial;
    }
}
