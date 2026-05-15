<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class PublicInvoiceDownloadController extends Controller
{
    /*
     * BEXIA_V5528B8_PUBLIC_PORTAL_CFDI_DOWNLOAD
     * Descarga pública protegida por token para CFDI solicitados desde /facturar.
     */
    public function __invoke(Request $request, Invoice $invoice, string $type, string $token)
    {
        if (! in_array($type, ['pdf', 'xml', 'zip'], true)) {
            abort(404);
        }

        if ((string) ($invoice->cfdi_status ?? '') !== 'stamped') {
            abort(404, 'La factura todavía no está timbrada.');
        }

        $metadata = $this->metadataArray($invoice->metadata ?? null);
        $expectedToken = (string) data_get($metadata, 'portal_invoice_request.download_token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, (string) $token)) {
            abort(403, 'Liga inválida o expirada.');
        }

        return app(BillingInvoiceDownloadController::class)($invoice, $type);
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
