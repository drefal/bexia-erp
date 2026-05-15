<?php

namespace App\Support\Billing;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class InvoiceCfdiStampService
{
    public function stamp(Invoice $invoice, ?User $user = null): array
    {
        $invoice->refresh();

        $validator = app(InvoiceCfdiValidator::class);
        $validation = $validator->validate($invoice, $user);

        if (! $validation['success']) {
            return [
                'success' => false,
                'status' => InvoiceCfdiValidator::STATUS_VALIDATION_ERROR,
                'message' => $validation['message'],
                'errors' => $validation['errors'] ?? [],
                'warnings' => $validation['warnings'] ?? [],
            ];
        }

        $company = Company::query()->find((int) $invoice->company_id);

        if (! $company) {
            return $this->stampError($invoice, $user, 'La factura no tiene empresa válida.');
        }

        try {
            if (blank($invoice->cfdi_xml_path ?? null) || ! Storage::disk('local')->exists((string) $invoice->cfdi_xml_path)) {
                $generated = app(InvoiceCfdiXmlBuilder::class)->generateSignedXml($invoice, $user);

                if (! $generated['success']) {
                    return $generated;
                }

                $invoice->refresh();
            }

            $xmlPath = (string) ($invoice->cfdi_xml_path ?? '');

            if ($xmlPath === '' || ! Storage::disk('local')->exists($xmlPath)) {
                return $this->stampError($invoice, $user, 'No existe XML CFDI firmado para timbrar.');
            }

            $xml = Storage::disk('local')->get($xmlPath);

            if (! is_string($xml) || trim($xml) === '') {
                return $this->stampError($invoice, $user, 'El XML CFDI firmado está vacío.');
            }

            DB::table('invoices')
                ->where('id', (int) $invoice->id)
                ->update([
                    'cfdi_status' => InvoiceCfdiValidator::STATUS_STAMPING,
                    'pac_error_message' => null,
                    'updated_at' => now(),
                ]);

            $result = app(SwPacClient::class)->stampSignedXml($company, $xml);

            if (! $result['success']) {
                return $this->stampError(
                    $invoice->refresh(),
                    $user,
                    (string) ($result['message'] ?? 'No se pudo timbrar con SW.'),
                    $result
                );
            }

            $basePath = 'invoices/cfdi/company_'.$invoice->company_id.'/invoice_'.$invoice->id;
            $stampedXmlPath = $basePath.'/cfdi_timbrado.xml';

            Storage::disk('local')->put($stampedXmlPath, (string) $result['xml']);

            DB::table('invoices')
                ->where('id', (int) $invoice->id)
                ->update([
                    'cfdi_status' => InvoiceCfdiValidator::STATUS_STAMPED,
                    'cfdi_uuid' => (string) $result['uuid'],
                    'cfdi_xml_path' => $stampedXmlPath,
                    'cfdi_stamped_at' => now(),
                    'pac_provider' => 'sw',
                    'pac_environment' => (string) ($result['environment'] ?? ''),
                    'pac_request_id' => $result['request_id'] ?? null,
                    'pac_error_message' => null,
                    'status' => 'issued',
                    'updated_at' => now(),
                ]);

            $invoice->refresh();

            /*
             * BEXIA_V5526R_MARK_GLOBAL_TICKETS_AFTER_STAMP
             */
            try {
                app(PosGlobalInvoiceService::class)->markStampedAfterCfdiStamp($invoice, $user?->id);
            } catch (Throwable $e) {
                report($e);
            }

            $invoice->refresh();

            $validator->audit($invoice, $user, [
                'action' => 'stamp',
                'status' => 'success',
                'pac_provider' => 'sw',
                'pac_environment' => (string) ($result['environment'] ?? ''),
                'request_id' => $result['request_id'] ?? null,
                'message' => 'CFDI timbrado correctamente con SW.',
                'request_meta' => [
                    'invoice_id' => (int) $invoice->id,
                    'invoice_number' => (string) ($invoice->number ?? ''),
                    'cfdi_series' => (string) ($invoice->cfdi_series ?? ''),
                    'cfdi_folio' => (string) ($invoice->cfdi_folio ?? ''),
                    'source_xml_path' => $xmlPath,
                ],
                'response_meta' => [
                    'uuid' => (string) $result['uuid'],
                    'stamped_xml_path' => $stampedXmlPath,
                    'http_status' => $result['http_status'] ?? null,
                    'endpoint' => $result['endpoint'] ?? null,
                    'environment' => $result['environment'] ?? null,
                    'response' => $result['response_meta'] ?? null,
                ],
            ]);

            /*
             * BEXIA_V5528B8_AUTO_EMAIL_AFTER_STAMP
             * Si la factura viene del portal /facturar, enviar automáticamente
             * PDF/XML/ZIP al correo capturado por el cliente.
             */
            $portalEmailResult = $this->sendPortalCfdiEmailAfterStamp($invoice->refresh(), $user);

            $successMessage = 'CFDI timbrado correctamente. UUID: '.$result['uuid'];

            if (($portalEmailResult['success'] ?? false) === true) {
                $successMessage .= ' También se envió el CFDI al correo del portal: '.($portalEmailResult['email'] ?? '');
            } elseif (($portalEmailResult['attempted'] ?? false) === true) {
                $successMessage .= ' No se pudo enviar automáticamente el correo del portal: '.($portalEmailResult['message'] ?? 'Error no especificado.');
            }

            return [
                'success' => true,
                'status' => InvoiceCfdiValidator::STATUS_STAMPED,
                'message' => $successMessage,
                'uuid' => (string) $result['uuid'],
                'xml_path' => $stampedXmlPath,
                'portal_email_result' => $portalEmailResult,
            ];
        } catch (Throwable $e) {
            return $this->stampError($invoice->refresh(), $user, $e->getMessage());
        }
    }


    /*
     * BEXIA_V5528B8_AUTO_EMAIL_HELPERS
     */
    private function sendPortalCfdiEmailAfterStamp(Invoice $invoice, ?User $user): array
    {
        $invoice->refresh();

        $metadata = $this->metadataArray($invoice->metadata ?? null);
        $email = strtolower(trim((string) data_get($metadata, 'portal_invoice_request.email', '')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'attempted' => false,
                'success' => false,
                'message' => 'La factura no tiene correo de portal válido.',
            ];
        }

        if ((bool) data_get($metadata, 'portal_invoice_request.auto_email_success', false)) {
            return [
                'attempted' => false,
                'success' => true,
                'already_sent' => true,
                'email' => $email,
                'message' => 'El correo automático del portal ya había sido enviado.',
            ];
        }

        $token = $this->ensurePortalDownloadTokenForInvoice($invoice);
        $links = $this->portalInvoiceDownloadLinks($invoice, $token);

        $message = app(InvoiceCfdiEmailService::class)->defaultMessage($invoice);
        $message .= "\n\n";
        $message .= "También puedes descargar tus archivos desde estas ligas seguras:\n";
        $message .= "PDF: " . ($links['pdf'] ?? '') . "\n";
        $message .= "XML: " . ($links['xml'] ?? '') . "\n";
        $message .= "ZIP: " . ($links['zip'] ?? '') . "\n";

        try {
            $result = app(InvoiceCfdiEmailService::class)
                ->send($invoice->refresh(), $email, $message);

            $success = (bool) ($result['success'] ?? false);
            $resultMessage = (string) ($result['message'] ?? '');

            $this->storePortalEmailResult($invoice->refresh(), [
                'attempted_at' => now()->toDateTimeString(),
                'success' => $success,
                'email' => $email,
                'message' => $resultMessage,
                'links' => $links,
                'user_id' => $user?->id,
            ]);

            return [
                'attempted' => true,
                'success' => $success,
                'email' => $email,
                'message' => $resultMessage,
                'links' => $links,
            ];
        } catch (Throwable $e) {
            report($e);

            $this->storePortalEmailResult($invoice->refresh(), [
                'attempted_at' => now()->toDateTimeString(),
                'success' => false,
                'email' => $email,
                'message' => $e->getMessage(),
                'links' => $links,
                'user_id' => $user?->id,
            ]);

            return [
                'attempted' => true,
                'success' => false,
                'email' => $email,
                'message' => $e->getMessage(),
                'links' => $links,
            ];
        }
    }

    private function ensurePortalDownloadTokenForInvoice(Invoice $invoice): string
    {
        $invoice->refresh();

        $metadata = $this->metadataArray($invoice->metadata ?? null);
        $token = (string) data_get($metadata, 'portal_invoice_request.download_token', '');

        if ($token === '') {
            $token = Str::random(64);

            data_set($metadata, 'portal_invoice_request.download_token', $token);
            $metadata['portal_download_token'] = $token;
            $metadata['portal_download_token_created_at'] = now()->toDateTimeString();

            DB::table('invoices')
                ->where('id', (int) $invoice->id)
                ->update([
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);

            $invoice->refresh();
        }

        return $token;
    }

    private function portalInvoiceDownloadLinks(Invoice $invoice, string $token): array
    {
        if ((int) ($invoice->id ?? 0) <= 0 || $token === '') {
            return [];
        }

        $links = [];

        foreach (['pdf', 'xml', 'zip'] as $type) {
            try {
                $links[$type] = route('public.invoice.download', [
                    'invoice' => (int) $invoice->id,
                    'type' => $type,
                    'token' => $token,
                ]);
            } catch (Throwable $e) {
                $links[$type] = url('/facturar/descargar/'.((int) $invoice->id).'/'.$type.'/'.$token);
            }
        }

        return $links;
    }

    private function storePortalEmailResult(Invoice $invoice, array $result): void
    {
        $metadata = $this->metadataArray($invoice->metadata ?? null);

        data_set($metadata, 'portal_invoice_request.auto_email_attempted_at', $result['attempted_at'] ?? now()->toDateTimeString());
        data_set($metadata, 'portal_invoice_request.auto_email_success', (bool) ($result['success'] ?? false));
        data_set($metadata, 'portal_invoice_request.auto_email_to', (string) ($result['email'] ?? ''));
        data_set($metadata, 'portal_invoice_request.auto_email_message', mb_substr((string) ($result['message'] ?? ''), 0, 1000));
        data_set($metadata, 'portal_invoice_request.auto_email_user_id', $result['user_id'] ?? null);
        data_set($metadata, 'portal_invoice_request.download_links', $result['links'] ?? []);

        DB::table('invoices')
            ->where('id', (int) $invoice->id)
            ->update([
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
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


    private function stampError(Invoice $invoice, ?User $user, string $message, array $meta = []): array
    {
        $safeMessage = mb_substr(trim($message), 0, 1000);

        DB::table('invoices')
            ->where('id', (int) $invoice->id)
            ->update([
                'cfdi_status' => InvoiceCfdiValidator::STATUS_STAMP_ERROR,
                'pac_error_message' => $safeMessage,
                'updated_at' => now(),
            ]);

        app(InvoiceCfdiValidator::class)->audit($invoice->refresh(), $user, [
            'action' => 'stamp',
            'status' => 'error',
            'pac_provider' => 'sw',
            'pac_environment' => (bool) ($invoice->company?->billing_pac_test_env ?? true) ? 'test' : 'production',
            'request_id' => $meta['request_id'] ?? null,
            'message' => $safeMessage,
            'request_meta' => [
                'invoice_id' => (int) $invoice->id,
                'invoice_number' => (string) ($invoice->number ?? ''),
                'cfdi_xml_path' => (string) ($invoice->cfdi_xml_path ?? ''),
            ],
            'response_meta' => [
                'error' => $safeMessage,
                'meta' => $meta['meta'] ?? $meta,
            ],
        ]);

        return [
            'success' => false,
            'status' => InvoiceCfdiValidator::STATUS_STAMP_ERROR,
            'message' => $safeMessage,
            'meta' => $meta,
        ];
    }
}
