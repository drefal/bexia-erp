<?php

namespace App\Http\Controllers;

use App\Filament\Resources\PosTicketResource;
use App\Support\InternalInvoiceBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PublicInvoicePortalController extends Controller
{
    /*
     * BEXIA_V5528B_PUBLIC_INVOICE_PORTAL_DRAFT_INVOICE
     * Portal público:
     * - valida ticket por folio + total
     * - captura datos fiscales
     * - crea factura interna en borrador
     * No timbra automáticamente todavía.
     */
    public function show(Request $request)
    {
        return view('pos.invoice-placeholder', [
            'ticket' => trim((string) $request->query('ticket', '')),
            'total' => trim((string) $request->query('total', '')),
            'result' => null,
            'fiscalData' => $this->emptyFiscalData(),
            'taxRegimeOptions' => $this->taxRegimeOptions(),
            'cfdiUseOptions' => $this->cfdiUseOptions(),
        ]);
    }

    public function validateTicket(Request $request)
    {
        $ticket = trim((string) $request->input('ticket', ''));
        $totalInput = trim((string) $request->input('total', ''));

        $result = $this->buildValidationResult($ticket, $totalInput);

        return view('pos.invoice-placeholder', [
            'ticket' => $ticket,
            'total' => $totalInput,
            'result' => $result,
            'fiscalData' => $this->emptyFiscalData(),
            'taxRegimeOptions' => $this->taxRegimeOptions(),
            'cfdiUseOptions' => $this->cfdiUseOptions(),
        ]);
    }

    public function requestInvoice(Request $request)
    {
        $ticket = trim((string) $request->input('ticket', ''));
        $totalInput = trim((string) $request->input('total', ''));

        $fiscalData = [
            'rfc' => strtoupper(trim((string) $request->input('rfc', ''))),
            'fiscal_name' => trim((string) $request->input('fiscal_name', '')),
            'postal_code' => trim((string) $request->input('postal_code', '')),
            'tax_regime_code' => trim((string) $request->input('tax_regime_code', '')),
            'cfdi_use_code' => trim((string) $request->input('cfdi_use_code', '')),
            'email' => strtolower(trim((string) $request->input('email', ''))),
        ];

        /*
         * BEXIA_V5528B1_PUBLIC_GENERAL_PORTAL_NORMALIZATION
         * Público general debe facturarse con 616/S01 y no debe contaminar el contacto base con correos de clientes.
         */
        $fiscalData = $this->normalizePortalFiscalData($fiscalData);

        $result = $this->buildValidationResult($ticket, $totalInput);

        if (! ($result['ok'] ?? false)) {
            return view('pos.invoice-placeholder', [
                'ticket' => $ticket,
                'total' => $totalInput,
                'result' => $result,
                'fiscalData' => $fiscalData,
                'taxRegimeOptions' => $this->taxRegimeOptions(),
                'cfdiUseOptions' => $this->cfdiUseOptions(),
            ]);
        }

        $fiscalError = $this->validateFiscalData($fiscalData);

        if ($fiscalError !== null) {
            return view('pos.invoice-placeholder', [
                'ticket' => $ticket,
                'total' => $totalInput,
                'result' => [
                    'ok' => false,
                    'type' => 'error',
                    'title' => 'Revisa los datos fiscales',
                    'message' => $fiscalError,
                    'show_fiscal_form' => true,
                    'order_id' => $result['order_id'] ?? null,
                    'order_number' => $result['order_number'] ?? $ticket,
                    'order_total' => $result['order_total'] ?? null,
                    'fiscal_label' => $result['fiscal_label'] ?? null,
                ],
                'fiscalData' => $fiscalData,
                'taxRegimeOptions' => $this->taxRegimeOptions(),
                'cfdiUseOptions' => $this->cfdiUseOptions(),
            ]);
        }

        try {
            $invoiceResult = $this->createPortalInvoiceDraft((int) $result['order_id'], $fiscalData);

            return view('pos.invoice-placeholder', [
                'ticket' => $ticket,
                'total' => $totalInput,
                'result' => [
                    'ok' => true,
                    'type' => 'success',
                    'title' => 'Solicitud de factura recibida',
                    'message' => 'Creamos tu factura en borrador. El equipo podrá revisarla, timbrarla y enviarla al correo capturado.',
                    'order_id' => $result['order_id'] ?? null,
                    'order_number' => $result['order_number'] ?? $ticket,
                    'order_total' => $result['order_total'] ?? null,
                    'fiscal_label' => 'Factura interna en borrador',
                    'invoice_id' => $invoiceResult['invoice_id'] ?? null,
                    'invoice_number' => $invoiceResult['invoice_number'] ?? null,
                    'email' => $fiscalData['email'],
                    'completed' => true,
                ],
                'fiscalData' => $fiscalData,
                'taxRegimeOptions' => $this->taxRegimeOptions(),
                'cfdiUseOptions' => $this->cfdiUseOptions(),
            ]);
        } catch (\Throwable $e) {
            return view('pos.invoice-placeholder', [
                'ticket' => $ticket,
                'total' => $totalInput,
                'result' => [
                    'ok' => false,
                    'type' => 'error',
                    'title' => 'No se pudo crear la factura',
                    'message' => $e->getMessage(),
                    'show_fiscal_form' => true,
                    'order_id' => $result['order_id'] ?? null,
                    'order_number' => $result['order_number'] ?? $ticket,
                    'order_total' => $result['order_total'] ?? null,
                    'fiscal_label' => $result['fiscal_label'] ?? null,
                ],
                'fiscalData' => $fiscalData,
                'taxRegimeOptions' => $this->taxRegimeOptions(),
                'cfdiUseOptions' => $this->cfdiUseOptions(),
            ]);
        }
    }

    protected function buildValidationResult(string $ticket, string $totalInput): array
    {
        if ($ticket === '') {
            return $this->error('Captura el folio del ticket.');
        }

        if ($totalInput === '' || ! is_numeric(str_replace(',', '', $totalInput))) {
            return $this->error('Captura el total del ticket.');
        }

        if (! Schema::hasTable('pos_orders')) {
            return $this->error('El módulo de tickets PDV no está disponible.');
        }

        $totalInput = (float) str_replace(',', '', $totalInput);

        $order = DB::table('pos_orders')
            ->where('number', $ticket)
            ->orderByDesc('id')
            ->first();

        if (! $order) {
            return $this->error('No encontramos un ticket con ese folio.');
        }

        $orderTotal = round((float) ($order->total ?? 0), 2);
        $givenTotal = round($totalInput, 2);

        if (abs($orderTotal - $givenTotal) > 0.01) {
            return $this->error('El total capturado no coincide con el ticket.');
        }

        $status = (string) ($order->status ?? '');

        if ($status === 'pending_payment') {
            return $this->error('Este ticket todavía está pendiente de pago.');
        }

        if (in_array($status, ['cancelled', 'canceled', 'cancelled_test'], true)) {
            return $this->error('Este ticket está cancelado y no puede facturarse.');
        }

        if ($status === 'returned') {
            return $this->error('Este ticket tiene devolución y no puede facturarse desde el portal.');
        }

        if ($status !== 'paid') {
            return $this->error('Este ticket no está en estado pagado.');
        }

        $fiscalStatus = PosTicketResource::fiscalStatus($order);
        $fiscalLabel = PosTicketResource::fiscalStatusLabel($fiscalStatus);

        if (! PosTicketResource::canCreateIndividualInvoiceFromTicket($order)) {
            /*
             * BEXIA_V5528B8_PORTAL_DOWNLOAD_LINKS
             * Si el ticket ya fue solicitado desde el portal, mostrar estado real:
             * - en proceso si sigue en borrador
             * - ligas de descarga si ya quedó timbrado
             */
            $portalInvoiceResult = $this->portalInvoiceResultForOrder(
                $order,
                $ticket,
                $orderTotal,
                $status,
                $fiscalStatus,
                $fiscalLabel
            );

            if ($portalInvoiceResult !== null) {
                return $portalInvoiceResult;
            }

            return [
                'ok' => false,
                'type' => 'blocked',
                'title' => 'Ticket no disponible para facturación',
                'message' => 'Estado fiscal: ' . $fiscalLabel . '. Si ya fue facturado, revisa la factura relacionada o solicita apoyo en tienda.',
                'ticket' => $ticket,
                'order_id' => (int) $order->id,
                'order_number' => (string) ($order->number ?? ''),
                'order_total' => $orderTotal,
                'status' => $status,
                'fiscal_status' => $fiscalStatus,
                'fiscal_label' => $fiscalLabel,
            ];
        }

        return [
            'ok' => true,
            'type' => 'eligible',
            'title' => 'Ticket encontrado',
            'message' => 'El ticket es elegible para facturación. Captura tus datos fiscales para crear la factura en borrador.',
            'ticket' => $ticket,
            'order_id' => (int) $order->id,
            'order_number' => (string) ($order->number ?? ''),
            'order_total' => $orderTotal,
            'status' => $status,
            'fiscal_status' => $fiscalStatus,
            'fiscal_label' => $fiscalLabel,
            'show_fiscal_form' => true,
        ];
    }

    protected function createPortalInvoiceDraft(int $orderId, array $fiscalData): array
    {
        if (! Schema::hasTable('pos_orders')) {
            throw new \RuntimeException('No existe tabla de tickets.');
        }

        return DB::transaction(function () use ($orderId, $fiscalData): array {
            $order = DB::table('pos_orders')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new \RuntimeException('No se encontró el ticket.');
            }

            if (! PosTicketResource::canCreateIndividualInvoiceFromTicket($order)) {
                throw new \RuntimeException('Este ticket ya no está disponible para facturación.');
            }

            $contactId = $this->createOrUpdateFiscalContact($order, $fiscalData);

            if ($contactId && Schema::hasColumn('pos_orders', 'customer_id')) {
                DB::table('pos_orders')
                    ->where('id', $orderId)
                    ->update([
                        'customer_id' => $contactId,
                        'updated_at' => now(),
                    ]);
            }

            $invoiceId = app(InternalInvoiceBuilder::class)->createFromPosOrder($orderId, null);

            $this->updateInvoiceFiscalSnapshot($invoiceId, $contactId, $fiscalData);

            /*
             * BEXIA_V5528B8_ENSURE_PORTAL_DOWNLOAD_TOKEN_ON_DRAFT
             */
            $this->ensurePortalDownloadTokenForInvoice($invoiceId);

            $invoice = DB::table('invoices')->where('id', $invoiceId)->first();

            $order = DB::table('pos_orders')->where('id', $orderId)->first();
            $metadata = $this->metadataArray($order->metadata ?? null);

            $metadata['billing_status'] = 'portal_invoice_draft';
            $metadata['portal_invoice_id'] = $invoiceId;
            $metadata['portal_invoice_number'] = (string) ($invoice->number ?? '');
            $metadata['portal_invoice_requested_at'] = now()->toDateTimeString();
            $metadata['portal_invoice_email'] = $fiscalData['email'];
            $metadata['portal_invoice_rfc'] = $fiscalData['rfc'];

            DB::table('pos_orders')
                ->where('id', $orderId)
                ->update([
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);

            return [
                'invoice_id' => (int) $invoiceId,
                'invoice_number' => (string) ($invoice->number ?? ('#' . $invoiceId)),
            ];
        });
    }

    protected function createOrUpdateFiscalContact(object $order, array $data): ?int
    {
        if (! Schema::hasTable('contacts')) {
            return null;
        }

        $columns = Schema::getColumnListing('contacts');
        $has = fn (string $column): bool => in_array($column, $columns, true);

        $companyId = (int) ($order->company_id ?? 0);
        $rfc = strtoupper((string) $data['rfc']);

        $query = DB::table('contacts')->where('rfc', $rfc);

        if ($companyId > 0 && $has('company_id')) {
            $query->where('company_id', $companyId);
        }

        $contact = $query->orderByDesc('id')->first();

        $payload = [];

        foreach ([
            'company_id' => $companyId > 0 ? $companyId : null,
            'name' => $data['fiscal_name'],
            'fiscal_name' => $data['fiscal_name'],
            'rfc' => $rfc,
            'email' => $this->isPublicGeneralRfc($rfc) ? null : $data['email'],
            'fiscal_zip' => $data['postal_code'],
            'postal_code' => $data['postal_code'],
            'sat_tax_regime_code' => $data['tax_regime_code'],
            'customer_cfdi_use_code' => $data['cfdi_use_code'],
            'sat_cfdi_use_code' => $data['cfdi_use_code'],
            'updated_at' => now(),
        ] as $column => $value) {
            if ($has($column)) {
                $payload[$column] = $value;
            }
        }

        if ($contact) {
            DB::table('contacts')
                ->where('id', $contact->id)
                ->update($payload);

            return (int) $contact->id;
        }

        if ($has('created_at')) {
            $payload['created_at'] = now();
        }

        return (int) DB::table('contacts')->insertGetId($payload);
    }

    protected function updateInvoiceFiscalSnapshot(int $invoiceId, ?int $contactId, array $data): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $invoice = DB::table('invoices')->where('id', $invoiceId)->first();

        if (! $invoice) {
            return;
        }

        $columns = Schema::getColumnListing('invoices');
        $has = fn (string $column): bool => in_array($column, $columns, true);

        $metadata = $this->metadataArray($invoice->metadata ?? null);
        $metadata['source'] = $metadata['source'] ?? 'internal_invoice_builder';
        $metadata['portal_invoice_request'] = [
            'rfc' => $data['rfc'],
            'fiscal_name' => $data['fiscal_name'],
            'postal_code' => $data['postal_code'],
            'tax_regime_code' => $data['tax_regime_code'],
            'cfdi_use_code' => $data['cfdi_use_code'],
            'email' => $data['email'],
            'requested_at' => now()->toDateTimeString(),
        ];

        $payload = [
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ];

        foreach ([
            'contact_id' => $contactId,
            'customer_name' => $data['fiscal_name'],
            'customer_fiscal_name' => $data['fiscal_name'],
            'customer_rfc' => $data['rfc'],
            'customer_tax_regime_code' => $data['tax_regime_code'],
            'customer_cfdi_use_code' => $data['cfdi_use_code'],
            'customer_postal_code' => $data['postal_code'],
            'customer_email' => $data['email'],
        ] as $column => $value) {
            if ($has($column)) {
                $payload[$column] = $value;
            }
        }

        DB::table('invoices')
            ->where('id', $invoiceId)
            ->update($payload);
    }


    protected function normalizePortalFiscalData(array $data): array
    {
        /*
         * BEXIA_V5528B1_NORMALIZE_PORTAL_FISCAL_DATA
         */
        $data['rfc'] = strtoupper(trim((string) ($data['rfc'] ?? '')));

        if ($data['rfc'] === 'XAXX010101000') {
            $data['fiscal_name'] = 'PUBLICO EN GENERAL';
            $data['tax_regime_code'] = '616';
            $data['cfdi_use_code'] = 'S01';
        }

        return $data;
    }

    protected function isPublicGeneralRfc(string $rfc): bool
    {
        return strtoupper(trim($rfc)) === 'XAXX010101000';
    }


    protected function validateFiscalData(array $data): ?string
    {
        if ($data['rfc'] === '') {
            return 'Captura el RFC.';
        }

        if (! preg_match('/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/u', $data['rfc'])) {
            return 'El RFC no tiene un formato válido.';
        }

        if ($data['fiscal_name'] === '') {
            return 'Captura la razón social o nombre fiscal.';
        }

        if (! preg_match('/^[0-9]{5}$/', $data['postal_code'])) {
            return 'El código postal fiscal debe tener 5 dígitos.';
        }

        if ($data['tax_regime_code'] === '') {
            return 'Selecciona el régimen fiscal.';
        }

        if ($data['cfdi_use_code'] === '') {
            return 'Selecciona el uso CFDI.';
        }

        if ($data['email'] === '' || ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Captura un correo válido.';
        }

        return null;
    }

    protected function emptyFiscalData(): array
    {
        return [
            'rfc' => '',
            'fiscal_name' => '',
            'postal_code' => '',
            'tax_regime_code' => '',
            'cfdi_use_code' => '',
            'email' => '',
        ];
    }

    protected function error(string $message): array
    {
        return [
            'ok' => false,
            'type' => 'error',
            'title' => 'No se pudo validar el ticket',
            'message' => $message,
        ];
    }

    protected function metadataArray($metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_object($metadata)) {
            return json_decode(json_encode($metadata), true) ?: [];
        }

        if ($metadata === null || $metadata === '') {
            return [];
        }

        $decoded = json_decode((string) $metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function taxRegimeOptions(): array
    {
        return [
            '601' => '601 - General de Ley Personas Morales',
            '603' => '603 - Personas Morales con Fines no Lucrativos',
            '605' => '605 - Sueldos y Salarios',
            '606' => '606 - Arrendamiento',
            '612' => '612 - Personas Físicas con Actividades Empresariales y Profesionales',
            '616' => '616 - Sin obligaciones fiscales',
            '621' => '621 - Incorporación Fiscal',
            '626' => '626 - Régimen Simplificado de Confianza',
        ];
    }

    protected function cfdiUseOptions(): array
    {
        return [
            'G01' => 'G01 - Adquisición de mercancías',
            'G03' => 'G03 - Gastos en general',
            'I01' => 'I01 - Construcciones',
            'I02' => 'I02 - Mobiliario y equipo de oficina',
            'I04' => 'I04 - Equipo de cómputo',
            'D01' => 'D01 - Honorarios médicos',
            'D02' => 'D02 - Gastos médicos por incapacidad',
            'D04' => 'D04 - Donativos',
            'D10' => 'D10 - Pagos por servicios educativos',
            'S01' => 'S01 - Sin efectos fiscales',
        ];
    }

    /*
     * BEXIA_V5528B8_PORTAL_DOWNLOAD_HELPERS
     */
    protected function portalInvoiceResultForOrder(
        object $order,
        string $ticket,
        float $orderTotal,
        string $status,
        string $fiscalStatus,
        string $fiscalLabel
    ): ?array {
        $orderMetadata = $this->metadataArray($order->metadata ?? null);
        $invoiceId = (int) ($orderMetadata['portal_invoice_id'] ?? 0);

        if ($invoiceId <= 0) {
            return null;
        }

        $invoice = DB::table('invoices')->where('id', $invoiceId)->first();

        if (! $invoice) {
            return null;
        }

        $invoiceMetadata = $this->metadataArray($invoice->metadata ?? null);
        $portalEmail = (string) (
            data_get($invoiceMetadata, 'portal_invoice_request.email')
            ?: ($orderMetadata['portal_invoice_email'] ?? '')
        );

        $base = [
            'ticket' => $ticket,
            'order_id' => (int) $order->id,
            'order_number' => (string) ($order->number ?? ''),
            'order_total' => $orderTotal,
            'status' => $status,
            'fiscal_status' => $fiscalStatus,
            'invoice_id' => $invoiceId,
            'invoice_number' => (string) ($invoice->number ?? ('#' . $invoiceId)),
            'email' => $portalEmail,
        ];

        if ((string) ($invoice->cfdi_status ?? '') === 'stamped' && filled($invoice->cfdi_uuid ?? null)) {
            $token = $this->ensurePortalDownloadTokenForInvoice($invoiceId);

            return array_merge($base, [
                'ok' => true,
                'type' => 'success',
                'title' => 'Tu factura está lista',
                'message' => 'Tu CFDI ya fue timbrado. También enviamos los archivos al correo registrado.',
                'fiscal_label' => 'CFDI timbrado',
                'cfdi_uuid' => (string) ($invoice->cfdi_uuid ?? ''),
                'download_links' => $this->portalInvoiceDownloadLinks($invoiceId, $token),
                'completed' => true,
            ]);
        }

        return array_merge($base, [
            'ok' => false,
            'type' => 'blocked',
            'title' => 'Solicitud de factura en proceso',
            'message' => 'Tu solicitud ya fue recibida. Cuando la factura quede timbrada, enviaremos los archivos al correo capturado y aquí aparecerán las ligas de descarga.',
            'fiscal_label' => 'Factura en revisión',
            'completed' => true,
        ]);
    }

    protected function ensurePortalDownloadTokenForInvoice(int $invoiceId): string
    {
        $invoice = DB::table('invoices')->where('id', $invoiceId)->first();

        if (! $invoice) {
            return '';
        }

        $metadata = $this->metadataArray($invoice->metadata ?? null);
        $token = (string) data_get($metadata, 'portal_invoice_request.download_token', '');

        if ($token === '') {
            $token = Str::random(64);

            data_set($metadata, 'portal_invoice_request.download_token', $token);
            $metadata['portal_download_token'] = $token;
            $metadata['portal_download_token_created_at'] = now()->toDateTimeString();

            DB::table('invoices')
                ->where('id', $invoiceId)
                ->update([
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }

        return $token;
    }

    protected function portalInvoiceDownloadLinks(int $invoiceId, string $token): array
    {
        if ($invoiceId <= 0 || $token === '') {
            return [];
        }

        return [
            [
                'type' => 'pdf',
                'label' => 'Descargar PDF',
                'url' => route('public.invoice.download', [
                    'invoice' => $invoiceId,
                    'type' => 'pdf',
                    'token' => $token,
                ]),
            ],
            [
                'type' => 'xml',
                'label' => 'Descargar XML',
                'url' => route('public.invoice.download', [
                    'invoice' => $invoiceId,
                    'type' => 'xml',
                    'token' => $token,
                ]),
            ],
            [
                'type' => 'zip',
                'label' => 'Descargar ZIP',
                'url' => route('public.invoice.download', [
                    'invoice' => $invoiceId,
                    'type' => 'zip',
                    'token' => $token,
                ]),
            ],
        ];
    }


}
