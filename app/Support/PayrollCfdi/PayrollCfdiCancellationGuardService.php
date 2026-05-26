<?php

namespace App\Support\PayrollCfdi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PayrollCfdiCancellationGuardService
{
    public function validate(int $companyId, int $receiptId, string $reason = '02'): array
    {
        $errors = [];
        $warnings = [];

        $appEnv = (string) config('app.env');
        $allowedEnv = (string) config('payroll_cfdi.cancellation_allowed_env', 'production');
        $enabled = (bool) config('payroll_cfdi.cancellation_enabled', false);

        if ($appEnv !== $allowedEnv) {
            $errors[] = "Cancelacion CFDI nomina bloqueada: ambiente actual '{$appEnv}', ambiente permitido '{$allowedEnv}'.";
        }

        if (! $enabled) {
            $errors[] = 'Cancelacion CFDI nomina bloqueada: PAYROLL_CFDI_CANCELLATION_ENABLED no esta habilitado.';
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
            $this->requireField($errors, $company, 'billing_pac_provider', 'La empresa no tiene PAC configurado.');
            $this->requireField($errors, $company, 'billing_pac_username', 'La empresa no tiene usuario PAC configurado.');
            $this->requireField($errors, $company, 'billing_pac_password', 'La empresa no tiene password/token PAC configurado.');
        }

        $receipt = null;

        if (Schema::hasTable('payroll_cfdi_receipts')) {
            $receipt = DB::table('payroll_cfdi_receipts')
                ->where('company_id', $companyId)
                ->where('id', $receiptId)
                ->first();

            if (! $receipt) {
                $errors[] = "No existe recibo CFDI nomina {$receiptId} para empresa {$companyId}.";
            } else {
                if ((string) $receipt->status !== 'stamped') {
                    $errors[] = "El recibo {$receiptId} no esta timbrado. Estado actual: {$receipt->status}.";
                }

                if (blank($receipt->uuid ?? null)) {
                    $errors[] = "El recibo {$receiptId} no tiene UUID.";
                }

                if ((string) ($receipt->pac_provider ?? '') === 'dev-demo') {
                    $errors[] = "El recibo {$receiptId} tiene timbrado demo DEV; no se puede cancelar ante PAC/SAT.";
                }

                if (filled($receipt->xml_path ?? null) && ! Storage::disk('local')->exists($receipt->xml_path)) {
                    $warnings[] = "El XML del recibo {$receiptId} no existe en storage: {$receipt->xml_path}.";
                }
            }
        }

        if (! in_array($reason, ['01', '02', '03', '04'], true)) {
            $errors[] = "Motivo de cancelacion invalido: {$reason}. Usa 01, 02, 03 o 04.";
        }

        return [
            'success' => count($errors) === 0,
            'blocked' => count($errors) > 0,
            'errors' => array_values($errors),
            'warnings' => array_values($warnings),
            'summary' => [
                'company_id' => $companyId,
                'receipt_id' => $receiptId,
                'reason' => $reason,
                'app_env' => $appEnv,
                'cancellation_allowed_env' => $allowedEnv,
                'payroll_cfdi_cancellation_enabled' => $enabled,
                'company_name' => $company->name ?? null,
                'company_pac_provider' => $company->billing_pac_provider ?? null,
                'receipt_status' => $receipt->status ?? null,
                'receipt_uuid' => $receipt->uuid ?? null,
                'receipt_pac_provider' => $receipt->pac_provider ?? null,
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
