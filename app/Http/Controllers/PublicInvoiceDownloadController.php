<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class PublicInvoiceDownloadController extends Controller
{
    /*
     * BEXIA_V5528C16_PUBLIC_CFDI_DOWNLOAD_DIRECT_PROD
     * Descarga pública protegida por token para CFDI solicitados desde /facturar.
     * No depende del controlador interno de admin ni de auth().
     */
    public function __invoke(Request $request, Invoice $invoice, string $type, string $token): BinaryFileResponse
    {
        $type = strtolower(trim($type));

        if (! in_array($type, ['pdf', 'xml', 'zip'], true)) {
            abort(404);
        }

        $this->ensureTokenIsValid($invoice, $token);
        $this->ensureStamped($invoice);

        return match ($type) {
            'pdf' => $this->downloadPdf($invoice),
            'xml' => $this->downloadXml($invoice),
            'zip' => $this->downloadZip($invoice),
        };
    }

    private function ensureTokenIsValid(Invoice $invoice, string $token): void
    {
        $metadata = $this->metadataArray($invoice->metadata ?? null);
        $expectedToken = (string) data_get($metadata, 'portal_invoice_request.download_token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, (string) $token)) {
            abort(403, 'Liga inválida o expirada.');
        }
    }

    private function ensureStamped(Invoice $invoice): void
    {
        abort_unless((string) ($invoice->cfdi_status ?? '') === 'stamped', 404, 'La factura todavía no está timbrada.');
        abort_if(blank($invoice->cfdi_uuid ?? null), 404, 'La factura no tiene UUID.');
    }

    private function downloadPdf(Invoice $invoice): BinaryFileResponse
    {
        $invoice->refresh();

        $path = trim((string) ($invoice->cfdi_pdf_path ?? ''));

        abort_if($path === '', 404, 'La factura no tiene PDF CFDI generado.');
        abort_if(! Storage::disk('local')->exists($path), 404, 'No existe el PDF CFDI.');

        $fullPath = Storage::disk('local')->path($path);

        abort_if(! is_readable($fullPath), 500, 'El PDF CFDI existe pero no es legible por la aplicación.');

        return response()->download(
            $fullPath,
            $this->baseFilename($invoice).'.pdf',
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    private function downloadXml(Invoice $invoice): BinaryFileResponse
    {
        $invoice->refresh();

        $path = trim((string) ($invoice->cfdi_xml_path ?? ''));

        abort_if($path === '', 404, 'La factura no tiene XML CFDI generado.');
        abort_if(! Storage::disk('local')->exists($path), 404, 'No existe el XML CFDI.');

        $fullPath = Storage::disk('local')->path($path);

        abort_if(! is_readable($fullPath), 500, 'El XML CFDI existe pero no es legible por la aplicación.');

        return response()->download(
            $fullPath,
            $this->baseFilename($invoice).'.xml',
            [
                'Content-Type' => 'application/xml',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    private function downloadZip(Invoice $invoice): BinaryFileResponse
    {
        $invoice->refresh();

        abort_unless(class_exists(ZipArchive::class), 500, 'ZipArchive no está disponible en PHP.');

        $xmlPath = trim((string) ($invoice->cfdi_xml_path ?? ''));
        $pdfPath = trim((string) ($invoice->cfdi_pdf_path ?? ''));

        abort_if($xmlPath === '' || ! Storage::disk('local')->exists($xmlPath), 404, 'No existe el XML CFDI.');
        abort_if($pdfPath === '' || ! Storage::disk('local')->exists($pdfPath), 404, 'No existe el PDF CFDI.');

        $xmlFullPath = Storage::disk('local')->path($xmlPath);
        $pdfFullPath = Storage::disk('local')->path($pdfPath);

        abort_if(! is_readable($xmlFullPath), 500, 'El XML CFDI existe pero no es legible.');
        abort_if(! is_readable($pdfFullPath), 500, 'El PDF CFDI existe pero no es legible.');

        $base = $this->baseFilename($invoice);
        $zipPath = 'invoices/cfdi/company_'.$invoice->company_id.'/invoice_'.$invoice->id.'/'.$base.'.zip';

        Storage::disk('local')->makeDirectory(dirname($zipPath));

        $zipFullPath = Storage::disk('local')->path($zipPath);

        $zip = new ZipArchive();

        if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'No se pudo crear el ZIP CFDI.');
        }

        $zip->addFile($xmlFullPath, $base.'.xml');
        $zip->addFile($pdfFullPath, $base.'.pdf');
        $zip->close();

        return response()->download(
            $zipFullPath,
            $base.'.zip',
            [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    private function baseFilename(Invoice $invoice): string
    {
        $series = preg_replace('/[^A-Z0-9_-]/i', '', (string) ($invoice->cfdi_series ?: 'CFDI'));
        $folio = str_pad((string) ($invoice->cfdi_folio ?: $invoice->id), 5, '0', STR_PAD_LEFT);
        $uuid = strtoupper((string) ($invoice->cfdi_uuid ?: 'SIN_UUID'));

        return 'cfdi_'.$series.'_'.$folio.'_'.$uuid;
    }

    private function metadataArray(mixed $metadata): array
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
}
