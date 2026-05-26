<?php

namespace App\Support\PayrollCfdi;

use App\Models\PayrollCfdiReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PayrollCfdiStampService
{
    public function __construct(
        protected PayrollCfdiStampingGuardService $guard,
    ) {
    }

    public function stamp(int $companyId, int $receiptId, ?int $userId = null): array
    {
        $this->assertSchema();

        $guard = $this->guard->validate($companyId, $receiptId);

        if (! ($guard['success'] ?? false)) {
            $this->auditBlocked($companyId, $receiptId, $userId, $guard);

            return [
                'success' => false,
                'blocked' => true,
                'message' => 'Timbrado CFDI nomina bloqueado por guard.',
                'errors' => $guard['errors'] ?? [],
                'warnings' => $guard['warnings'] ?? [],
                'summary' => $guard['summary'] ?? [],
            ];
        }

        $receipt = PayrollCfdiReceipt::query()
            ->where('company_id', $companyId)
            ->where('id', $receiptId)
            ->first();

        if (! $receipt) {
            return [
                'success' => false,
                'blocked' => false,
                'message' => 'No existe el recibo CFDI nomina.',
                'errors' => ["No existe receipt_id={$receiptId} para company_id={$companyId}."],
                'warnings' => [],
                'summary' => [
                    'company_id' => $companyId,
                    'receipt_id' => $receiptId,
                ],
            ];
        }

        try {
            $xml = $this->loadXml($receipt);

            /*
             * Punto de integracion real:
             *
             * La facturacion ya cuenta con PAC/CSD funcionando en PROD.
             * Para V5.65.5c se debe reutilizar la capa existente:
             *
             * - App\Support\Billing\SwPacClient
             * - App\Support\Billing\InvoiceCfdiStampService
             * - Campos PAC/CSD de companies:
             *   billing_pac_provider
             *   billing_pac_username
             *   billing_pac_password
             *   billing_pac_test_env
             *   billing_csd_certificate_path
             *   billing_csd_key_path
             *   billing_csd_password
             *
             * Este V5.65.5b no envia al PAC todavia. Solo deja el servicio
             * seguro, auditable y bloqueado por ambiente.
             */

            throw new RuntimeException('Integracion PAC real de nomina pendiente para V5.65.5c. Guard OK, XML cargado, no se envio al PAC.');

        } catch (Throwable $e) {
            DB::table('payroll_cfdi_receipts')
                ->where('company_id', $companyId)
                ->where('id', $receiptId)
                ->update([
                    'status' => 'error',
                    'pac_error_message' => $e->getMessage(),
                    'updated_at' => now(),
                ]);

            $this->audit([
                'company_id' => $companyId,
                'payroll_cfdi_receipt_id' => $receiptId,
                'payroll_run_id' => $receipt->payroll_run_id,
                'payroll_run_line_id' => $receipt->payroll_run_line_id,
                'employee_id' => $receipt->employee_id,
                'user_id' => $userId,
                'action' => 'stamp',
                'status' => 'error',
                'pac_provider' => $receipt->pac_provider,
                'pac_test_env' => $receipt->pac_test_env,
                'request_id' => null,
                'request_meta' => [
                    'xml_path' => $receipt->xml_path,
                    'note' => 'No se envio al PAC en V5.65.5b.',
                ],
                'response_meta' => null,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'blocked' => false,
                'message' => 'No se pudo timbrar CFDI nomina.',
                'errors' => [$e->getMessage()],
                'warnings' => [],
                'summary' => [
                    'company_id' => $companyId,
                    'receipt_id' => $receiptId,
                    'receipt_status' => 'error',
                    'xml_path' => $receipt->xml_path,
                    'xml_loaded' => isset($xml) && is_string($xml) && strlen($xml) > 0,
                    'integration_pending' => true,
                ],
            ];
        }
    }

    protected function loadXml(PayrollCfdiReceipt $receipt): string
    {
        if (! filled($receipt->xml_path)) {
            throw new RuntimeException('El recibo no tiene XML generado.');
        }

        if (! Storage::disk('local')->exists($receipt->xml_path)) {
            throw new RuntimeException('El XML no existe en storage: ' . $receipt->xml_path);
        }

        $xml = Storage::disk('local')->get($receipt->xml_path);

        if (trim($xml) === '') {
            throw new RuntimeException('El XML esta vacio: ' . $receipt->xml_path);
        }

        return $xml;
    }

    protected function auditBlocked(int $companyId, int $receiptId, ?int $userId, array $guard): void
    {
        $receipt = Schema::hasTable('payroll_cfdi_receipts')
            ? DB::table('payroll_cfdi_receipts')->where('company_id', $companyId)->where('id', $receiptId)->first()
            : null;

        $this->audit([
            'company_id' => $companyId,
            'payroll_cfdi_receipt_id' => $receiptId,
            'payroll_run_id' => $receipt->payroll_run_id ?? null,
            'payroll_run_line_id' => $receipt->payroll_run_line_id ?? null,
            'employee_id' => $receipt->employee_id ?? null,
            'user_id' => $userId,
            'action' => 'stamp',
            'status' => 'blocked',
            'pac_provider' => $receipt->pac_provider ?? null,
            'pac_test_env' => $receipt->pac_test_env ?? null,
            'request_id' => null,
            'request_meta' => [
                'guard_summary' => $guard['summary'] ?? [],
            ],
            'response_meta' => null,
            'message' => implode(' | ', $guard['errors'] ?? ['Timbrado bloqueado por guard.']),
        ]);
    }

    protected function audit(array $data): void
    {
        if (! Schema::hasTable('payroll_cfdi_audits')) {
            return;
        }

        $data['request_meta'] = array_key_exists('request_meta', $data)
            ? $this->json($data['request_meta'])
            : null;

        $data['response_meta'] = array_key_exists('response_meta', $data)
            ? $this->json($data['response_meta'])
            : null;

        $data['ip_address'] = null;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('payroll_cfdi_audits')->insert($data);
    }

    protected function assertSchema(): void
    {
        foreach (['payroll_cfdi_receipts', 'payroll_cfdi_audits'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("No existe la tabla requerida: {$table}");
            }
        }
    }

    protected function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
