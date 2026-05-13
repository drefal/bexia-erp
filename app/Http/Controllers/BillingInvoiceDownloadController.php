<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\Billing\InvoicePdfBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class BillingInvoiceDownloadController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice, string $type): BinaryFileResponse
    {

        // BEXIA_V5523T7_DOWNLOAD_CURRENT_CFDI_PDF_CALL
        if ((string) ($type ?? '') === 'pdf') {
            return $this->downloadCurrentPdfFile($invoice);
        }

        abort_unless(auth()->check(), 403);

        $type = strtolower(trim($type));

        abort_unless(in_array($type, ['xml', 'pdf', 'zip'], true), 404);

        return match ($type) {
            'xml' => $this->downloadXml($invoice),
            'pdf' => $this->downloadPdf($invoice),
            'zip' => $this->downloadZip($invoice),
        };
    }

    private function downloadXml(Invoice $invoice): BinaryFileResponse
    {
        $this->ensureStamped($invoice);

        $path = trim((string) ($invoice->cfdi_xml_path ?? ''));

        abort_if($path === '' || ! Storage::disk('local')->exists($path), 404, 'No existe el XML timbrado.');

        return response()->download(
            Storage::disk('local')->path($path),
            $this->baseFilename($invoice).'.xml',
            ['Content-Type' => 'application/xml']
        );
    }

    private function downloadPdf(Invoice $invoice): BinaryFileResponse
    {
        $this->ensureStamped($invoice);

        /*
         * BEXIA_V5523T8_DOWNLOAD_PDF_CURRENT_PATH
         * Usar exclusivamente el PDF vigente de invoices.cfdi_pdf_path.
         * Ese es el mismo archivo que ya usa correctamente el correo.
         */
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

    private function downloadZip(Invoice $invoice): BinaryFileResponse
    {
        $this->ensureStamped($invoice);

        abort_unless(class_exists(ZipArchive::class), 500, 'ZipArchive no está disponible en PHP.');

        $xmlPath = trim((string) ($invoice->cfdi_xml_path ?? ''));

        abort_if($xmlPath === '' || ! Storage::disk('local')->exists($xmlPath), 404, 'No existe el XML timbrado.');

        $pdfPath = $this->ensurePdf($invoice);

        $base = $this->baseFilename($invoice);
        $zipPath = 'invoices/cfdi/company_'.$invoice->company_id.'/invoice_'.$invoice->id.'/'.$base.'.zip';

        Storage::disk('local')->makeDirectory(dirname($zipPath));

        $zipFullPath = Storage::disk('local')->path($zipPath);

        $zip = new ZipArchive();

        if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'No se pudo crear el ZIP CFDI.');
        }

        $zip->addFile(Storage::disk('local')->path($xmlPath), $base.'.xml');
        $zip->addFile(Storage::disk('local')->path($pdfPath), $base.'.pdf');
        $zip->close();

        return response()->download(
            $zipFullPath,
            $base.'.zip',
            ['Content-Type' => 'application/zip']
        );
    }

    private function ensurePdf(Invoice $invoice): string
    {
        $path = trim((string) ($invoice->cfdi_pdf_path ?? ''));

        if ($path !== '' && Storage::disk('local')->exists($path)) {
            return $path;
        }

        $result = app(InvoicePdfBuilder::class)->generate($invoice, auth()->user());

        abort_if(! ($result['success'] ?? false), 500, $result['message'] ?? 'No se pudo generar el PDF.');

        $invoice->refresh();

        $path = trim((string) ($invoice->cfdi_pdf_path ?? ''));

        abort_if($path === '' || ! Storage::disk('local')->exists($path), 500, 'El PDF no quedó disponible.');

        return $path;
    }

    private function ensureStamped(Invoice $invoice): void
    {
        abort_unless((string) ($invoice->cfdi_status ?? '') === 'stamped', 422, 'La factura todavía no está timbrada.');
        abort_if(blank($invoice->cfdi_uuid ?? null), 422, 'La factura no tiene UUID.');
    }

    private function baseFilename(Invoice $invoice): string
    {
        $series = preg_replace('/[^A-Z0-9_-]/i', '', (string) ($invoice->cfdi_series ?: 'CFDI'));
        $folio = str_pad((string) ($invoice->cfdi_folio ?: $invoice->id), 5, '0', STR_PAD_LEFT);
        $uuid = strtoupper((string) ($invoice->cfdi_uuid ?: 'SIN_UUID'));

        return 'cfdi_'.$series.'_'.$folio.'_'.$uuid;
    }

    private function downloadCurrentPdfFile(Invoice $invoice): BinaryFileResponse
    {
        /*
         * BEXIA_V5523T7_DOWNLOAD_CURRENT_CFDI_PDF
         * La descarga PDF debe usar el PDF vigente guardado en invoices.cfdi_pdf_path.
         * Este archivo ya es el mismo que usa el correo CFDI.
         */
        $invoice->refresh();

        $path = (string) ($invoice->cfdi_pdf_path ?? '');

        if ($path === '') {
            abort(404, 'La factura no tiene PDF CFDI generado.');
        }

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'El PDF CFDI no existe en almacenamiento.');
        }

        $fullPath = Storage::disk('local')->path($path);

        if (! is_readable($fullPath)) {
            abort(500, 'El PDF CFDI existe pero no es legible por la aplicación.');
        }

        $filename = $this->baseFilename($invoice).'.pdf';

        return response()
            ->download($fullPath, $filename, [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
    }


}
