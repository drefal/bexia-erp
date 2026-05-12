<?php

namespace App\Support\Billing;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvoiceCfdiValidator
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VALIDATION_ERROR = 'validation_error';
    public const STATUS_READY_TO_STAMP = 'ready_to_stamp';
    public const STATUS_STAMPING = 'stamping';
    public const STATUS_STAMPED = 'stamped';
    public const STATUS_STAMP_ERROR = 'stamp_error';
    public const STATUS_CANCEL_PENDING = 'cancel_pending';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_CANCEL_ERROR = 'cancel_error';

    public function validate(Invoice $invoice, ?User $user = null): array
    {
        $invoice->refresh();

        $errors = [];
        $warnings = [];

        // BEXIA_V5523K_MAX_BACKDATE_72H
        $maxBackdateHours = 72;
        $cfdiBackdateCutoffText = now()->subHours($maxBackdateHours)->format('d/m/Y H:i:s');
        $cfdiInvoiceDateText = null;

        $invoiceDateRaw = $invoice->invoice_date ?? null;

        if (blank($invoiceDateRaw)) {
            $errors[] = 'La factura no tiene fecha de emisión.';
        } else {
            try {
                $rawText = trim((string) $invoiceDateRaw);
                $invoiceDate = \Carbon\Carbon::parse($invoiceDateRaw);

                /*
                 * Si el campo viene solo como fecha, usamos fin del día para evitar rechazar
                 * indebidamente una factura del día límite por no tener hora capturada.
                 * Cuando después agreguemos hora CFDI real, se validará con la hora exacta.
                 */
                $hasExplicitTime = (bool) preg_match('/\d{1,2}:\d{2}/', $rawText);
                $invoiceDateForLimit = $hasExplicitTime
                    ? $invoiceDate->copy()
                    : $invoiceDate->copy()->endOfDay();

                $cfdiInvoiceDateText = $invoiceDate->format('d/m/Y');

                if ($invoiceDateForLimit->lt(now()->subHours($maxBackdateHours))) {
                    $errors[] = 'La factura tiene fecha ' . $invoiceDate->format('d/m/Y') .
                        ' y ya excede el límite de ' . $maxBackdateHours .
                        ' horas en el pasado para timbrar. Contacta al administrador de facturación para revisar el caso.';
                }
            } catch (\Throwable $e) {
                $errors[] = 'La fecha de emisión de la factura no es válida.';
            }
        }


        $company = DB::table('companies')->where('id', (int) $invoice->company_id)->first();

        if (! $company) {
            $errors[] = 'La factura no tiene empresa válida.';
        }

        if ($company) {
            if (blank($company->tax_id ?? null)) {
                $errors[] = 'La empresa emisora no tiene RFC.';
            }

            if (blank($company->business_name ?? null) && blank($company->name ?? null)) {
                $errors[] = 'La empresa emisora no tiene razón social/nombre.';
            }

            if (blank($company->tax_regime ?? null)) {
                $errors[] = 'La empresa emisora no tiene régimen fiscal.';
            }

            if (blank($company->fiscal_postal_code ?? null) && blank($company->postal_code ?? null)) {
                $errors[] = 'La empresa emisora no tiene código postal fiscal.';
            }

            if (blank($company->billing_pac_provider ?? null)) {
                $errors[] = 'La empresa no tiene PAC configurado.';
            }

            if (blank($company->billing_pac_username ?? null) || blank($company->billing_pac_password ?? null)) {
                $errors[] = 'La empresa no tiene usuario/contraseña PAC configurados.';
            }

            // BEXIA_V5523M_REQUIRE_CSD
            if (blank($company->billing_csd_certificate_path ?? null) || blank($company->billing_csd_key_path ?? null) || blank($company->billing_csd_password ?? null)) {
                $errors[] = 'La empresa no tiene CSD configurado: falta certificado .cer, llave .key o contraseña.';
            } elseif (($company->billing_csd_last_test_status ?? null) !== 'success') {
                $errors[] = 'El CSD de la empresa no está validado correctamente. Valídalo desde Empresas antes de timbrar.';
            }

            if (($company->billing_pac_last_test_status ?? null) !== 'success') {
                $warnings[] = 'La última prueba de conexión PAC no está marcada como correcta.';
            }
        }

        if (blank($invoice->customer_fiscal_name ?? null)) {
            $errors[] = 'La factura no tiene razón social del receptor.';
        }

        if (blank($invoice->customer_rfc ?? null)) {
            $errors[] = 'La factura no tiene RFC del receptor.';
        }

        if (blank($invoice->customer_postal_code ?? null)) {
            $errors[] = 'La factura no tiene código postal fiscal del receptor.';
        }

        if (blank($invoice->customer_tax_regime_code ?? null)) {
            $errors[] = 'La factura no tiene régimen fiscal del receptor.';
        }

        if (blank($invoice->customer_cfdi_use_code ?? null)) {
            $errors[] = 'La factura no tiene Uso CFDI.';
        }

        // BEXIA_V5523O5_VALIDATE_SAT_CATALOGS
        $existsInSatCatalog = function (string $table, string $code): bool {
            $code = trim($code);

            if ($code === '' || ! Schema::hasTable($table)) {
                return true;
            }

            $columns = Schema::getColumnListing($table);

            foreach (['code', 'key', 'value'] as $column) {
                if (in_array($column, $columns, true)) {
                    return DB::table($table)->where($column, $code)->exists();
                }
            }

            return true;
        };

        $relationExistsInSatCatalog = function (string $taxRegimeCode, string $cfdiUseCode): bool {
            $taxRegimeCode = trim($taxRegimeCode);
            $cfdiUseCode = trim($cfdiUseCode);

            if ($taxRegimeCode === '' || $cfdiUseCode === '' || ! Schema::hasTable('sat_cfdi_use_tax_regime')) {
                return true;
            }

            $columns = Schema::getColumnListing('sat_cfdi_use_tax_regime');

            $regimeColumn = null;
            foreach (['tax_regime_code', 'sat_tax_regime_code', 'regime_code', 'fiscal_regime_code'] as $candidate) {
                if (in_array($candidate, $columns, true)) {
                    $regimeColumn = $candidate;
                    break;
                }
            }

            $useColumn = null;
            foreach (['cfdi_use_code', 'sat_cfdi_use_code', 'use_code'] as $candidate) {
                if (in_array($candidate, $columns, true)) {
                    $useColumn = $candidate;
                    break;
                }
            }

            if (! $regimeColumn || ! $useColumn) {
                return true;
            }

            return DB::table('sat_cfdi_use_tax_regime')
                ->where($regimeColumn, $taxRegimeCode)
                ->where($useColumn, $cfdiUseCode)
                ->exists();
        };

        $companyTaxRegimeCode = trim((string) ($company->tax_regime ?? ''));
        $customerTaxRegimeCode = trim((string) ($invoice->customer_tax_regime_code ?? ''));
        $customerCfdiUseCode = trim((string) ($invoice->customer_cfdi_use_code ?? ''));

        if (! $existsInSatCatalog('sat_tax_regimes', $companyTaxRegimeCode)) {
            $errors[] = 'El régimen fiscal de la empresa emisora no existe en el catálogo SAT configurado.';
        }

        if (! $existsInSatCatalog('sat_tax_regimes', $customerTaxRegimeCode)) {
            $errors[] = 'El régimen fiscal del receptor no existe en el catálogo SAT configurado.';
        }

        if (! $existsInSatCatalog('sat_cfdi_uses', $customerCfdiUseCode)) {
            $errors[] = 'El Uso CFDI del receptor no existe en el catálogo SAT configurado.';
        }

        if (! $relationExistsInSatCatalog($customerTaxRegimeCode, $customerCfdiUseCode)) {
            $errors[] = 'El Uso CFDI seleccionado no es válido para el régimen fiscal del receptor según Configuración facturación.';
        }

        // BEXIA_V5523L_WHATSAPP_WARNING
        if (blank($invoice->customer_whatsapp_phone ?? null)) {
            $warnings[] = 'El cliente no tiene WhatsApp/teléfono para envío automático de factura.';
        }

        if (blank($invoice->currency_code ?? null)) {
            $errors[] = 'La factura no tiene moneda.';
        }

        $lines = DB::table('invoice_lines')
            ->where('invoice_id', (int) $invoice->id)
            ->orderBy('id')
            ->get();

        $billableLines = $lines->filter(function ($line) {
            return (string) ($line->source_type ?? '') !== 'comment';
        });

        if ($billableLines->count() === 0) {
            $errors[] = 'La factura debe tener al menos una línea facturable.';
        }

        foreach ($billableLines as $line) {
            $prefix = 'Línea ' . $line->id . ': ';

            if (blank($line->product_name ?? null)) {
                $errors[] = $prefix . 'no tiene producto/concepto.';
            }

            if ((float) ($line->quantity ?? 0) <= 0) {
                $errors[] = $prefix . 'la cantidad debe ser mayor a cero.';
            }

            if ((float) ($line->unit_price_without_tax ?? 0) < 0) {
                $errors[] = $prefix . 'el precio no puede ser negativo.';
            }

            if (! is_numeric($line->tax_rate ?? null)) {
                $errors[] = $prefix . 'no tiene tasa de impuesto válida.';
            }

            if (blank($line->sat_product_service_code ?? null)) {
                $warnings[] = $prefix . 'no tiene clave SAT de producto/servicio.';
            }

            if (blank($line->sat_unit_code ?? null)) {
                $warnings[] = $prefix . 'no tiene unidad SAT.';
            }

            if (blank($line->sat_tax_object_code ?? null)) {
                $warnings[] = $prefix . 'no tiene objeto de impuesto SAT.';
            }
        }

        if ((float) ($invoice->total ?? 0) <= 0) {
            $errors[] = 'El total de la factura debe ser mayor a cero.';
        }

        $pacProvider = (string) ($company->billing_pac_provider ?? 'sw');
        $pacEnvironment = (bool) ($company->billing_pac_test_env ?? true) ? 'test' : 'production';

        $status = empty($errors)
            ? self::STATUS_READY_TO_STAMP
            : self::STATUS_VALIDATION_ERROR;

        $message = empty($errors)
            ? 'Factura lista para generar XML CFDI y timbrar.'
            : implode(' | ', $errors);

        $this->updateInvoiceCfdiStatus($invoice, $status, $pacProvider, $pacEnvironment, empty($errors) ? null : $message);

        $this->audit($invoice, $user, [
            'action' => 'validate',
            'status' => empty($errors) ? 'success' : 'error',
            'pac_provider' => $pacProvider,
            'pac_environment' => $pacEnvironment,
            'message' => $message,
            'request_meta' => [
                'invoice_id' => (int) $invoice->id,
                'invoice_number' => (string) ($invoice->number ?? ''),
                'invoice_date' => $cfdiInvoiceDateText,
                'max_backdate_hours' => 72,
                'backdate_cutoff' => $cfdiBackdateCutoffText,
                'company_id' => (int) ($invoice->company_id ?? 0),
                'customer_rfc_present' => filled($invoice->customer_rfc ?? null),
                'lines_count' => $lines->count(),
                'billable_lines_count' => $billableLines->count(),
                'total' => (float) ($invoice->total ?? 0),
            ],
            'response_meta' => [
                'errors' => $errors,
                'warnings' => $warnings,
                'next_step' => empty($errors) ? 'generate_xml' : 'fix_invoice_data',
            ],
        ]);

        return [
            'success' => empty($errors),
            'status' => $status,
            'message' => $message,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    public function audit(Invoice $invoice, ?User $user, array $data): void
    {
        if (! Schema::hasTable('invoice_cfdi_audits')) {
            return;
        }

        DB::table('invoice_cfdi_audits')->insert([
            'company_id' => (int) ($invoice->company_id ?? 0) ?: null,
            'invoice_id' => (int) $invoice->id,
            'user_id' => $user?->id,
            'action' => (string) ($data['action'] ?? 'unknown'),
            'status' => (string) ($data['status'] ?? 'info'),
            'pac_provider' => $data['pac_provider'] ?? null,
            'pac_environment' => $data['pac_environment'] ?? null,
            'request_id' => $data['request_id'] ?? null,
            'request_meta' => json_encode($data['request_meta'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'response_meta' => json_encode($data['response_meta'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'message' => mb_substr((string) ($data['message'] ?? ''), 0, 4000),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function updateInvoiceCfdiStatus(
        Invoice $invoice,
        string $status,
        ?string $pacProvider,
        ?string $pacEnvironment,
        ?string $errorMessage
    ): void {
        $updates = [
            'updated_at' => now(),
        ];

        foreach ([
            'cfdi_status' => $status,
            'pac_provider' => $pacProvider,
            'pac_environment' => $pacEnvironment,
            'pac_error_message' => $errorMessage,
            'cfdi_version' => '4.0',
            'cfdi_type' => 'I',
        ] as $column => $value) {
            if (Schema::hasColumn('invoices', $column)) {
                $updates[$column] = $value;
            }
        }

        DB::table('invoices')
            ->where('id', (int) $invoice->id)
            ->update($updates);
    }
}
