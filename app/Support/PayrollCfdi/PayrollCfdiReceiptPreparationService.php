<?php

namespace App\Support\PayrollCfdi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PayrollCfdiReceiptPreparationService
{
    public function __construct(
        protected PayrollCfdiReadinessService $readinessService,
    ) {
    }

    public function prepare(int $companyId, int $payrollRunId, ?int $userId = null, bool $force = false): array
    {
        $this->assertSchema();

        $readiness = $this->readinessService->validateCompany($companyId, $payrollRunId);

        if (! ($readiness['success'] ?? false)) {
            return [
                'success' => false,
                'message' => 'La nomina no esta lista para preparar CFDI.',
                'errors' => $readiness['errors'] ?? [],
                'warnings' => $readiness['warnings'] ?? [],
                'summary' => $readiness['summary'] ?? [],
            ];
        }

        $run = DB::table('payroll_runs')
            ->where('company_id', $companyId)
            ->where('id', $payrollRunId)
            ->first();

        if (! $run) {
            return [
                'success' => false,
                'message' => 'No existe la corrida de nomina indicada.',
                'errors' => ["No existe payroll_run_id={$payrollRunId} para company_id={$companyId}."],
                'warnings' => [],
                'summary' => [],
            ];
        }

        $lines = DB::table('payroll_run_lines')
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $payrollRunId)
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            return [
                'success' => false,
                'message' => 'La nomina no tiene lineas.',
                'errors' => ["La nomina {$payrollRunId} no tiene lineas para preparar recibos."],
                'warnings' => [],
                'summary' => [],
            ];
        }

        $result = [
            'success' => true,
            'message' => 'Recibos CFDI nomina preparados en borrador.',
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'warnings' => $readiness['warnings'] ?? [],
            'receipt_ids' => [],
            'summary' => [
                'company_id' => $companyId,
                'payroll_run_id' => $payrollRunId,
                'lines_count' => $lines->count(),
                'force' => $force,
            ],
        ];

        DB::transaction(function () use ($companyId, $payrollRunId, $userId, $force, $run, $lines, &$result): void {
            $company = DB::table('companies')->where('id', $companyId)->first();

            foreach ($lines as $line) {
                try {
                    $existing = DB::table('payroll_cfdi_receipts')
                        ->where('company_id', $companyId)
                        ->where('payroll_run_line_id', $line->id)
                        ->first();

                    if ($existing && ! $force) {
                        $result['skipped']++;
                        $result['receipt_ids'][] = (int) $existing->id;

                        $this->audit([
                            'company_id' => $companyId,
                            'payroll_cfdi_receipt_id' => $existing->id,
                            'payroll_run_id' => $payrollRunId,
                            'payroll_run_line_id' => $line->id,
                            'employee_id' => $line->employee_id,
                            'user_id' => $userId,
                            'action' => 'prepare_draft',
                            'status' => 'skipped',
                            'message' => 'Ya existia recibo CFDI nomina en borrador para esta linea.',
                            'request_meta' => [
                                'force' => $force,
                            ],
                        ]);

                        continue;
                    }

                    if ($existing && in_array((string) $existing->status, ['stamped', 'cancelled'], true)) {
                        $result['skipped']++;
                        $result['warnings'][] = "Linea {$line->id}: ya tiene recibo con status {$existing->status}; no se modifico.";
                        continue;
                    }

                    $employee = DB::table('employees')
                        ->where('company_id', $companyId)
                        ->where('id', $line->employee_id)
                        ->first();

                    $contract = $this->contractForLine($companyId, $line);

                    $lineConcepts = DB::table('payroll_run_line_concepts')
                        ->where('company_id', $companyId)
                        ->where('payroll_run_id', $payrollRunId)
                        ->where('payroll_run_line_id', $line->id)
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->get()
                        ->map(fn ($row) => $this->rowToArray($row))
                        ->values()
                        ->all();

                    $payload = [
                        'company_id' => $companyId,
                        'payroll_run_id' => $payrollRunId,
                        'payroll_run_line_id' => $line->id,
                        'employee_id' => $line->employee_id,
                        'status' => 'draft',
                        'cfdi_version' => '4.0',
                        'payroll_complement_version' => '1.2',
                        'series' => 'NOM',
                        'folio' => 'RUN' . $payrollRunId . '-LINE' . $line->id,
                        'uuid' => null,
                        'pac_provider' => $company->billing_pac_provider ?? null,
                        'pac_test_env' => (bool) ($company->billing_pac_test_env ?? true),
                        'pac_request_id' => null,
                        'pac_error_message' => null,
                        'xml_path' => null,
                        'pdf_path' => null,
                        'validated_at' => now(),
                        'stamped_at' => null,
                        'cancelled_at' => null,
                        'issuer_snapshot' => $this->json([
                            'company_id' => $companyId,
                            'name' => $company->name ?? null,
                            'business_name' => $company->business_name ?? null,
                            'tax_id' => $company->tax_id ?? null,
                            'tax_regime' => $company->tax_regime ?? null,
                            'fiscal_postal_code' => $company->fiscal_postal_code ?? null,
                        ]),
                        'employee_snapshot' => $this->json([
                            'employee_id' => $employee->id ?? null,
                            'name' => $employee->name ?? null,
                            'fiscal_name' => $employee->fiscal_name ?? $employee->name ?? null,
                            'rfc' => $employee->rfc ?? null,
                            'curp' => $employee->curp ?? null,
                            'social_security_number' => $employee->social_security_number ?? $employee->ssn ?? null,
                            'fiscal_postal_code' => $employee->fiscal_postal_code ?? null,
                            'sat_tax_regime_code' => $employee->sat_tax_regime_code ?? null,
                        ]),
                        'contract_snapshot' => $this->json($contract ? $this->rowToArray($contract) : []),
                        'totals_snapshot' => $this->json([
                            'period_start' => $run->period_start ?? null,
                            'period_end' => $run->period_end ?? null,
                            'payment_date' => $run->payment_date ?? null,
                            'period_days' => $line->period_days ?? null,
                            'payable_days' => $line->payable_days ?? null,
                            'base_amount' => $line->base_amount ?? 0,
                            'overtime_amount' => $line->overtime_amount ?? 0,
                            'incident_perceptions' => $line->incident_perceptions ?? 0,
                            'incident_deductions' => $line->incident_deductions ?? 0,
                            'gross_amount' => $line->gross_amount ?? 0,
                            'deductions_amount' => $line->deductions_amount ?? 0,
                            'net_amount' => $line->net_amount ?? 0,
                        ]),
                        'validation_errors' => null,
                        'metadata' => $this->json([
                            'source' => 'payroll_run',
                            'prepared_by' => 'V5.65.3a',
                            'line_concepts' => $lineConcepts,
                        ]),
                        'updated_at' => now(),
                    ];

                    if ($existing) {
                        DB::table('payroll_cfdi_receipts')
                            ->where('id', $existing->id)
                            ->update($payload);

                        $receiptId = (int) $existing->id;
                        $result['updated']++;
                    } else {
                        $payload['created_at'] = now();
                        $receiptId = (int) DB::table('payroll_cfdi_receipts')->insertGetId($payload);
                        $result['created']++;
                    }

                    $result['receipt_ids'][] = $receiptId;

                    if (Schema::hasColumn('payroll_run_lines', 'payroll_cfdi_status')) {
                        DB::table('payroll_run_lines')
                            ->where('id', $line->id)
                            ->update([
                                'payroll_cfdi_status' => 'draft',
                                'payroll_cfdi_validation_errors' => null,
                                'updated_at' => now(),
                            ]);
                    }

                    $this->audit([
                        'company_id' => $companyId,
                        'payroll_cfdi_receipt_id' => $receiptId,
                        'payroll_run_id' => $payrollRunId,
                        'payroll_run_line_id' => $line->id,
                        'employee_id' => $line->employee_id,
                        'user_id' => $userId,
                        'action' => 'prepare_draft',
                        'status' => 'success',
                        'pac_provider' => $company->billing_pac_provider ?? null,
                        'pac_test_env' => (bool) ($company->billing_pac_test_env ?? true),
                        'message' => 'Recibo CFDI nomina preparado en borrador.',
                        'request_meta' => [
                            'force' => $force,
                            'folio' => $payload['folio'],
                        ],
                    ]);
                } catch (Throwable $e) {
                    $result['success'] = false;
                    $result['errors'][] = "Linea {$line->id}: " . $e->getMessage();

                    $this->audit([
                        'company_id' => $companyId,
                        'payroll_run_id' => $payrollRunId,
                        'payroll_run_line_id' => $line->id,
                        'employee_id' => $line->employee_id ?? null,
                        'user_id' => $userId,
                        'action' => 'prepare_draft',
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            $status = $result['success'] ? 'drafts_prepared' : 'drafts_error';

            DB::table('payroll_runs')
                ->where('company_id', $companyId)
                ->where('id', $payrollRunId)
                ->update([
                    'payroll_cfdi_status' => $status,
                    'payroll_cfdi_validated_at' => now(),
                    'payroll_cfdi_ready_lines_count' => $result['created'] + $result['updated'] + $result['skipped'],
                    'payroll_cfdi_error_lines_count' => count($result['errors']),
                    'payroll_cfdi_validation_errors' => count($result['errors']) > 0 ? $this->json($result['errors']) : null,
                    'updated_at' => now(),
                ]);
        });

        return $result;
    }

    protected function assertSchema(): void
    {
        foreach (['payroll_cfdi_receipts', 'payroll_cfdi_audits', 'payroll_runs', 'payroll_run_lines'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException("No existe la tabla requerida: {$table}");
            }
        }
    }

    protected function contractForLine(int $companyId, object $line): ?object
    {
        if (! Schema::hasTable('employee_contracts')) {
            return null;
        }

        if (! empty($line->employee_contract_id)) {
            $contract = DB::table('employee_contracts')
                ->where('company_id', $companyId)
                ->where('id', $line->employee_contract_id)
                ->first();

            if ($contract) {
                return $contract;
            }
        }

        return DB::table('employee_contracts')
            ->where('company_id', $companyId)
            ->where('employee_id', $line->employee_id)
            ->where('is_current', true)
            ->orderByDesc('id')
            ->first();
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

    protected function rowToArray(object $row): array
    {
        return json_decode(json_encode($row, JSON_UNESCAPED_UNICODE), true) ?: [];
    }

    protected function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
