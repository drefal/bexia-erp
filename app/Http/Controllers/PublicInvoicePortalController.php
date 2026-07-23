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
            'fiscalData' => $this->fiscalDataForPortalValidationResult($result),
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
            /*
             * BEXIA_V5528C9B_RETRY_USE_EXISTING_INVOICE_PROD
             * Si el intento anterior falló por datos fiscales, no crear otra factura:
             * actualizar la factura existente y volver a timbrar.
             */
            $invoiceResult = ! empty($result['retry_invoice_id'])
                ? $this->prepareExistingPortalInvoiceForRetry((int) $result['order_id'], (int) $result['retry_invoice_id'], $fiscalData)
                : $this->createPortalInvoiceDraft((int) $result['order_id'], $fiscalData);

            /*
             * BEXIA_V5528C_PORTAL_AUTO_STAMP_AFTER_REQUEST_PROD
             * En producción se intenta timbrar inmediatamente desde el portal.
             * Si el PAC no responde o falla validación, queda como solicitud recibida.
             */
            $autoStampResult = $this->attemptPortalAutoStampAfterRequest($invoiceResult, $result, $ticket, $fiscalData);

            if ($autoStampResult !== null) {
                return view('pos.invoice-placeholder', [
                    'ticket' => $ticket,
                    'total' => $totalInput,
                    'result' => $autoStampResult,
                    'fiscalData' => $fiscalData,
                    'taxRegimeOptions' => $this->taxRegimeOptions(),
                    'cfdiUseOptions' => $this->cfdiUseOptions(),
                ]);
            }

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

        // BEXIA_V582_P3_XLSM_A13_PORTAL_LOOKUP
        if ($this->isHistoricalMovement($order)) {
            return $this->error('Este movimiento es historico y no esta disponible para facturacion.');
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

            // BEXIA_V582_P3_XLSM_A13_PORTAL_TRANSACTION
            if ($this->isHistoricalMovement($order)) {
                throw new \RuntimeException('Este movimiento es historico y no puede facturarse.');
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
        /*
         * BEXIA_V5528C_PORTAL_FULL_TAX_REGIME_OPTIONS_PROD
         */
        $options = [];

        foreach (['sat_tax_regimes', 'sat_tax_regime', 'tax_regimes'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);

            $codeColumn = collect(['code', 'key', 'sat_code', 'tax_regime_code', 'regime_code'])
                ->first(fn ($column) => in_array($column, $columns, true));

            $nameColumn = collect(['name', 'description', 'label', 'title'])
                ->first(fn ($column) => in_array($column, $columns, true));

            if (! $codeColumn) {
                continue;
            }

            $query = DB::table($table);

            foreach (['is_active', 'active', 'enabled'] as $activeColumn) {
                if (in_array($activeColumn, $columns, true)) {
                    $query->where($activeColumn, true);
                    break;
                }
            }

            foreach ($query->orderBy($codeColumn)->get() as $row) {
                $code = trim((string) ($row->{$codeColumn} ?? ''));

                if (! preg_match('/^\\d{3}$/', $code)) {
                    continue;
                }

                $name = $nameColumn ? trim((string) ($row->{$nameColumn} ?? '')) : '';

                $options[$code] = $code . ($name !== '' ? ' - ' . $name : '');
            }
        }

        foreach (['sat_billing_catalog_items', 'sat_catalog_items', 'sat_billing_catalogs'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);

            $codeColumn = collect(['code', 'key', 'sat_code', 'value', 'item_key'])
                ->first(fn ($column) => in_array($column, $columns, true));

            $nameColumn = collect(['name', 'description', 'label', 'title'])
                ->first(fn ($column) => in_array($column, $columns, true));

            if (! $codeColumn) {
                continue;
            }

            $catalogColumns = array_values(array_filter(
                ['catalog', 'catalog_type', 'type', 'catalog_key', 'group', 'category'],
                fn ($column) => in_array($column, $columns, true)
            ));

            foreach (DB::table($table)->limit(3000)->get() as $row) {
                $belongsToTaxRegime = empty($catalogColumns);

                foreach ($catalogColumns as $catalogColumn) {
                    $catalogValue = mb_strtolower((string) ($row->{$catalogColumn} ?? ''));

                    if (
                        str_contains($catalogValue, 'regimen')
                        || str_contains($catalogValue, 'régimen')
                        || str_contains($catalogValue, 'tax_regime')
                        || str_contains($catalogValue, 'regime')
                    ) {
                        $belongsToTaxRegime = true;
                        break;
                    }
                }

                if (! $belongsToTaxRegime) {
                    continue;
                }

                $code = trim((string) ($row->{$codeColumn} ?? ''));

                if (! preg_match('/^\\d{3}$/', $code)) {
                    continue;
                }

                $name = $nameColumn ? trim((string) ($row->{$nameColumn} ?? '')) : '';

                $options[$code] = $code . ($name !== '' ? ' - ' . $name : '');
            }
        }

        if (! empty($options)) {
            ksort($options);

            return $options;
        }

        return [
            '601' => '601 - General de Ley Personas Morales',
            '603' => '603 - Personas Morales con Fines no Lucrativos',
            '605' => '605 - Sueldos y Salarios e Ingresos Asimilados a Salarios',
            '606' => '606 - Arrendamiento',
            '607' => '607 - Régimen de Enajenación o Adquisición de Bienes',
            '608' => '608 - Demás ingresos',
            '610' => '610 - Residentes en el Extranjero sin Establecimiento Permanente en México',
            '611' => '611 - Ingresos por Dividendos',
            '612' => '612 - Personas Físicas con Actividades Empresariales y Profesionales',
            '614' => '614 - Ingresos por intereses',
            '615' => '615 - Régimen de los ingresos por obtención de premios',
            '616' => '616 - Sin obligaciones fiscales',
            '620' => '620 - Sociedades Cooperativas de Producción que optan por diferir sus ingresos',
            '621' => '621 - Incorporación Fiscal',
            '622' => '622 - Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
            '623' => '623 - Opcional para Grupos de Sociedades',
            '624' => '624 - Coordinados',
            '625' => '625 - Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
            '626' => '626 - Régimen Simplificado de Confianza',
            '628' => '628 - Hidrocarburos',
            '629' => '629 - De los Regímenes Fiscales Preferentes',
            '630' => '630 - Enajenación de acciones en bolsa de valores',
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

        /*
         * BEXIA_V5528C7_PORTAL_RECHECK_FISCAL_ERROR_PROD
         */
        if ((string) ($invoice->cfdi_status ?? '') === 'stamp_error') {
            $stampMessage = $this->latestPortalInvoiceCfdiErrorMessage($invoiceId);

            if ($this->isPortalCustomerFiscalDataError($stampMessage)) {
                return $this->portalInvoiceFiscalDataErrorResult($base, $stampMessage);
            }
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


    /*
     * BEXIA_V5528C_PORTAL_AUTO_STAMP_HELPER_PROD
     */
    protected function attemptPortalAutoStampAfterRequest(
        array $invoiceResult,
        array $validationResult,
        string $ticket,
        array $fiscalData
    ): ?array {
        $invoiceId = (int) ($invoiceResult['invoice_id'] ?? 0);

        if ($invoiceId <= 0) {
            return null;
        }

        $base = [
            'order_id' => $validationResult['order_id'] ?? null,
            'order_number' => $validationResult['order_number'] ?? $ticket,
            'order_total' => $validationResult['order_total'] ?? null,
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceResult['invoice_number'] ?? null,
            'email' => $fiscalData['email'] ?? null,
            'completed' => true,
        ];

        try {
            $invoice = \App\Models\Invoice::query()->find($invoiceId);

            if (! $invoice) {
                return $this->portalInvoiceReceivedFallbackResult($base);
            }

            try {
                \App\Filament\Resources\InvoiceResource::recalculateInvoice($invoice);
            } catch (\Throwable $e) {
                report($e);
            }

            $invoice->refresh();

            $stampResult = app(\App\Support\Billing\InvoiceCfdiStampService::class)
                ->stamp($invoice, null);

            $invoice->refresh();

            if (($stampResult['success'] ?? false) === true && (string) ($invoice->cfdi_status ?? '') === 'stamped') {
                $token = $this->ensurePortalDownloadTokenForInvoice($invoiceId);

                return array_merge($base, [
                    'ok' => true,
                    'type' => 'success',
                    'title' => 'Factura timbrada correctamente',
                    'message' => 'Tu factura fue timbrada correctamente. También enviamos los archivos al correo capturado.',
                    'fiscal_label' => 'CFDI timbrado',
                    'cfdi_uuid' => (string) ($invoice->cfdi_uuid ?? ''),
                    'download_links' => $this->portalInvoiceDownloadLinks($invoiceId, $token),
                ]);
            }

            /*
             * BEXIA_V5528C7_PORTAL_FISCAL_ERROR_MESSAGE_PROD
             * Si el PAC/SAT rechaza por datos fiscales del receptor, se informa
             * al cliente. Si parece falla temporal/PAC sin respuesta, queda como
             * solicitud recibida para revisión interna.
             */
            $stampMessage = (string) (
                ($stampResult['message'] ?? '')
                ?: $this->latestPortalInvoiceCfdiErrorMessage($invoiceId)
            );

            if ($this->isPortalCustomerFiscalDataError($stampMessage)) {
                return $this->portalInvoiceFiscalDataErrorResult($base, $stampMessage);
            }

            return $this->portalInvoiceReceivedFallbackResult($base);
        } catch (\Throwable $e) {
            report($e);

            return $this->portalInvoiceReceivedFallbackResult($base);
        }
    }


    protected function portalInvoiceReceivedFallbackResult(array $base): array
    {
        return array_merge($base, [
            'ok' => true,
            'type' => 'success',
            'title' => 'Solicitud de factura recibida',
            'message' => 'Creamos tu factura en borrador. Si el timbrado no respondió en este momento, el equipo podrá revisarla, timbrarla y enviarla al correo capturado.',
            'fiscal_label' => 'Factura interna en borrador',
        ]);
    }



    /*
     * BEXIA_V5528C7_PORTAL_FISCAL_ERROR_HELPERS_PROD
     */
        protected function portalInvoiceFiscalDataErrorResult(array $base, string $stampMessage): array
    {
        /*
         * BEXIA_V5528C9B_FISCAL_ERROR_CAN_RETRY_PROD
         */
        $safeMessage = trim($stampMessage);
        $invoiceId = (int) ($base['invoice_id'] ?? 0);

        return array_merge($base, [
            'ok' => true,
            'type' => 'error',
            'title' => 'Corrige tus datos fiscales',
            'message' => 'El SAT/PAC rechazó el timbrado porque los datos fiscales no coinciden exactamente con la Constancia de Situación Fiscal. Revisa RFC, nombre fiscal, código postal fiscal, régimen fiscal y vuelve a intentarlo.',
            'fiscal_label' => 'Datos fiscales rechazados',
            'stamp_error_message' => $safeMessage,
            'show_fiscal_form' => true,
            'retry_invoice_id' => $invoiceId,
            'completed' => false,
        ]);
    }


    protected function isPortalCustomerFiscalDataError(?string $message): bool
    {
        $message = mb_strtolower((string) $message);

        if ($message === '') {
            return false;
        }

        $needles = [
            'cfdi40145',
            'nombre del receptor',
            'rfc del receptor',
            'domicilio fiscal receptor',
            'regimen fiscal receptor',
            'régimen fiscal receptor',
            'uso cfdi',
            'codigo postal del receptor',
            'código postal del receptor',
            'debe pertenecer al nombre asociado',
            'lista de rfc inscritos no cancelados',
            'constancia de situación fiscal',
            'constancia de situacion fiscal',
        ];

        foreach ($needles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function latestPortalInvoiceCfdiErrorMessage(int $invoiceId): string
    {
        if ($invoiceId <= 0 || ! \Illuminate\Support\Facades\Schema::hasTable('invoice_cfdi_audits')) {
            return '';
        }

        $row = DB::table('invoice_cfdi_audits')
            ->where('invoice_id', $invoiceId)
            ->where(function ($query) {
                $query->where('status', 'error')
                    ->orWhere('action', 'stamp');
            })
            ->orderByDesc('id')
            ->first();

        return trim((string) ($row->message ?? ''));
    }


    protected function fiscalDataForPortalValidationResult(array $result): array
    {
        /*
         * BEXIA_V5528C9B_PREFILL_RETRY_FISCAL_DATA_PROD
         */
        $invoiceId = (int) ($result['retry_invoice_id'] ?? 0);

        if ($invoiceId <= 0) {
            return $this->emptyFiscalData();
        }

        $invoice = DB::table('invoices')->where('id', $invoiceId)->first();

        if (! $invoice) {
            return $this->emptyFiscalData();
        }

        $metadata = $this->metadataArray($invoice->metadata ?? null);
        $request = data_get($metadata, 'portal_invoice_request', []);

        if (! is_array($request)) {
            $request = [];
        }

        return [
            'rfc' => (string) (($request['rfc'] ?? '') ?: ($invoice->customer_rfc ?? '')),
            'fiscal_name' => (string) (($request['fiscal_name'] ?? '') ?: ($invoice->customer_fiscal_name ?? '')),
            'postal_code' => (string) (($request['postal_code'] ?? '') ?: ($invoice->customer_postal_code ?? '')),
            'tax_regime_code' => (string) (($request['tax_regime_code'] ?? '') ?: ($invoice->customer_tax_regime_code ?? '')),
            'cfdi_use_code' => (string) (($request['cfdi_use_code'] ?? '') ?: ($invoice->customer_cfdi_use_code ?? '')),
            'email' => (string) ($request['email'] ?? ''),
        ];
    }

    protected function prepareExistingPortalInvoiceForRetry(int $orderId, int $invoiceId, array $fiscalData): array
    {
        /*
         * BEXIA_V5528C9B_PREPARE_EXISTING_INVOICE_RETRY_PROD
         */
        if ($orderId <= 0 || $invoiceId <= 0) {
            throw new \RuntimeException('No se pudo preparar el reintento de facturación.');
        }

        return DB::transaction(function () use ($orderId, $invoiceId, $fiscalData): array {
            $order = DB::table('pos_orders')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new \RuntimeException('No se encontró el ticket.');
            }

            // BEXIA_V582_P3_XLSM_A13_PORTAL_TRANSACTION
            if ($this->isHistoricalMovement($order)) {
                throw new \RuntimeException('Este movimiento es historico y no puede facturarse.');
            }

            $invoice = DB::table('invoices')
                ->where('id', $invoiceId)
                ->lockForUpdate()
                ->first();

            if (! $invoice) {
                throw new \RuntimeException('No se encontró la factura para reintentar.');
            }

            $orderMetadata = $this->metadataArray($order->metadata ?? null);
            $linkedInvoiceId = (int) ($orderMetadata['portal_invoice_id'] ?? 0);

            if ($linkedInvoiceId !== $invoiceId) {
                throw new \RuntimeException('La factura no corresponde al ticket capturado.');
            }

            $stampMessage = $this->latestPortalInvoiceCfdiErrorMessage($invoiceId);

            if ((string) ($invoice->cfdi_status ?? '') !== 'stamp_error' || ! $this->isPortalCustomerFiscalDataError($stampMessage)) {
                throw new \RuntimeException('Esta factura no está disponible para corrección desde el portal.');
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

            $this->updateInvoiceFiscalSnapshot($invoiceId, $contactId, $fiscalData);
            $this->updatePortalRetryMetadata($orderId, $invoiceId, $fiscalData);
            $this->resetPortalInvoiceStampStateForRetry($invoiceId);

            $invoice = DB::table('invoices')->where('id', $invoiceId)->first();

            return [
                'invoice_id' => (int) $invoiceId,
                'invoice_number' => (string) ($invoice->number ?? ('#' . $invoiceId)),
                'retry' => true,
            ];
        });
    }

    protected function updatePortalRetryMetadata(int $orderId, int $invoiceId, array $fiscalData): void
    {
        /*
         * BEXIA_V5528C9B_UPDATE_RETRY_METADATA_PROD
         */
        $invoice = DB::table('invoices')->where('id', $invoiceId)->first();

        if ($invoice) {
            $metadata = $this->metadataArray($invoice->metadata ?? null);

            data_set($metadata, 'portal_invoice_request.rfc', $fiscalData['rfc'] ?? '');
            data_set($metadata, 'portal_invoice_request.fiscal_name', $fiscalData['fiscal_name'] ?? '');
            data_set($metadata, 'portal_invoice_request.postal_code', $fiscalData['postal_code'] ?? '');
            data_set($metadata, 'portal_invoice_request.tax_regime_code', $fiscalData['tax_regime_code'] ?? '');
            data_set($metadata, 'portal_invoice_request.cfdi_use_code', $fiscalData['cfdi_use_code'] ?? '');
            data_set($metadata, 'portal_invoice_request.email', $fiscalData['email'] ?? '');
            data_set($metadata, 'portal_invoice_request.retry_requested_at', now()->toDateTimeString());

            data_forget($metadata, 'portal_invoice_request.auto_email_success');
            data_forget($metadata, 'portal_invoice_request.auto_email_to');
            data_forget($metadata, 'portal_invoice_request.auto_email_attempted_at');
            data_forget($metadata, 'portal_invoice_request.auto_email_message');

            DB::table('invoices')
                ->where('id', $invoiceId)
                ->update([
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }

        $order = DB::table('pos_orders')->where('id', $orderId)->first();

        if ($order) {
            $metadata = $this->metadataArray($order->metadata ?? null);

            $metadata['portal_invoice_retry_requested_at'] = now()->toDateTimeString();
            $metadata['portal_invoice_email'] = $fiscalData['email'] ?? '';
            $metadata['portal_invoice_rfc'] = $fiscalData['rfc'] ?? '';

            DB::table('pos_orders')
                ->where('id', $orderId)
                ->update([
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }
    }

    protected function resetPortalInvoiceStampStateForRetry(int $invoiceId): void
    {
        /*
         * BEXIA_V5528C9B_RESET_STAMP_STATE_RETRY_PROD
         */
        if ($invoiceId <= 0 || ! Schema::hasTable('invoices')) {
            return;
        }

        $updates = [
            'updated_at' => now(),
        ];

        foreach ([
            'cfdi_status' => null,
            'cfdi_uuid' => null,
            'cfdi_pdf_path' => null,
            'cfdi_xml_path' => null,
            'cfdi_status_message' => null,
            'cfdi_error_message' => null,
            'cfdi_error' => null,
            'cfdi_last_error' => null,
        ] as $column => $value) {
            if (Schema::hasColumn('invoices', $column)) {
                $updates[$column] = $value;
            }
        }

        DB::table('invoices')
            ->where('id', $invoiceId)
            ->update($updates);
    }


    // BEXIA_V582_P3_XLSM_A13_PORTAL_HELPER
    private function isHistoricalMovement(object $order): bool
    {
        return (bool) ($order->is_legacy ?? false)
            || filled($order->migration_batch_id ?? null)
            || strtoupper(trim((string) ($order->source_system ?? ''))) === 'PAPELON_XLSM';
    }

}
