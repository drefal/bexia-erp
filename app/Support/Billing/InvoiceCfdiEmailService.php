<?php

namespace App\Support\Billing;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InvoiceCfdiEmailService
{
    public function defaultEmail(Invoice $invoice): string
    {
        foreach ([
            'customer_email',
            'contact_email',
            'billing_email',
            'email',
        ] as $field) {
            $value = trim((string) ($invoice->{$field} ?? ''));

            if ($this->looksLikeEmail($value)) {
                return $value;
            }
        }

        if (Schema::hasTable('contacts')) {
            foreach (['contact_id', 'customer_id', 'client_id'] as $field) {
                $id = (int) ($invoice->{$field} ?? 0);

                if ($id <= 0) {
                    continue;
                }

                $contact = DB::table('contacts')->where('id', $id)->first();

                if (! $contact) {
                    continue;
                }

                foreach ([
                    'billing_email',
                    'invoice_email',
                    'email',
                    'contact_email',
                ] as $emailField) {
                    $value = trim((string) ($contact->{$emailField} ?? ''));

                    if ($this->looksLikeEmail($value)) {
                        return $value;
                    }
                }
            }
        }

        return '';
    }

    public function send(Invoice $invoice, string $to, ?string $message = null): array
    {
        $invoice->refresh();

        if ((string) ($invoice->cfdi_status ?? '') !== 'stamped') {
            return [
                'success' => false,
                'message' => 'La factura todavía no está timbrada.',
            ];
        }

        if (blank($invoice->cfdi_uuid ?? null)) {
            return [
                'success' => false,
                'message' => 'La factura no tiene UUID.',
            ];
        }

        $recipients = $this->normalizeRecipients($to);

        if ($recipients === []) {
            return [
                'success' => false,
                'message' => 'El correo destino no es válido.',
            ];
        }

        $apiKey = trim((string) env('RESEND_API_KEY'));

        if ($apiKey === '') {
            return [
                'success' => false,
                'message' => 'RESEND_API_KEY está vacío. Configura Resend igual que en Salidas.',
            ];
        }

        $xmlPath = trim((string) ($invoice->cfdi_xml_path ?? ''));

        if ($xmlPath === '' || ! Storage::disk('local')->exists($xmlPath)) {
            return [
                'success' => false,
                'message' => 'No existe el XML timbrado.',
            ];
        }

        $pdfPath = $this->ensurePdf($invoice);

        if ($pdfPath === '' || ! Storage::disk('local')->exists($pdfPath)) {
            return [
                'success' => false,
                'message' => 'No existe el PDF de la factura.',
            ];
        }

        $xmlFullPath = Storage::disk('local')->path($xmlPath);
        $pdfFullPath = Storage::disk('local')->path($pdfPath);

        $xmlBinary = file_get_contents($xmlFullPath);
        $pdfBinary = file_get_contents($pdfFullPath);

        if ($xmlBinary === false || $xmlBinary === '') {
            return [
                'success' => false,
                'message' => 'No se pudo leer el XML timbrado.',
            ];
        }

        if ($pdfBinary === false || strlen($pdfBinary) < 200) {
            return [
                'success' => false,
                'message' => 'No se pudo leer el PDF o está vacío.',
            ];
        }

        $base = $this->baseFilename($invoice);
        $displayFolio = $this->displayFolio($invoice);
        $subject = $this->defaultSubject($invoice);

        $body = trim((string) ($message ?: ''));

        if ($body === '') {
            $body = $this->defaultMessage($invoice);
        }

        $html = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5;color:#111827;">'
            . nl2br(e($body))
            . '</div>';

        try {
            Log::info('CFDI_EMAIL_RESEND: antes de enviar', [
                'invoice_id' => $invoice->id,
                'uuid' => $invoice->cfdi_uuid,
                'to' => $recipients,
                'xml_bytes' => strlen($xmlBinary),
                'pdf_bytes' => strlen($pdfBinary),
                'from' => $this->resendFromAddress(),
            ]);

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(30)
                ->post('https://api.resend.com/emails', [
                    'from' => $this->resendFromAddress(),
                    'to' => $recipients,
                    'subject' => $subject,
                    'html' => $html,
                    'attachments' => [
                        [
                            'filename' => $base.'.xml',
                            'content' => base64_encode($xmlBinary),
                        ],
                        [
                            'filename' => $base.'.pdf',
                            'content' => base64_encode($pdfBinary),
                        ],
                    ],
                ]);

            Log::info('CFDI_EMAIL_RESEND: respuesta', [
                'invoice_id' => $invoice->id,
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
            ]);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'message' => 'Resend API error ['.$response->status().']: '.substr($response->body(), 0, 500),
                ];
            }
        } catch (Throwable $e) {
            Log::error('CFDI_EMAIL_RESEND: fallo', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'No se pudo enviar el correo: '.$e->getMessage(),
            ];
        }

        try {
            app(InvoiceCfdiValidator::class)->audit($invoice->refresh(), auth()->user(), [
                'action' => 'send_cfdi_email_resend',
                'status' => 'success',
                'pac_provider' => (string) ($invoice->pac_provider ?? 'sw'),
                'pac_environment' => (string) ($invoice->pac_environment ?? ''),
                'message' => 'CFDI enviado por correo con Resend.',
                'request_meta' => [
                    'to' => $recipients,
                    'invoice_id' => (int) $invoice->id,
                    'uuid' => (string) $invoice->cfdi_uuid,
                ],
                'response_meta' => [
                    'xml_path' => $xmlPath,
                    'pdf_path' => $pdfPath,
                    'transport' => 'resend',
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning('CFDI_EMAIL_RESEND: no se pudo auditar envío', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'success' => true,
            'message' => 'CFDI enviado por correo correctamente a '.implode(', ', $recipients).'.',
        ];
    }

    private function resendFromAddress(): string
    {
        $fromName = trim((string) (config('mail.from.name') ?: 'Notificaciones BexiaERP'));
        $fromEmail = trim((string) (config('mail.from.address') ?: 'notificaciones@bexiaerp.com'));

        if ($fromName === '') {
            return $fromEmail;
        }

        return sprintf('%s <%s>', $fromName, $fromEmail);
    }

    private function ensurePdf(Invoice $invoice): string
    {
        $path = trim((string) ($invoice->cfdi_pdf_path ?? ''));

        if ($path !== '' && Storage::disk('local')->exists($path)) {
            return $path;
        }

        $result = app(InvoicePdfBuilder::class)->generate($invoice, auth()->user());

        if (! ($result['success'] ?? false)) {
            return '';
        }

        $invoice->refresh();

        return trim((string) ($invoice->cfdi_pdf_path ?? ''));
    }


    public function defaultSubject(Invoice $invoice): string
    {
        return 'Factura de '.$this->companyDisplayName($invoice);
    }

    public function defaultMessage(Invoice $invoice): string
    {
        $date = $this->invoiceDateLabel($invoice);
        $place = $this->branchDisplayName($invoice);

        if ($place === '') {
            $place = $this->companyDisplayName($invoice);
        }

        $purchaseLine = 'Adjuntamos la factura de tu compra';

        if ($date !== '') {
            $purchaseLine .= ' del día '.$date;
        }

        if ($place !== '') {
            $purchaseLine .= ' en '.$place;
        }

        $purchaseLine .= '.';

        return implode("\n", [
            'Buen día.',
            '',
            $purchaseLine,
            '',
            'UUID: '.(string) ($invoice->cfdi_uuid ?? ''),
            'Total: $'.number_format((float) ($invoice->total ?? 0), 2),
            '',
            'Saludos.',
        ]);
    }

    private function companyDisplayName(Invoice $invoice): string
    {
        $companyId = (int) ($invoice->company_id ?? 0);

        if ($companyId > 0 && Schema::hasTable('companies')) {
            $company = DB::table('companies')->where('id', $companyId)->first();

            if ($company) {
                foreach ([
                    'commercial_name',
                    'display_name',
                    'name',
                    'business_name',
                    'legal_name',
                ] as $field) {
                    $value = trim((string) ($company->{$field} ?? ''));

                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return 'BexiaERP';
    }

    private function branchDisplayName(Invoice $invoice): string
    {
        /*
         * BEXIA_V5523R9B2D_BRANCH_THEN_COMPANY_EMAIL
         * Solo devuelve sucursal real.
         * No usa billing_series.name porque eso puede ser "Facturación Prueba DEV".
         * Si no encuentra sucursal, defaultMessage usará empresa.
         */

        foreach ([
            'branch_name',
            'sucursal_name',
            'store_name',
            'location_name',
            'warehouse_name',
        ] as $field) {
            $value = trim((string) ($invoice->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        $lookups = [];

        $directLookupMap = [
            'branch_id' => ['branches', 'company_branches', 'branch_offices'],
            'company_branch_id' => ['company_branches', 'branches', 'branch_offices'],
            'sucursal_id' => ['branches', 'company_branches', 'branch_offices'],
            'store_id' => ['stores', 'branches', 'company_branches'],
            'location_id' => ['locations', 'branches', 'company_branches'],
            'warehouse_id' => ['warehouses', 'branches', 'company_branches'],
        ];

        foreach ($directLookupMap as $field => $tables) {
            $id = (int) ($invoice->{$field} ?? 0);

            if ($id <= 0) {
                continue;
            }

            foreach ($tables as $table) {
                $lookups[] = [$table, $id, true];
            }
        }

        /*
         * PDV/caja: solo se usa para llegar a sucursal.
         * No usamos el nombre propio del PDV como sucursal.
         */
        $posLookupMap = [
            'point_of_sale_id' => ['points_of_sale', 'sale_points', 'pos_points', 'pos_terminals', 'cash_registers', 'pos_registers'],
            'sale_point_id' => ['sale_points', 'points_of_sale', 'pos_points', 'pos_terminals', 'cash_registers', 'pos_registers'],
            'pos_id' => ['pos_points', 'points_of_sale', 'sale_points', 'pos_terminals'],
            'pos_terminal_id' => ['pos_terminals', 'points_of_sale', 'sale_points'],
            'cash_register_id' => ['cash_registers', 'pos_registers', 'points_of_sale', 'sale_points'],
        ];

        foreach ($posLookupMap as $field => $tables) {
            $id = (int) ($invoice->{$field} ?? 0);

            if ($id <= 0) {
                continue;
            }

            foreach ($tables as $table) {
                $lookups[] = [$table, $id, false];
            }
        }

        /*
         * Facturación: billing_series solo debe apuntar a sucursal/PDV.
         * No se usa billing_series.name/display_name.
         */
        $billingSeriesId = (int) ($invoice->billing_series_id ?? 0);

        if ($billingSeriesId > 0 && Schema::hasTable('billing_series')) {
            $series = DB::table('billing_series')->where('id', $billingSeriesId)->first();

            if ($series) {
                foreach ([
                    'branch_name',
                    'sucursal_name',
                    'store_name',
                    'location_name',
                    'warehouse_name',
                ] as $field) {
                    $value = trim((string) ($series->{$field} ?? ''));

                    if ($value !== '') {
                        return $value;
                    }
                }

                foreach ($directLookupMap as $field => $tables) {
                    $id = (int) ($series->{$field} ?? 0);

                    if ($id <= 0) {
                        continue;
                    }

                    foreach ($tables as $table) {
                        $lookups[] = [$table, $id, true];
                    }
                }

                foreach ($posLookupMap as $field => $tables) {
                    $id = (int) ($series->{$field} ?? 0);

                    if ($id <= 0) {
                        continue;
                    }

                    foreach ($tables as $table) {
                        $lookups[] = [$table, $id, false];
                    }
                }
            }
        }

        foreach ($lookups as [$table, $id, $allowOwnName]) {
            $name = $this->lookupBranchDisplayName($table, $id, $allowOwnName);

            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    private function lookupBranchDisplayName(string $table, int $id, bool $allowOwnName = true): string
    {
        if ($id <= 0 || ! Schema::hasTable($table)) {
            return '';
        }

        $row = DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '';
        }

        foreach ([
            'branch_id' => ['branches', 'company_branches', 'branch_offices'],
            'company_branch_id' => ['company_branches', 'branches', 'branch_offices'],
            'sucursal_id' => ['branches', 'company_branches', 'branch_offices'],
            'store_id' => ['stores', 'branches', 'company_branches'],
            'location_id' => ['locations', 'branches', 'company_branches'],
            'warehouse_id' => ['warehouses', 'branches', 'company_branches'],
        ] as $field => $tables) {
            $linkedId = (int) ($row->{$field} ?? 0);

            if ($linkedId <= 0) {
                continue;
            }

            foreach ($tables as $linkedTable) {
                $linkedName = $this->lookupRowDisplayName($linkedTable, $linkedId);

                if ($linkedName !== '') {
                    return $linkedName;
                }
            }
        }

        if (! $allowOwnName) {
            return '';
        }

        return $this->rowDisplayName($row);
    }

    private function lookupRowDisplayName(string $table, int $id): string
    {
        if ($id <= 0 || ! Schema::hasTable($table)) {
            return '';
        }

        $row = DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '';
        }

        return $this->rowDisplayName($row);
    }

    private function rowDisplayName(object $row): string
    {
        foreach ([
            'commercial_name',
            'display_name',
            'name',
            'branch_name',
            'sucursal_name',
            'store_name',
            'warehouse_name',
            'location_name',
            'description',
            'city',
        ] as $field) {
            $value = trim((string) ($row->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function invoiceDateLabel(Invoice $invoice): string
    {
        foreach ([
            'date',
            'invoice_date',
            'issued_at',
            'created_at',
        ] as $field) {
            $value = $invoice->{$field} ?? null;

            if (! $value) {
                continue;
            }

            try {
                return \Carbon\Carbon::parse($value)->format('d/m/Y');
            } catch (Throwable $e) {
                continue;
            }
        }

        return '';
    }


    private function baseFilename(Invoice $invoice): string
    {
        $series = preg_replace('/[^A-Z0-9_-]/i', '', (string) ($invoice->cfdi_series ?: 'CFDI'));
        $folio = str_pad((string) ($invoice->cfdi_folio ?: $invoice->id), 5, '0', STR_PAD_LEFT);
        $uuid = strtoupper((string) ($invoice->cfdi_uuid ?: 'SIN_UUID'));

        return 'cfdi_'.$series.'_'.$folio.'_'.$uuid;
    }

    private function displayFolio(Invoice $invoice): string
    {
        $series = trim((string) ($invoice->cfdi_series ?? ''));
        $folio = trim((string) ($invoice->cfdi_folio ?? ''));

        if ($series !== '' || $folio !== '') {
            return trim($series.' '.$folio);
        }

        return (string) ($invoice->number ?? $invoice->id);
    }

    // BEXIA_V5523R7_MULTIPLE_EMAIL_DESTINATIONS
    private function normalizeRecipients(string $to): array
    {
        $parts = preg_split('/[;,\n\r\t ]+/', $to) ?: [];

        $recipients = [];

        foreach ($parts as $part) {
            $email = trim((string) $part);

            if ($this->looksLikeEmail($email)) {
                $recipients[] = $email;
            }
        }

        return array_values(array_unique($recipients));
    }

    private function looksLikeEmail(string $value): bool
    {
        return filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
    }
}
