<?php

namespace App\Support\PayrollCfdi;

use App\Models\Company;
use App\Models\PayrollCfdiReceipt;
use App\Support\Billing\SwPacClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use RuntimeException;
use Throwable;

class PayrollCfdiCancelService
{
    public function __construct(
        protected PayrollCfdiCancellationGuardService $guard,
        protected SwPacClient $swPacClient,
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

        $company = Company::query()->find($companyId);

        if (! $company) {
            return $this->failure(
                companyId: $companyId,
                receiptId: $receiptId,
                message: 'No existe la empresa para cancelar CFDI nomina.',
                errors: ["No existe company_id={$companyId}."],
            );
        }

        $receipt = PayrollCfdiReceipt::query()
            ->where('company_id', $companyId)
            ->where('id', $receiptId)
            ->first();

        if (! $receipt) {
            return $this->failure(
                companyId: $companyId,
                receiptId: $receiptId,
                message: 'No existe el recibo CFDI nomina.',
                errors: ["No existe receipt_id={$receiptId} para company_id={$companyId}."],
            );
        }

        try {
            $this->updateReceipt($companyId, $receiptId, [
                'status' => 'cancelling',
                'pac_error_message' => null,
                'updated_at' => now(),
            ]);

            $this->audit([
                'company_id' => $companyId,
                'payroll_cfdi_receipt_id' => $receiptId,
                'payroll_run_id' => $receipt->payroll_run_id,
                'payroll_run_line_id' => $receipt->payroll_run_line_id,
                'employee_id' => $receipt->employee_id,
                'user_id' => $userId,
                'action' => 'cancel',
                'status' => 'sending_to_pac',
                'pac_provider' => $company->billing_pac_provider ?? $receipt->pac_provider ?? 'sw',
                'pac_test_env' => (bool) ($company->billing_pac_test_env ?? $receipt->pac_test_env ?? false),
                'request_id' => null,
                'request_meta' => [
                    'uuid' => $receipt->uuid,
                    'reason' => $reason,
                    'replacement_uuid' => $replacementUuid,
                    'client' => SwPacClient::class,
                    'available_cancel_methods' => $this->availableCancelMethods(),
                ],
                'response_meta' => null,
                'message' => 'Enviando solicitud de cancelacion CFDI nomina al PAC SW desde ambiente permitido.',
            ]);

            $pacResult = $this->cancelWithPac(
                company: $company,
                receipt: $receipt,
                reason: $reason,
                replacementUuid: $replacementUuid,
            );

            if (! ($pacResult['success'] ?? false)) {
                $message = $this->pacMessage($pacResult, 'PAC rechazo o no pudo cancelar CFDI nomina.');

                $this->updateReceipt($companyId, $receiptId, [
                    'status' => 'cancel_error',
                    'pac_request_id' => $this->pacRequestId($pacResult),
                    'pac_error_message' => $message,
                    'metadata' => $this->json($this->mergeMetadata($receipt, [
                        'cancel_error_at' => now()->toDateTimeString(),
                        'cancel_error_source' => 'V5.65.7b',
                        'cancel_reason' => $reason,
                        'cancel_replacement_uuid' => $replacementUuid,
                    ])),
                    'updated_at' => now(),
                ]);

                $this->audit([
                    'company_id' => $companyId,
                    'payroll_cfdi_receipt_id' => $receiptId,
                    'payroll_run_id' => $receipt->payroll_run_id,
                    'payroll_run_line_id' => $receipt->payroll_run_line_id,
                    'employee_id' => $receipt->employee_id,
                    'user_id' => $userId,
                    'action' => 'cancel',
                    'status' => 'error',
                    'pac_provider' => $company->billing_pac_provider ?? $receipt->pac_provider ?? 'sw',
                    'pac_test_env' => (bool) ($company->billing_pac_test_env ?? $receipt->pac_test_env ?? false),
                    'request_id' => $this->pacRequestId($pacResult),
                    'request_meta' => [
                        'uuid' => $receipt->uuid,
                        'reason' => $reason,
                        'replacement_uuid' => $replacementUuid,
                    ],
                    'response_meta' => $pacResult,
                    'message' => $message,
                ]);

                return [
                    'success' => false,
                    'blocked' => false,
                    'message' => 'No se pudo cancelar CFDI nomina.',
                    'errors' => [$message],
                    'warnings' => [],
                    'summary' => [
                        'company_id' => $companyId,
                        'receipt_id' => $receiptId,
                        'uuid' => $receipt->uuid,
                        'reason' => $reason,
                        'replacement_uuid' => $replacementUuid,
                        'receipt_status' => 'cancel_error',
                        'pac_provider' => $company->billing_pac_provider ?? 'sw',
                        'pac_request_id' => $this->pacRequestId($pacResult),
                    ],
                ];
            }

            $cancelledXmlPath = $this->storeCancellationEvidence($companyId, $receipt, $pacResult);

            $this->updateReceipt($companyId, $receiptId, [
                'status' => 'cancelled',
                'pac_request_id' => $this->pacRequestId($pacResult),
                'pac_error_message' => null,
                'metadata' => $this->json($this->mergeMetadata($receipt, [
                    'cancelled_by' => 'V5.65.7b',
                    'cancelled_at' => now()->toDateTimeString(),
                    'cancel_reason' => $reason,
                    'cancel_replacement_uuid' => $replacementUuid,
                    'cancelled_xml_path' => $cancelledXmlPath,
                    'cancel_pac_response' => $this->safePacResponse($pacResult),
                ])),
                'updated_at' => now(),
            ]);

            $this->syncPayrollRunStatus((int) $receipt->company_id, (int) $receipt->payroll_run_id);

            $this->audit([
                'company_id' => $companyId,
                'payroll_cfdi_receipt_id' => $receiptId,
                'payroll_run_id' => $receipt->payroll_run_id,
                'payroll_run_line_id' => $receipt->payroll_run_line_id,
                'employee_id' => $receipt->employee_id,
                'user_id' => $userId,
                'action' => 'cancel',
                'status' => 'success',
                'pac_provider' => $company->billing_pac_provider ?? $receipt->pac_provider ?? 'sw',
                'pac_test_env' => (bool) ($company->billing_pac_test_env ?? $receipt->pac_test_env ?? false),
                'request_id' => $this->pacRequestId($pacResult),
                'request_meta' => [
                    'uuid' => $receipt->uuid,
                    'reason' => $reason,
                    'replacement_uuid' => $replacementUuid,
                ],
                'response_meta' => $this->safePacResponse($pacResult),
                'message' => 'CFDI nomina cancelado correctamente.',
            ]);

            return [
                'success' => true,
                'blocked' => false,
                'message' => 'CFDI nomina cancelado correctamente.',
                'errors' => [],
                'warnings' => [],
                'summary' => [
                    'company_id' => $companyId,
                    'receipt_id' => $receiptId,
                    'uuid' => $receipt->uuid,
                    'reason' => $reason,
                    'replacement_uuid' => $replacementUuid,
                    'receipt_status' => 'cancelled',
                    'pac_provider' => $company->billing_pac_provider ?? 'sw',
                    'pac_request_id' => $this->pacRequestId($pacResult),
                    'cancelled_xml_path' => $cancelledXmlPath,
                ],
            ];
        } catch (Throwable $e) {
            $this->updateReceipt($companyId, $receiptId, [
                'status' => 'cancel_error',
                'pac_error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            $this->audit([
                'company_id' => $companyId,
                'payroll_cfdi_receipt_id' => $receiptId,
                'payroll_run_id' => $receipt->payroll_run_id ?? null,
                'payroll_run_line_id' => $receipt->payroll_run_line_id ?? null,
                'employee_id' => $receipt->employee_id ?? null,
                'user_id' => $userId,
                'action' => 'cancel',
                'status' => 'error',
                'pac_provider' => $company->billing_pac_provider ?? $receipt->pac_provider ?? 'sw',
                'pac_test_env' => (bool) ($company->billing_pac_test_env ?? $receipt->pac_test_env ?? false),
                'request_id' => null,
                'request_meta' => [
                    'uuid' => $receipt->uuid ?? null,
                    'reason' => $reason,
                    'replacement_uuid' => $replacementUuid,
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
                    'receipt_status' => 'cancel_error',
                ],
            ];
        }
    }

    protected function cancelWithPac(Company $company, PayrollCfdiReceipt $receipt, string $reason, ?string $replacementUuid): array
    {
        $methods = [
            'cancelPayrollCfdi',
            'cancelPayrollUuid',
            'cancelCfdi',
            'cancelUuid',
            'cancel',
        ];

        foreach ($methods as $method) {
            if (! method_exists($this->swPacClient, $method)) {
                continue;
            }

            return $this->callPacMethod($method, $company, $receipt, $reason, $replacementUuid);
        }

        throw new RuntimeException(
            'SwPacClient no tiene metodo de cancelacion compatible. Metodos buscados: ' . implode(', ', $methods)
        );
    }

    protected function callPacMethod(string $method, Company $company, PayrollCfdiReceipt $receipt, string $reason, ?string $replacementUuid): array
    {
        $reflection = new ReflectionMethod($this->swPacClient, $method);
        $count = $reflection->getNumberOfParameters();

        $uuid = (string) $receipt->uuid;

        $argsByCount = [
            1 => [$uuid],
            2 => [$company, $uuid],
            3 => [$company, $uuid, $reason],
            4 => [$company, $uuid, $reason, $replacementUuid],
            5 => [$company, $uuid, $reason, $replacementUuid, $receipt],
        ];

        $args = $argsByCount[min(max($count, 1), 5)] ?? [$company, $uuid, $reason, $replacementUuid];

        $result = $this->swPacClient->{$method}(...$args);

        if (is_array($result)) {
            $result['_bexia_method'] = $method;
            return $result;
        }

        if (is_object($result)) {
            return [
                'success' => true,
                '_bexia_method' => $method,
                'data' => json_decode(json_encode($result), true),
            ];
        }

        return [
            'success' => (bool) $result,
            '_bexia_method' => $method,
            'data' => $result,
        ];
    }

    protected function availableCancelMethods(): array
    {
        return array_values(array_filter([
            method_exists($this->swPacClient, 'cancelPayrollCfdi') ? 'cancelPayrollCfdi' : null,
            method_exists($this->swPacClient, 'cancelPayrollUuid') ? 'cancelPayrollUuid' : null,
            method_exists($this->swPacClient, 'cancelCfdi') ? 'cancelCfdi' : null,
            method_exists($this->swPacClient, 'cancelUuid') ? 'cancelUuid' : null,
            method_exists($this->swPacClient, 'cancel') ? 'cancel' : null,
        ]));
    }

    protected function storeCancellationEvidence(int $companyId, PayrollCfdiReceipt $receipt, array $pacResult): ?string
    {
        $xml = $this->pacXml($pacResult);

        if (! is_string($xml) || trim($xml) === '') {
            return null;
        }

        $path = 'payroll-cfdi/cancellations/company_' . $companyId
            . '/run_' . $receipt->payroll_run_id
            . '/receipt_' . $receipt->id . '_cancel.xml';

        Storage::disk('local')->put($path, $xml);

        return $path;
    }

    protected function syncPayrollRunStatus(int $companyId, int $payrollRunId): void
    {
        if (! Schema::hasTable('payroll_runs')) {
            return;
        }

        $total = DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $payrollRunId)
            ->count();

        $cancelled = DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $payrollRunId)
            ->where('status', 'cancelled')
            ->count();

        $errors = DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $payrollRunId)
            ->whereIn('status', ['error', 'cancel_error'])
            ->count();

        $status = $cancelled > 0 && $cancelled === $total
            ? 'cancelled'
            : ($cancelled > 0 ? 'partial_cancelled' : ($errors > 0 ? 'stamp_error' : null));

        if ($status === null) {
            return;
        }

        DB::table('payroll_runs')
            ->where('company_id', $companyId)
            ->where('id', $payrollRunId)
            ->update([
                'payroll_cfdi_status' => $status,
                'payroll_cfdi_error_lines_count' => $errors,
                'updated_at' => now(),
            ]);
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

    protected function updateReceipt(int $companyId, int $receiptId, array $values): void
    {
        $allowed = [];

        foreach ($values as $column => $value) {
            if (Schema::hasColumn('payroll_cfdi_receipts', $column)) {
                $allowed[$column] = $value;
            }
        }

        if ($allowed === []) {
            return;
        }

        DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('id', $receiptId)
            ->update($allowed);
    }

    protected function assertSchema(): void
    {
        foreach (['payroll_cfdi_receipts', 'payroll_cfdi_audits'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("No existe la tabla requerida: {$table}");
            }
        }
    }

    protected function failure(int $companyId, int $receiptId, string $message, array $errors): array
    {
        return [
            'success' => false,
            'blocked' => false,
            'message' => $message,
            'errors' => $errors,
            'warnings' => [],
            'summary' => [
                'company_id' => $companyId,
                'receipt_id' => $receiptId,
            ],
        ];
    }

    protected function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function mergeMetadata(PayrollCfdiReceipt $receipt, array $extra): array
    {
        return array_merge($this->decode($receipt->metadata ?? null), $extra);
    }

    protected function pacRequestId(array $result): ?string
    {
        foreach ([
            ['request_id'],
            ['requestId'],
            ['id'],
            ['data', 'request_id'],
            ['data', 'requestId'],
            ['data', 'id'],
        ] as $path) {
            $value = $this->arrayGet($result, $path);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    protected function pacMessage(array $result, string $fallback): string
    {
        foreach ([
            ['message'],
            ['error'],
            ['error_message'],
            ['data', 'message'],
            ['data', 'error'],
            ['data', 'error_message'],
        ] as $path) {
            $value = $this->arrayGet($result, $path);

            if (filled($value)) {
                return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }

        return $fallback;
    }

    protected function pacXml(array $result): ?string
    {
        foreach ([
            ['xml'],
            ['cancel_xml'],
            ['acuse'],
            ['acknowledgement'],
            ['data', 'xml'],
            ['data', 'cancel_xml'],
            ['data', 'acuse'],
            ['data', 'acknowledgement'],
        ] as $path) {
            $value = $this->arrayGet($result, $path);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function safePacResponse(array $result): array
    {
        $safe = $result;

        foreach (['xml', 'cancel_xml', 'acuse', 'acknowledgement'] as $key) {
            if (isset($safe[$key]) && is_string($safe[$key])) {
                $safe[$key] = '[XML_LENGTH_' . strlen($safe[$key]) . ']';
            }
        }

        if (isset($safe['data']) && is_array($safe['data'])) {
            foreach (['xml', 'cancel_xml', 'acuse', 'acknowledgement'] as $key) {
                if (isset($safe['data'][$key]) && is_string($safe['data'][$key])) {
                    $safe['data'][$key] = '[XML_LENGTH_' . strlen($safe['data'][$key]) . ']';
                }
            }
        }

        return $safe;
    }

    protected function arrayGet(array $array, array $path): mixed
    {
        $current = $array;

        foreach ($path as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    protected function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
