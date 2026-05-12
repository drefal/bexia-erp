<?php

namespace App\Support\Billing;

use App\Models\Invoice;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use NumberFormatter;
use Throwable;

class InvoicePdfBuilder
{
    public function generate(Invoice $invoice, ?User $user = null): array
    {
        $invoice->refresh();

        $company = DB::table('companies')->where('id', (int) $invoice->company_id)->first();

        if (! $company) {
            return [
                'success' => false,
                'message' => 'La factura no tiene empresa válida.',
                'path' => null,
            ];
        }

        $lines = DB::table('invoice_lines')
            ->where('invoice_id', (int) $invoice->id)
            ->orderBy('id')
            ->get();

        $payments = Schema::hasTable('invoice_payments')
            ? DB::table('invoice_payments')->where('invoice_id', (int) $invoice->id)->orderBy('id')->get()
            : collect();

        $xmlInfo = $this->xmlInfo($invoice);
        $isStamped = (string) ($invoice->cfdi_status ?? '') === InvoiceCfdiValidator::STATUS_STAMPED;

        $subtotal = $this->moneyValue($invoice->subtotal ?? null, $lines, 'subtotal');
        $taxTotal = $this->moneyValue($invoice->tax_total ?? null, $lines, 'tax');
        $total = is_numeric($invoice->total ?? null)
            ? round((float) $invoice->total, 2)
            : round($subtotal + $taxTotal, 2);

        $data = [
            'invoice' => $invoice,
            'company' => $company,
            'lines' => $this->lineViewModels($lines),
            'payments' => $payments,
            'xmlInfo' => $xmlInfo,
            'isStamped' => $isStamped,
            'generatedAt' => now(),
            'logoDataUri' => $this->logoDataUri($company),
            'branchLabel' => $this->branchLabel($invoice, $company),
            'companyAddress' => $this->companyAddress($company),
            'customerAddress' => $this->customerAddress($invoice),
            'subtotal' => $subtotal,
            'taxTotal' => $taxTotal,
            'total' => $total,
            'amountWords' => $this->amountToWords($total, (string) (($invoice->currency_code ?? '') ?: 'MXN')),
            'cfdiTypeLabel' => 'I - Ingreso',
            'paymentLabel' => $this->paymentLabel($invoice),
            'cfdiUseLabel' => $this->cfdiUseLabel($invoice),
            'qrDataUri' => $this->qrDataUri($invoice, $xmlInfo, $total),
            'qrUrl' => $this->satQrUrl($invoice, $xmlInfo, $total),
        ];

        $pdf = Pdf::loadView('pdf.invoices.cfdi', $data)
            ->setPaper('letter', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);

        $basePath = 'invoices/cfdi/company_'.$invoice->company_id.'/invoice_'.$invoice->id;
        $pdfPath = $basePath.'/factura.pdf';

        Storage::disk('local')->put($pdfPath, $pdf->output());

        DB::table('invoices')
            ->where('id', (int) $invoice->id)
            ->update([
                'cfdi_pdf_path' => $pdfPath,
                'updated_at' => now(),
            ]);

        app(InvoiceCfdiValidator::class)->audit($invoice->refresh(), $user, [
            'action' => 'generate_pdf',
            'status' => 'success',
            'pac_provider' => (string) ($invoice->pac_provider ?? 'sw'),
            'pac_environment' => (string) ($invoice->pac_environment ?? ''),
            'message' => 'PDF de factura generado correctamente.',
            'request_meta' => [
                'invoice_id' => (int) $invoice->id,
                'invoice_number' => (string) ($invoice->number ?? ''),
                'cfdi_status' => (string) ($invoice->cfdi_status ?? ''),
            ],
            'response_meta' => [
                'pdf_path' => $pdfPath,
                'is_stamped' => $isStamped,
                'layout' => 'odoo_gl7_like_v5523q2',
            ],
        ]);

        return [
            'success' => true,
            'message' => 'PDF generado correctamente.',
            'path' => $pdfPath,
        ];
    }

    private function lineViewModels($lines): array
    {
        $result = [];

        foreach ($lines as $line) {
            if ((string) ($line->source_type ?? '') === 'comment') {
                continue;
            }

            $quantity = round((float) ($line->quantity ?? 0), 4);

            $subtotal = isset($line->subtotal) && is_numeric($line->subtotal)
                ? round((float) $line->subtotal, 2)
                : round($quantity * (float) ($line->unit_price_without_tax ?? $line->unit_price ?? 0), 2);

            $tax = 0.0;

            if (isset($line->tax_amount) && is_numeric($line->tax_amount)) {
                $tax = round((float) $line->tax_amount, 2);
            } elseif (isset($line->total) && is_numeric($line->total)) {
                $tax = max(0.0, round((float) $line->total - $subtotal, 2));
            } elseif (isset($line->tax_rate) && is_numeric($line->tax_rate)) {
                $rate = (float) $line->tax_rate;
                $rate = $rate > 1 ? $rate / 100 : $rate;
                $tax = round($subtotal * $rate, 2);
            }

            $unitPrice = $quantity > 0
                ? round($subtotal / $quantity, 2)
                : round((float) ($line->unit_price_without_tax ?? $line->unit_price ?? 0), 2);

            $result[] = [
                'quantity' => $quantity,
                'unit' => trim((string) ($line->sat_unit_code ?? $line->unit_name ?? $line->unit ?? '')),
                'unit_name' => trim((string) ($line->unit_name ?? $line->unit ?? '')),
                'sat_code' => trim((string) ($line->sat_product_service_code ?? '')),
                'internal_ref' => $this->lineReference($line),
                'barcode' => trim((string) ($line->barcode ?? $line->product_barcode ?? '')),
                'description' => trim((string) ($line->product_name ?? $line->description ?? 'Concepto')),
                'unit_price' => $unitPrice,
                'tax' => $tax,
                'subtotal' => $subtotal,
            ];
        }

        return $result;
    }

    private function moneyValue(mixed $storedValue, $lines, string $type): float
    {
        if (is_numeric($storedValue)) {
            return round((float) $storedValue, 2);
        }

        $sum = 0.0;

        foreach ($lines as $line) {
            $quantity = round((float) ($line->quantity ?? 0), 4);

            $subtotal = isset($line->subtotal) && is_numeric($line->subtotal)
                ? round((float) $line->subtotal, 2)
                : round($quantity * (float) ($line->unit_price_without_tax ?? $line->unit_price ?? 0), 2);

            if ($type === 'subtotal') {
                $sum += $subtotal;
                continue;
            }

            if (isset($line->tax_amount) && is_numeric($line->tax_amount)) {
                $sum += round((float) $line->tax_amount, 2);
            } elseif (isset($line->total) && is_numeric($line->total)) {
                $sum += max(0.0, round((float) $line->total - $subtotal, 2));
            }
        }

        return round($sum, 2);
    }

    private function lineReference(object $line): string
    {
        foreach (['product_sku', 'sku', 'product_code', 'code', 'product_id'] as $field) {
            $value = trim((string) ($line->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function xmlInfo(Invoice $invoice): array
    {
        $path = (string) ($invoice->cfdi_xml_path ?? '');

        $info = [
            'exists' => false,
            'uuid' => (string) ($invoice->cfdi_uuid ?? ''),
            'stamp_date' => '',
            'sat_certificate' => '',
            'issuer_certificate' => '',
            'pac_rfc' => '',
            'sello_cfdi' => '',
            'sello_sat' => '',
            'cadena_sat' => '',
            'emission_date' => '',
            'expedition_place' => '',
            'issuer_regime' => '',
            'supplier_rfc' => (string) ($invoice->company?->tax_id ?? ''),
            'customer_rfc' => (string) ($invoice->customer_rfc ?? ''),
        ];

        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return $info;
        }

        $xml = Storage::disk('local')->get($path);

        if (! is_string($xml) || trim($xml) === '') {
            return $info;
        }

        $info['exists'] = true;

        $dom = new DOMDocument();

        libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();

        if (! $loaded) {
            return $info;
        }

        $comprobante = $dom->documentElement;

        if ($comprobante instanceof DOMElement) {
            $info['issuer_certificate'] = (string) $comprobante->getAttribute('NoCertificado');
            $info['emission_date'] = (string) $comprobante->getAttribute('Fecha');
            $info['expedition_place'] = (string) $comprobante->getAttribute('LugarExpedicion');
            $info['sello_cfdi'] = (string) $comprobante->getAttribute('Sello');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
        $xpath->registerNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

        $emisor = $xpath->query('//cfdi:Emisor')->item(0);

        if ($emisor instanceof DOMElement) {
            $info['supplier_rfc'] = (string) $emisor->getAttribute('Rfc');
            $info['issuer_regime'] = (string) $emisor->getAttribute('RegimenFiscal');
        }

        $receptor = $xpath->query('//cfdi:Receptor')->item(0);

        if ($receptor instanceof DOMElement) {
            $info['customer_rfc'] = (string) $receptor->getAttribute('Rfc');
        }

        $tfd = $xpath->query('//tfd:TimbreFiscalDigital')->item(0);

        if ($tfd instanceof DOMElement) {
            $uuid = strtoupper((string) $tfd->getAttribute('UUID'));

            $info['uuid'] = $uuid;
            $info['stamp_date'] = (string) $tfd->getAttribute('FechaTimbrado');
            $info['sat_certificate'] = (string) $tfd->getAttribute('NoCertificadoSAT');
            $info['pac_rfc'] = (string) $tfd->getAttribute('RfcProvCertif');
            $info['sello_cfdi'] = (string) $tfd->getAttribute('SelloCFD');
            $info['sello_sat'] = (string) $tfd->getAttribute('SelloSAT');
            $info['cadena_sat'] = $this->cadenaOriginalSat($tfd);
        }

        return $info;
    }

    private function cadenaOriginalSat(DOMElement $tfd): string
    {
        return '||'
            . '1.1|'
            . $tfd->getAttribute('UUID').'|'
            . $tfd->getAttribute('FechaTimbrado').'|'
            . $tfd->getAttribute('RfcProvCertif').'|'
            . $tfd->getAttribute('SelloCFD').'|'
            . $tfd->getAttribute('NoCertificadoSAT')
            . '||';
    }

    private function logoDataUri(object $company): string
    {
        foreach (['logo_path', 'logo_compact_path'] as $field) {
            $path = trim((string) ($company->{$field} ?? ''));

            if ($path === '') {
                continue;
            }

            foreach (['public', 'local'] as $disk) {
                try {
                    if (! Storage::disk($disk)->exists($path)) {
                        continue;
                    }

                    $contents = Storage::disk($disk)->get($path);

                    if (! is_string($contents) || $contents === '') {
                        continue;
                    }

                    $mime = $this->mimeFromPath($path);

                    return 'data:'.$mime.';base64,'.base64_encode($contents);
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return '';
    }

    private function mimeFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    private function branchLabel(Invoice $invoice, object $company): string
    {
        $source = trim((string) ($invoice->source_number ?? $invoice->source_type ?? ''));

        if ($source !== '') {
            return $source;
        }

        return trim((string) ($company->city ?? ''));
    }

    private function companyAddress(object $company): string
    {
        $parts = [];

        foreach ([
            'street',
            'address_line1',
            'address_line2',
            'neighborhood',
            'municipality',
            'city',
            'state',
            'fiscal_postal_code',
            'postal_code',
            'country',
        ] as $field) {
            $value = trim((string) ($company->{$field} ?? ''));

            if ($value !== '' && ! in_array($value, $parts, true)) {
                $parts[] = $value;
            }
        }

        return implode("\n", $parts);
    }

    private function customerAddress(Invoice $invoice): string
    {
        foreach ([
            'customer_address',
            'customer_fiscal_address',
            'billing_address',
            'shipping_address',
        ] as $field) {
            $value = trim((string) ($invoice->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return trim((string) ($invoice->customer_postal_code ?? ''));
    }

    private function cfdiUseLabel(Invoice $invoice): string
    {
        $code = trim((string) ($invoice->customer_cfdi_use_code ?? ''));

        if ($code === '') {
            return '';
        }

        $name = '';

        if (Schema::hasTable('sat_cfdi_uses')) {
            $row = DB::table('sat_cfdi_uses')->where('code', $code)->first();
            $name = trim((string) ($row->name ?? ''));
        }

        return $code.($name !== '' ? ' - '.$name : '');
    }

    private function paymentLabel(Invoice $invoice): string
    {
        $method = trim((string) ($invoice->payment_method_code ?? ''));
        $form = trim((string) ($invoice->payment_form_code ?? ''));

        if ($method === '') {
            $method = ((float) ($invoice->balance_total ?? 0) <= 0.000001) ? 'PUE' : 'PPD';
        }

        if ($form === '') {
            $form = '99';
        }

        // BEXIA_V5523Q2D_SAFE_CATALOG_LOOKUP
        // No asumimos que sat_billing_catalog_items tenga columna "catalog".
        // En algunas instalaciones usa catalog_id.
        $formName = $this->catalogItemName($form, ['forma_pago', 'payment_form', 'payment_method']);

        if ($formName === '') {
            $formName = match ($form) {
                '01' => 'Efectivo',
                '02' => 'Cheque nominativo',
                '03' => 'Transferencia electrónica de fondos',
                '04' => 'Tarjeta de crédito',
                '28' => 'Tarjeta de débito',
                '99' => 'Por Definir',
                default => '',
            };
        }

        return $method.' / '.$form.($formName !== '' ? ' - '.$formName : '');
    }

    private function catalogItemName(string $code, array $catalogAliases): string
    {
        $code = trim($code);

        if ($code === '' || ! Schema::hasTable('sat_billing_catalog_items')) {
            return '';
        }

        $columns = Schema::getColumnListing('sat_billing_catalog_items');

        if (! in_array('code', $columns, true)) {
            return '';
        }

        $query = DB::table('sat_billing_catalog_items')->where('code', $code);

        if (in_array('active', $columns, true)) {
            $query->where('active', true);
        }

        if (in_array('catalog', $columns, true)) {
            $query->whereIn('catalog', $catalogAliases);
        } elseif (in_array('catalog_code', $columns, true)) {
            $query->whereIn('catalog_code', $catalogAliases);
        } elseif (in_array('catalog_key', $columns, true)) {
            $query->whereIn('catalog_key', $catalogAliases);
        } elseif (in_array('catalog_id', $columns, true) && Schema::hasTable('sat_billing_catalogs')) {
            $catalogColumns = Schema::getColumnListing('sat_billing_catalogs');

            $ids = collect();

            foreach (['code', 'key', 'slug', 'name'] as $catalogColumn) {
                if (! in_array($catalogColumn, $catalogColumns, true)) {
                    continue;
                }

                $found = DB::table('sat_billing_catalogs')
                    ->whereIn($catalogColumn, $catalogAliases)
                    ->pluck('id');

                $ids = $ids->merge($found);
            }

            $ids = $ids->unique()->values();

            if ($ids->isNotEmpty()) {
                $query->whereIn('catalog_id', $ids->all());
            }
        }

        $row = $query->first();

        if (! $row) {
            return '';
        }

        foreach (['name', 'description', 'label', 'display_name'] as $field) {
            $value = trim((string) ($row->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function amountToWords(float $amount, string $currency = 'MXN'): string
    {
        $amount = round($amount, 2);
        $integer = (int) floor($amount);
        $cents = (int) round(($amount - $integer) * 100);

        if (class_exists(NumberFormatter::class)) {
            try {
                $fmt = new NumberFormatter('es_MX', NumberFormatter::SPELLOUT);
                $words = mb_strtoupper((string) $fmt->format($integer));

                return trim($words).' PESOS '.str_pad((string) $cents, 2, '0', STR_PAD_LEFT).'/100 '.$currency;
            } catch (Throwable) {
                // fallback below
            }
        }

        return 'TOTAL '.$this->formatMoney($amount).' '.$currency;
    }

    private function satQrUrl(Invoice $invoice, array $xmlInfo, float $total): string
    {
        $uuid = trim((string) ($xmlInfo['uuid'] ?? $invoice->cfdi_uuid ?? ''));

        if ($uuid === '') {
            return '';
        }

        $re = trim((string) ($xmlInfo['supplier_rfc'] ?? ''));
        $rr = trim((string) ($xmlInfo['customer_rfc'] ?? $invoice->customer_rfc ?? ''));
        $tt = number_format($total, 6, '.', '');
        $sello = trim((string) ($xmlInfo['sello_cfdi'] ?? ''));
        $fe = $sello !== '' ? substr($sello, -8) : '';

        return 'https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?'
            . http_build_query([
                're' => $re,
                'rr' => $rr,
                'tt' => $tt,
                'id' => $uuid,
                'fe' => $fe,
            ], '', '&', PHP_QUERY_RFC3986);
    }

    private function qrDataUri(Invoice $invoice, array $xmlInfo, float $total): string
    {
        $url = $this->satQrUrl($invoice, $xmlInfo, $total);

        if ($url === '') {
            return '';
        }

        if (! class_exists(\BaconQrCode\Writer::class)) {
            return '';
        }

        try {
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(220),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            );

            $writer = new \BaconQrCode\Writer($renderer);
            $svg = $writer->writeString($url);

            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        } catch (Throwable) {
            return '';
        }
    }

    private function formatMoney(float $amount): string
    {
        return '$ '.number_format($amount, 2, '.', ',');
    }
}
