<?php

namespace App\Support\PayrollCfdi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class PayrollCfdiCancelService
{
    public function __construct(
        protected PayrollCfdiCancellationGuardService $guard,
    ) {
    }

    public function cancel(
        int $companyId,
        int $receiptId,
        string $reason = '02',
        ?string $replacementUuid = null,
        ?int $userId = null,
    ): array {
        $this->assertSchema();

        $guard = $this->guard->validate($companyId, $receiptId, $reason);

        if (! ($guard['success'] ?? false)) {
            $this->auditBlocked($companyId, $receiptId, $userId, $reason, $replacementUuid, $guard);

            return [
                'success' => false,
                'blocked' => true,
                'message' => 'Cancelacion CFDI nomina bloqueada por guard.',
                'errors' => $guard['errors'] ?? [],
                'warnings' => $guard['warnings'] ?? [],
                'summary' => $guard['summary'] ?? [],
            ];
        }

        $receipt = DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('id', $receiptId)
            ->first();

        try {
            /*
             * V5.65.7a deja lista la estructura segura.
             *
             * V5.65.7b debe conectar aqui la cancelacion real al PAC SW,
             * reutilizando la capa existente de facturacion cuando se confirme
             * el metodo exacto del cliente PAC para cancelar UUID.
             */

            throw new RuntimeException('Integracion PAC real para cancelacion CFDI nomina pendiente para V5.65.7b. No se envio al PAC/SAT.');

        } catch (Throwable $e) {
            $this->audit([
                'company_id' => $companyId,
                'payroll_cfdi_receipt_id' => $receiptId,
                'payroll_run_id' => $receipt->payroll_run_id ?? null,
                'payroll_run_line_id' => $receipt->payroll_run_line_id ?? null,
                'employee_id' => $receipt->employee_id ?? null,
                'user_id' => $userId,
                'action' => 'cancel',
                'status' => 'error',
                'pac_provider' => $receipt->pac_provider ?? null,
                'pac_test_env' => $receipt->pac_test_env ?? null,
                'request_id' => null,
                'request_meta' => [
                    'uuid' => $receipt->uuid ?? null,
                    'reason' => $reason,
                    'replacement_uuid' => $replacementUuid,
                    'note' => 'No se envio al PAC/SAT en V5.65.7a.',
                ],
                'response_meta' => null,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'blocked' => false,
                'message' => 'No se pudo cancelar CFDI nomina.',
                'errors' => [$e->getMessage()],
                'warnings' => [],
                'summary' => [
                    'company_id' => $companyId,
                    'receipt_id' => $receiptId,
                    'uuid' => $receipt->uuid ?? null,
                    'reason' => $reason,
                    'replacement_uuid' => $replacementUuid,
                    'integration_pending' => true,
                ],
            ];
        }
    }

    protected function auditBlocked(int $companyId, int $receiptId, ?int $userId, string $reason, ?string $replacementUuid, array $guard): void
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
            'action' => 'cancel',
            'status' => 'blocked',
            'pac_provider' => $receipt->pac_provider ?? null,
            'pac_test_env' => $receipt->pac_test_env ?? null,
            'request_id' => null,
            'request_meta' => [
                'uuid' => $receipt->uuid ?? null,
                'reason' => $reason,
                'replacement_uuid' => $replacementUuid,
                'guard_summary' => $guard['summary'] ?? [],
            ],
            'response_meta' => null,
            'message' => implode(' | ', $guard['errors'] ?? ['Cancelacion bloqueada por guard.']),
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
