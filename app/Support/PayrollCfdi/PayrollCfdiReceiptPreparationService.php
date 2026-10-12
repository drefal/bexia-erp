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

        $mode = PayrollCfdiModeGuard::validate(
            $companyId,
            $payrollRunId
        );

        if (! ($mode['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $mode['message']
                    ?? 'Modo de nómina no permitido para CFDI.',
                'errors' => [
                    $mode['error']
                    ?? 'La corrida no permite CFDI.',
                ],
                'warnings' => [],
                'summary' => [
                    'company_id' => $companyId,
                    'payroll_run_id' => $payrollRunId,
                ],
            ];
        }

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

                    $contract = $this->contractForLine(
                        $companyId,
                        $line
                    );

                    /*
                     * BEXIA_PHASE3D_FISCAL_SNAPSHOT
                     *
                     * Congelamos la periodicidad SAT y el resultado fiscal
                     * de la última versión de la corrida.
                     */
                    $periodicitySatCode = null;

                    if (
                        $contract
                        && ! empty(
                            $contract->payroll_periodicity_id
                        )
                        && Schema::hasTable(
                            'payroll_periodicities'
                        )
                    ) {
                        $periodicitySatCode = DB::table(
                            'payroll_periodicities'
                        )
                            ->where(
                                'id',
                                $contract
                                    ->payroll_periodicity_id
                            )
                            ->value('sat_code');
                    }

                    /*
                     * BEXIA_V5836_STRICT_PERIODICITY_SNAPSHOT
                     *
                     * Para CFDI la periodicidad SAT debe venir del
                     * catálogo contractual real. No reconstruirla
                     * posteriormente desde period_type.
                     */
                    if (
                        (string) (
                            $run->calculation_mode_snapshot
                            ?? ''
                        ) === 'fiscal_cfdi'
                        && trim(
                            (string) $periodicitySatCode
                        ) === ''
                    ) {
                        throw new \RuntimeException(
                            'El contrato del empleado '
                            . $line->employee_id
                            . ' no tiene una periodicidad de pago '
                            . 'con clave SAT válida para CFDI.'
                        );
                    }

                    $fiscalResult = null;
                    $fiscalSnapshot = [];

                    /*
                     * BEXIA_V5836_EXACT_FISCAL_VERSION_BINDING
                     *
                     * La versión vigente de una corrida es la de mayor
                     * version_number dentro de ESA corrida.
                     *
                     * payroll_run_version_id es un ID global y no debe
                     * utilizarse para inferir cuál versión es vigente.
                     *
                     * Tampoco hacemos fallback silencioso a un resultado
                     * fiscal de una versión anterior.
                     */
                    $latestRunVersion = null;

                    if (
                        Schema::hasTable(
                            'payroll_run_versions'
                        )
                    ) {
                        $latestRunVersion = DB::table(
                            'payroll_run_versions'
                        )
                            ->where(
                                'company_id',
                                $companyId
                            )
                            ->where(
                                'payroll_run_id',
                                $payrollRunId
                            )
                            ->orderByDesc(
                                'version_number'
                            )
                            ->orderByDesc(
                                'id'
                            )
                            ->first();
                    }

                    if (
                        Schema::hasTable(
                            'payroll_fiscal_run_results'
                        )
                        && $latestRunVersion
                    ) {
                        $fiscalResult = DB::table(
                            'payroll_fiscal_run_results'
                        )
                            ->where(
                                'company_id',
                                $companyId
                            )
                            ->where(
                                'payroll_run_id',
                                $payrollRunId
                            )
                            ->where(
                                'payroll_run_version_id',
                                $latestRunVersion->id
                            )
                            ->where(
                                'employee_id',
                                $line->employee_id
                            )
                            ->first();

                        if (
                            $fiscalResult
                            && ! empty(
                                $fiscalResult
                                    ->calculation_snapshot
                            )
                        ) {
                            $decoded = json_decode(
                                (string) $fiscalResult
                                    ->calculation_snapshot,
                                true
                            );

                            $fiscalSnapshot =
                                is_array($decoded)
                                    ? $decoded
                                    : [];
                        }
                    }

                    /*
                     * BEXIA_V5836_REQUIRE_FISCAL_RESULT_FOR_LATEST_VERSION
                     *
                     * Una corrida fiscal_cfdi no puede preparar un recibo
                     * con datos fiscales de otra versión ni sin resultado
                     * fiscal correspondiente a su última versión.
                     */
                    if (
                        (string) (
                            $run->calculation_mode_snapshot
                            ?? ''
                        ) === 'fiscal_cfdi'
                    ) {
                        if (! $latestRunVersion) {
                            throw new \RuntimeException(
                                'La corrida fiscal no tiene una versión '
                                . 'de cálculo disponible para CFDI.'
                            );
                        }

                        if (! $fiscalResult) {
                            throw new \RuntimeException(
                                'No existe resultado fiscal para el empleado '
                                . $line->employee_id
                                . ' en la versión V'
                                . $latestRunVersion->version_number
                                . ' de la corrida. No se utilizará una '
                                . 'versión fiscal anterior.'
                            );
                        }

                        if (
                            (int) $fiscalResult
                                ->payroll_run_version_id
                            !== (int) $latestRunVersion->id
                        ) {
                            throw new \RuntimeException(
                                'El resultado fiscal no corresponde '
                                . 'a la versión vigente de la corrida.'
                            );
                        }
                    }

                    /*
                     * BEXIA_V5836_RECEIPT_VERSION_LINE_BINDING
                     *
                     * El resultado fiscal, los conceptos y los totales
                     * del recibo deben pertenecer a la MISMA versión.
                     */
                    if (! $latestRunVersion) {
                        throw new \RuntimeException(
                            'La corrida no tiene versión congelada '
                            . 'disponible para preparar CFDI.'
                        );
                    }

                    $versionLineSnapshot =
                        $this->exactVersionLineSnapshot(
                            $companyId,
                            $payrollRunId,
                            $latestRunVersion,
                            $line
                        );

                    $versionLine =
                        $versionLineSnapshot['line'];

                    $versionLineConcepts =
                        $versionLineSnapshot['concepts'];

                    $versionLineDetails =
                        $versionLineSnapshot['details'];

                    /*
                     * BEXIA_V5836_PAYROLL_FISCAL_ORG_SNAPSHOT
                     *
                     * Congelar nombres organizacionales reales en el
                     * recibo. El XML nunca debe inventar GENERAL.
                     */
                    $contractSnapshot = $contract
                        ? $this->rowToArray($contract)
                        : [];

                    if ($contract) {
                        $departmentName = null;
                        $positionName = null;

                        if (
                            ! empty($contract->hr_department_id)
                            && Schema::hasTable('hr_departments')
                        ) {
                            $departmentName = DB::table(
                                'hr_departments'
                            )
                                ->where(
                                    'company_id',
                                    $companyId
                                )
                                ->where(
                                    'id',
                                    $contract->hr_department_id
                                )
                                ->value('name');
                        }

                        if (
                            ! empty($contract->hr_job_position_id)
                            && Schema::hasTable('hr_job_positions')
                        ) {
                            $positionName = DB::table(
                                'hr_job_positions'
                            )
                                ->where(
                                    'company_id',
                                    $companyId
                                )
                                ->where(
                                    'id',
                                    $contract->hr_job_position_id
                                )
                                ->value('name');
                        }

                        $contractSnapshot['department'] =
                            filled($departmentName)
                                ? trim((string) $departmentName)
                                : null;

                        $contractSnapshot['position'] =
                            filled($positionName)
                                ? trim((string) $positionName)
                                : null;

                        /*
                         * BEXIA_V5836_EMPLOYER_REGISTRATION_SNAPSHOT
                         *
                         * Congelar el número real del registro patronal.
                         * El XML no debe consultar el catálogo vivo después.
                         */
                        $employerRegistration = null;

                        if (
                            ! empty(
                                $contract
                                    ->payroll_employer_registration_id
                            )
                            && Schema::hasTable(
                                'payroll_employer_registrations'
                            )
                        ) {
                            $employerRegistration = DB::table(
                                'payroll_employer_registrations'
                            )
                                ->where(
                                    'id',
                                    $contract
                                        ->payroll_employer_registration_id
                                )
                                ->where(
                                    'company_id',
                                    $companyId
                                )
                                ->first();
                        }

                        $contractSnapshot[
                            'employer_registration_number'
                        ] = $employerRegistration
                            ? trim(
                                (string) (
                                    $employerRegistration
                                        ->registration_number
                                    ?? ''
                                )
                            )
                            : null;

                        $contractSnapshot[
                            'employer_registration_name'
                        ] = $employerRegistration
                            ? trim(
                                (string) (
                                    $employerRegistration->name
                                    ?? ''
                                )
                            )
                            : null;

                        $contractSnapshot[
                            'employer_registration_company_id'
                        ] = $employerRegistration
                            ? (
                                (int) (
                                    $employerRegistration
                                        ->employer_company_id
                                    ?? 0
                                )
                                ?: null
                            )
                            : null;
                    }

                    /*
                     * BEXIA_V5836_RECEIPT_CONCEPTS_FROM_VERSION
                     *
                     * Nunca leer payroll_run_line_concepts vivo
                     * para construir el recibo CFDI.
                     */
                    $lineConcepts =
                        $versionLineConcepts;

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
                        'contract_snapshot' => $this->json(
                            $contractSnapshot
                        ),
                        /*
                         * BEXIA_V5836_RECEIPT_TOTALS_FROM_VERSION
                         *
                         * Periodo, modo lógico y montos provienen de
                         * la misma versión exacta que el resultado fiscal.
                         */
                        'totals_snapshot' => $this->json([
                            'payroll_run_version_id' =>
                                $latestRunVersion->id,

                            'payroll_run_version_number' =>
                                $latestRunVersion
                                    ->version_number,

                            'period_start' =>
                                $latestRunVersion
                                    ->period_start
                                ?? null,

                            'period_end' =>
                                $latestRunVersion
                                    ->period_end
                                ?? null,

                            'payment_date' =>
                                $latestRunVersion
                                    ->payment_date
                                ?? null,

                            'period_type' =>
                                $latestRunVersion
                                    ->period_type
                                ?? null,

                            /*
                             * BEXIA_V5836_STRICT_RUN_TYPE_SNAPSHOT
                             *
                             * No volver al payroll_run vivo.
                             */
                            'run_type' =>
                                $latestRunVersion
                                    ->run_type,

                            'payroll_periodicity_sat_code' =>
                                $periodicitySatCode,

                            'work_state_sat_code' =>
                                $contract
                                    ? (
                                        $contract
                                            ->sat_work_state_code
                                        ?? null
                                    )
                                    : null,

                            'period_days' =>
                                $versionLine
                                    ->period_days
                                ?? null,

                            'payable_days' =>
                                $versionLine
                                    ->payable_days
                                ?? null,

                            'base_amount' =>
                                $versionLine
                                    ->base_amount
                                ?? 0,

                            'overtime_amount' =>
                                $versionLine
                                    ->overtime_amount
                                ?? 0,

                            'incident_perceptions' =>
                                $versionLine
                                    ->incident_perceptions
                                ?? 0,

                            'incident_deductions' =>
                                $versionLine
                                    ->incident_deductions
                                ?? 0,

                            'gross_amount' =>
                                $versionLine
                                    ->gross_amount
                                ?? 0,

                            'deductions_amount' =>
                                $versionLine
                                    ->deductions_amount
                                ?? 0,

                            'net_amount' =>
                                $versionLine
                                    ->net_amount
                                ?? 0,
                        ]),
                        'validation_errors' => null,
                        'metadata' => $this->json([
                            'source' => 'payroll_run_version',

                            'prepared_by' => 'V5.83.6',

                            /*
                             * BEXIA_V5836_RECEIPT_DETAILS_FROM_VERSION
                             */
                            'payroll_run_version' => [
                                'id' =>
                                    $latestRunVersion->id,

                                'version_number' =>
                                    $latestRunVersion
                                        ->version_number,
                            ],

                            'line_concepts' =>
                                $lineConcepts,

                            'line_details' =>
                                $versionLineDetails,

                            'fiscal_result' =>
                                $fiscalResult
                                    ? array_merge(
                                        $this->rowToArray(
                                            $fiscalResult
                                        ),
                                        [
                                            'calculation_snapshot' =>
                                                $fiscalSnapshot,
                                        ]
                                    )
                                    : null,
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

    /**
     * BEXIA_V5836_RECEIPT_EXACT_VERSION_LINE
     *
     * Obtiene exclusivamente la línea congelada perteneciente
     * a la versión exacta usada para el resultado fiscal.
     *
     * No se permite reconstruir conceptos, details o totales
     * desde payroll_run_lines / payroll_run_line_concepts vivos.
     */
    protected function exactVersionLineSnapshot(
        int $companyId,
        int $payrollRunId,
        object $runVersion,
        object $liveLine
    ): array {
        if (
            ! Schema::hasTable(
                'payroll_run_version_lines'
            )
        ) {
            throw new \RuntimeException(
                'No existe payroll_run_version_lines '
                . 'para congelar el recibo CFDI.'
            );
        }

        if (
            (int) (
                $runVersion->payroll_run_id
                ?? 0
            ) !== $payrollRunId
        ) {
            throw new \RuntimeException(
                'La versión indicada no pertenece '
                . 'a la corrida solicitada.'
            );
        }

        $versionLine = DB::table(
            'payroll_run_version_lines'
        )
            ->where(
                'company_id',
                $companyId
            )
            ->where(
                'payroll_run_version_id',
                $runVersion->id
            )
            ->where(
                'original_payroll_run_line_id',
                $liveLine->id
            )
            ->where(
                'employee_id',
                $liveLine->employee_id
            )
            ->first();

        if (! $versionLine) {
            throw new \RuntimeException(
                'No existe línea congelada para el empleado '
                . ($liveLine->employee_id ?? 'N/D')
                . ' en la versión V'
                . ($runVersion->version_number ?? 'N/D')
                . '.'
            );
        }

        /*
         * BEXIA_V5836_RECEIPT_VERSION_JSON_DECODE
         *
         * Este servicio no usa PayrollCfdiXmlDraftService::decode().
         * Decodificar aquí los JSON congelados de la versión.
         */
        $conceptsRaw =
            $versionLine->concepts_snapshot
            ?? null;

        $detailsRaw =
            $versionLine->details
            ?? null;

        $concepts = is_array($conceptsRaw)
            ? $conceptsRaw
            : (
                $conceptsRaw !== null
                && $conceptsRaw !== ''
                    ? json_decode(
                        (string) $conceptsRaw,
                        true
                    )
                    : []
            );

        $details = is_array($detailsRaw)
            ? $detailsRaw
            : (
                $detailsRaw !== null
                && $detailsRaw !== ''
                    ? json_decode(
                        (string) $detailsRaw,
                        true
                    )
                    : []
            );

        if (! is_array($concepts)) {
            throw new \RuntimeException(
                'La línea de versión no contiene '
                . 'concepts_snapshot válido.'
            );
        }

        return [
            'line' => $versionLine,
            'concepts' => array_values(
                $concepts
            ),
            'details' => $details,
        ];
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
