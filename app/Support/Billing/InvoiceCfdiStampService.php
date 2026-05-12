<?php

namespace App\Support\Billing;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

            return [
                'success' => true,
                'status' => InvoiceCfdiValidator::STATUS_STAMPED,
                'message' => 'CFDI timbrado correctamente. UUID: '.$result['uuid'],
                'uuid' => (string) $result['uuid'],
                'xml_path' => $stampedXmlPath,
            ];
        } catch (Throwable $e) {
            return $this->stampError($invoice->refresh(), $user, $e->getMessage());
        }
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
