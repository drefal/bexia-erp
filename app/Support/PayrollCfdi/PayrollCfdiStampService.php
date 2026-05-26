<?php

namespace App\Support\PayrollCfdi;

use App\Models\Company;
use App\Models\PayrollCfdiReceipt;
use App\Support\Billing\SwPacClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PayrollCfdiStampService
{
    public function __construct(
        protected PayrollCfdiStampingGuardService $guard,
        protected SwPacClient $swPacClient,
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

        $company = Company::query()->find($companyId);

        if (! $company) {
            return $this->failure(
                companyId: $companyId,
                receiptId: $receiptId,
                message: 'No existe la empresa para timbrado CFDI nomina.',
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
            DB::table('payroll_cfdi_receipts')
                ->where('company_id', $companyId)
                ->where('id', $receiptId)
                ->update([
                    'status' => 'stamping',
                    'pac_provider' => $company->billing_pac_provider ?? $receipt->pac_provider,
                    'pac_test_env' => (bool) ($company->billing_pac_test_env ?? $receipt->pac_test_env ?? false),
                    'pac_error_message' => null,
                    'updated_at' => now(),
                ]);

            $xml = $this->loadXml($receipt);

            $this->audit([
                'company_id' => $companyId,
                'payroll_cfdi_receipt_id' => $receiptId,
                'payroll_run_id' => $receipt->payroll_run_id,
                'payroll_run_line_id' => $receipt->payroll_run_line_id,
                'employee_id' => $receipt->employee_id,
                'user_id' => $userId,
                'action' => 'stamp',
                'status' => 'sending_to_pac',
                'pac_provider' => $company->billing_pac_provider ?? $receipt->pac_provider ?? 'sw',
                'pac_test_env' => (bool) ($company->billing_pac_test_env ?? $receipt->pac_test_env ?? false),
                'request_id' => null,
                'request_meta' => [
                    'xml_path' => $receipt->xml_path,
                    'xml_length' => strlen($xml),
                    'client' => SwPacClient::class,
                    'method' => 'stampSignedXml',
                ],
                'response_meta' => null,
                'message' => 'Enviando CFDI nomina al PAC SW desde ambiente permitido.',
            ]);

            $pacResult = $this->swPacClient->stampSignedXml($company, $xml);

            if (! ($pacResult['success'] ?? false)) {
                $message = $this->pacMessage($pacResult, 'PAC rechazo o no pudo timbrar CFDI nomina.');

                DB::table('payroll_cfdi_receipts')
                    ->where('company_id', $companyId)
                    ->where('id', $receiptId)
                    ->update([
                        'status' => 'error',
                        'pac_request_id' => $this->pacRequestId($pacResult),
                        'pac_error_message' => $message,
                        'metadata' => $this->json($this->mergeMetadata($receipt, [
                            'stamp_error_at' => now()->toDateTimeString(),
                            'stamp_error_source' => 'V5.65.5c',
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
                    'action' => 'stamp',
                    'status' => 'error',
                    'pac_provider' => $company->billing_pac_provider ?? $receipt->pac_provider ?? 'sw',
                    'pac_test_env' => (bool) ($company->billing_pac_test_env ?? $receipt->pac_test_env ?? false),
                    'request_id' => $this->pacRequestId($pacResult),
                    'request_meta' => [
                        'xml_path' => $receipt->xml_path,
                    ],
                    'response_meta' => $pacResult,
                    'message' => $message,
                ]);

                return [
                    'success' => false,
                    'blocked' => false,
                    'message' => 'No se pudo timbrar CFDI nomina.',
                    'errors' => [$message],
                    'warnings' => [],
                    'summary' => [
                        'company_id' => $companyId,
                        'receipt_id' => $receiptId,
                        'receipt_status' => 'error',
                        'pac_provider' => $company->billing_pac_provider ?? 'sw',
                        'pac_test_env' => (bool) ($company->billing_pac_test_env ?? false),
                        'pac_request_id' => $this->pacRequestId($pacResult),
                    ],
                ];
            }

            $uuid = $this->pacUuid($pacResult);
            $stampedXml = $this->pacXml($pacResult) ?: $xml;

            if (blank($uuid)) {
                throw new RuntimeException('El PAC respondio success, pero no devolvio UUID.');
            }

            $stampedPath = 'payroll-cfdi/stamped/company_' . $companyId
                . '/run_' . $receipt->payroll_run_id
                . '/receipt_' . $receipt->id . '.xml';

            Storage::disk('local')->put($stampedPath, $stampedXml);

            DB::table('payroll_cfdi_receipts')
                ->where('company_id', $companyId)
                ->where('id', $receiptId)
                ->update([
                    'status' => 'stamped',
                    'uuid' => $uuid,
                    'pac_provider' => $company->billing_pac_provider ?? $receipt->pac_provider ?? 'sw',
                    'pac_test_env' => (bool) ($company->billing_pac_test_env ?? $receipt->pac_test_env ?? false),
                    'pac_request_id' => $this->pacRequestId($pacResult),
                    'pac_error_message' => null,
                    'xml_path' => $stampedPath,
                    'stamped_at' => now(),
                    'metadata' => $this->json($this->mergeMetadata($receipt, [
                        'stamped_by' => 'V5.65.5c',
                        'stamped_xml_path' => $stampedPath,
                        'original_draft_xml_path' => $receipt->xml_path,
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
                'action' => 'stamp',
                'status' => 'success',
                'pac_provider' => $company->billing_pac_provider ?? $receipt->pac_provider ?? 'sw',
                'pac_test_env' => (bool) ($company->billing_pac_test_env ?? $receipt->pac_test_env ?? false),
                'request_id' => $this->pacRequestId($pacResult),
                'request_meta' => [
                    'draft_xml_path' => $receipt->xml_path,
                    'stamped_xml_path' => $stampedPath,
                ],
                'response_meta' => $this->safePacResponse($pacResult),
                'message' => 'CFDI nomina timbrado correctamente.',
            ]);

            return [
                'success' => true,
                'blocked' => false,
                'message' => 'CFDI nomina timbrado correctamente.',
                'errors' => [],
                'warnings' => [],
                'summary' => [
                    'company_id' => $companyId,
                    'receipt_id' => $receiptId,
                    'receipt_status' => 'stamped',
                    'uuid' => $uuid,
                    'pac_provider' => $company->billing_pac_provider ?? 'sw',
                    'pac_test_env' => (bool) ($company->billing_pac_test_env ?? false),
                    'pac_request_id' => $this->pacRequestId($pacResult),
                    'xml_path' => $stampedPath,
                ],
            ];
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
                'payroll_run_id' => $receipt->payroll_run_id ?? null,
                'payroll_run_line_id' => $receipt->payroll_run_line_id ?? null,
                'employee_id' => $receipt->employee_id ?? null,
                'user_id' => $userId,
                'action' => 'stamp',
                'status' => 'error',
                'pac_provider' => $company->billing_pac_provider ?? $receipt->pac_provider ?? 'sw',
                'pac_test_env' => (bool) ($company->billing_pac_test_env ?? $receipt->pac_test_env ?? false),
                'request_id' => null,
                'request_meta' => [
                    'xml_path' => $receipt->xml_path ?? null,
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
                    'xml_path' => $receipt->xml_path ?? null,
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

    protected function syncPayrollRunStatus(int $companyId, int $payrollRunId): void
    {
        if (! Schema::hasTable('payroll_runs')) {
            return;
        }

        $total = DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $payrollRunId)
            ->count();

        $stamped = DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $payrollRunId)
            ->where('status', 'stamped')
            ->count();

        $errors = DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $payrollRunId)
            ->where('status', 'error')
            ->count();

        $status = $stamped > 0 && $stamped === $total
            ? 'stamped'
            : ($errors > 0 ? 'stamp_error' : 'partial_stamped');

        DB::table('payroll_runs')
            ->where('company_id', $companyId)
            ->where('id', $payrollRunId)
            ->update([
                'payroll_cfdi_status' => $status,
                'payroll_cfdi_ready_lines_count' => $stamped,
                'payroll_cfdi_error_lines_count' => $errors,
                'updated_at' => now(),
            ]);
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
        $metadata = $this->decode($receipt->metadata ?? null);

        return array_merge($metadata, $extra);
    }

    protected function pacUuid(array $result): ?string
    {
        foreach ([
            ['uuid'],
            ['UUID'],
            ['data', 'uuid'],
            ['data', 'UUID'],
            ['data', 'tfd', 'UUID'],
            ['data', 'tfd', 'uuid'],
            ['cfdi', 'uuid'],
            ['cfdi', 'UUID'],
        ] as $path) {
            $value = $this->arrayGet($result, $path);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    protected function pacXml(array $result): ?string
    {
        foreach ([
            ['xml'],
            ['stamped_xml'],
            ['cfdi'],
            ['data', 'xml'],
            ['data', 'stamped_xml'],
            ['data', 'cfdi'],
            ['data', 'xml_cfdi'],
        ] as $path) {
            $value = $this->arrayGet($result, $path);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
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

    protected function safePacResponse(array $result): array
    {
        $safe = $result;

        foreach (['xml', 'stamped_xml', 'cfdi'] as $key) {
            if (isset($safe[$key]) && is_string($safe[$key])) {
                $safe[$key] = '[XML_LENGTH_' . strlen($safe[$key]) . ']';
            }
        }

        if (isset($safe['data']) && is_array($safe['data'])) {
            foreach (['xml', 'stamped_xml', 'cfdi', 'xml_cfdi'] as $key) {
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
