<?php

namespace App\Support\PayrollCfdi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PayrollCfdiStampingGuardService
{
    public function validate(int $companyId, ?int $receiptId = null): array
    {
        $errors = [];
        $warnings = [];

        $appEnv = (string) config('app.env');
        $allowedEnv = (string) config('payroll_cfdi.stamping_allowed_env', 'production');
        $enabled = (bool) config('payroll_cfdi.stamping_enabled', false);

        if ($appEnv !== $allowedEnv) {
            $errors[] = "Timbrado CFDI nomina bloqueado: ambiente actual '{$appEnv}', ambiente permitido '{$allowedEnv}'.";
        }

        if (! $enabled) {
            $errors[] = 'Timbrado CFDI nomina bloqueado: PAYROLL_CFDI_STAMPING_ENABLED no esta habilitado.';
        }

        if (! Schema::hasTable('companies')) {
            $errors[] = 'No existe tabla companies.';
        }

        if (! Schema::hasTable('payroll_cfdi_receipts')) {
            $errors[] = 'No existe tabla payroll_cfdi_receipts.';
        }

        $company = Schema::hasTable('companies')
            ? DB::table('companies')->where('id', $companyId)->first()
            : null;

        if (! $company) {
            $errors[] = "No existe la empresa {$companyId}.";
        } else {
            $this->requireField($errors, $company, 'tax_id', 'La empresa no tiene RFC/tax_id.');
            $this->requireField($errors, $company, 'business_name', 'La empresa no tiene razon social/business_name.');
            $this->requireField($errors, $company, 'tax_regime', 'La empresa no tiene regimen fiscal/tax_regime.');
            $this->requireField($errors, $company, 'fiscal_postal_code', 'La empresa no tiene codigo postal fiscal.');

            $this->requireField($errors, $company, 'billing_pac_provider', 'La empresa no tiene PAC configurado.');
            $this->requireField($errors, $company, 'billing_pac_username', 'La empresa no tiene usuario PAC configurado.');
            $this->requireField($errors, $company, 'billing_pac_password', 'La empresa no tiene password/token PAC configurado.');
            $this->requireField($errors, $company, 'billing_csd_certificate_path', 'La empresa no tiene certificado CSD configurado.');
            $this->requireField($errors, $company, 'billing_csd_key_path', 'La empresa no tiene llave CSD configurada.');
            $this->requireField($errors, $company, 'billing_csd_password', 'La empresa no tiene password CSD configurado.');
        }

        $receipt = null;

        if ($receiptId !== null && Schema::hasTable('payroll_cfdi_receipts')) {
            $receipt = DB::table('payroll_cfdi_receipts')
                ->where('company_id', $companyId)
                ->where('id', $receiptId)
                ->first();

            if (! $receipt) {
                $errors[] = "No existe recibo CFDI nomina {$receiptId} para empresa {$companyId}.";
            } else {
                if (! in_array((string) $receipt->status, ['validated', 'error'], true)) {
                    $errors[] = "El recibo {$receiptId} no esta listo para timbrar. Estado actual: {$receipt->status}.";
                }

                if (! empty($receipt->uuid)) {
                    $errors[] = "El recibo {$receiptId} ya tiene UUID; no debe timbrarse de nuevo.";
                }

                if (empty($receipt->xml_path)) {
                    $errors[] = "El recibo {$receiptId} no tiene XML generado.";
                } elseif (! Storage::disk('local')->exists($receipt->xml_path)) {
                    $errors[] = "El XML del recibo {$receiptId} no existe en storage: {$receipt->xml_path}.";
                }
            }
        }

        return [
            'success' => count($errors) === 0,
            'blocked' => count($errors) > 0,
            'errors' => array_values($errors),
            'warnings' => array_values($warnings),
            'summary' => [
                'company_id' => $companyId,
                'receipt_id' => $receiptId,
                'app_env' => $appEnv,
                'stamping_allowed_env' => $allowedEnv,
                'payroll_cfdi_stamping_enabled' => $enabled,
                'company_name' => $company->name ?? null,
                'company_pac_provider' => $company->billing_pac_provider ?? null,
                'receipt_status' => $receipt->status ?? null,
                'receipt_xml_path' => $receipt->xml_path ?? null,
            ],
        ];
    }

    protected function requireField(array &$errors, object $row, string $field, string $message): void
    {
        if (! property_exists($row, $field)) {
            $errors[] = $message . " Campo no existe: {$field}.";
            return;
        }

        $value = $row->{$field};

        if ($value === null || trim((string) $value) === '') {
            $errors[] = $message;
        }
    }
}
