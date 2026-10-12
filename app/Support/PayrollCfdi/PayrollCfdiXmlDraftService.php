<?php

namespace App\Support\PayrollCfdi;

use DOMDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PayrollCfdiXmlDraftService
{
    public function prepareForRun(int $companyId, int $payrollRunId, ?int $userId = null, bool $force = false): array
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
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [
                    $mode['error']
                    ?? 'La corrida no permite CFDI.',
                ],
                'warnings' => [],
                'xml_paths' => [],
                'summary' => [
                    'company_id' => $companyId,
                    'payroll_run_id' => $payrollRunId,
                    'receipts_count' => 0,
                ],
            ];
        }

        $receipts = DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $payrollRunId)
            ->orderBy('id')
            ->get();

        if ($receipts->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No hay recibos CFDI nomina para esta corrida.',
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => ["No existen payroll_cfdi_receipts para payroll_run_id={$payrollRunId}."],
                'warnings' => [],
                'summary' => [
                    'company_id' => $companyId,
                    'payroll_run_id' => $payrollRunId,
                    'receipts_count' => 0,
                ],
            ];
        }

        $result = [
            'success' => true,
            'message' => 'XML CFDI nomina en borrador generados.',
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'warnings' => [],
            'xml_paths' => [],
            'summary' => [
                'company_id' => $companyId,
                'payroll_run_id' => $payrollRunId,
                'receipts_count' => $receipts->count(),
                'force' => $force,
            ],
        ];

        DB::transaction(function () use ($companyId, $payrollRunId, $userId, $force, $receipts, &$result): void {
            foreach ($receipts as $receipt) {
                try {
                    if (in_array((string) $receipt->status, ['stamped', 'cancelled'], true)) {
                        $result['skipped']++;
                        $result['warnings'][] = "Recibo {$receipt->id}: status {$receipt->status}; no se modifica.";
                        continue;
                    }

                    if (! $force && filled($receipt->xml_path ?? null)) {
                        $result['skipped']++;
                        $result['xml_paths'][] = (string) $receipt->xml_path;
                        continue;
                    }

                    $xml = $this->buildXml($receipt);

                    $path = 'payroll-cfdi/drafts/company_' . $companyId
                        . '/run_' . $payrollRunId
                        . '/receipt_' . $receipt->id . '.xml';

                    Storage::disk('local')->put($path, $xml);

                    DB::table('payroll_cfdi_receipts')
                        ->where('id', $receipt->id)
                        ->update([
                            'status' => 'validated',
                            'xml_path' => $path,
                            'validated_at' => now(),
                            'validation_errors' => null,
                            'metadata' => $this->json($this->mergeMetadata($receipt, [
                                'xml_draft_prepared_by' => 'V5.65.4a',
                                'xml_draft_prepared_at' => now()->toDateTimeString(),
                                'xml_draft_note' => 'XML tecnico en borrador. No timbrado. No enviado a PAC/SAT.',
                            ])),
                            'updated_at' => now(),
                        ]);

                    DB::table('payroll_cfdi_audits')->insert([
                        'company_id' => $companyId,
                        'payroll_cfdi_receipt_id' => $receipt->id,
                        'payroll_run_id' => $payrollRunId,
                        'payroll_run_line_id' => $receipt->payroll_run_line_id,
                        'employee_id' => $receipt->employee_id,
                        'user_id' => $userId,
                        'action' => 'prepare_xml_draft',
                        'status' => 'success',
                        'pac_provider' => $receipt->pac_provider ?? null,
                        'pac_test_env' => $receipt->pac_test_env ?? null,
                        'request_id' => null,
                        'request_meta' => $this->json([
                            'force' => $force,
                            'xml_path' => $path,
                        ]),
                        'response_meta' => null,
                        'message' => 'XML CFDI nomina en borrador generado localmente. No timbrado.',
                        'ip_address' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $result['updated']++;
                    $result['xml_paths'][] = $path;
                } catch (Throwable $e) {
                    $result['success'] = false;
                    $result['errors'][] = "Recibo {$receipt->id}: " . $e->getMessage();

                    DB::table('payroll_cfdi_audits')->insert([
                        'company_id' => $companyId,
                        'payroll_cfdi_receipt_id' => $receipt->id,
                        'payroll_run_id' => $payrollRunId,
                        'payroll_run_line_id' => $receipt->payroll_run_line_id ?? null,
                        'employee_id' => $receipt->employee_id ?? null,
                        'user_id' => $userId,
                        'action' => 'prepare_xml_draft',
                        'status' => 'error',
                        'pac_provider' => $receipt->pac_provider ?? null,
                        'pac_test_env' => $receipt->pac_test_env ?? null,
                        'request_id' => null,
                        'request_meta' => $this->json([
                            'force' => $force,
                        ]),
                        'response_meta' => null,
                        'message' => $e->getMessage(),
                        'ip_address' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('payroll_runs')
                ->where('company_id', $companyId)
                ->where('id', $payrollRunId)
                ->update([
                    'payroll_cfdi_status' => $result['success'] ? 'xml_drafts_prepared' : 'xml_drafts_error',
                    'payroll_cfdi_validated_at' => now(),
                    'payroll_cfdi_ready_lines_count' => $result['updated'] + $result['skipped'],
                    'payroll_cfdi_error_lines_count' => count($result['errors']),
                    'payroll_cfdi_validation_errors' => count($result['errors']) > 0 ? $this->json($result['errors']) : null,
                    'updated_at' => now(),
                ]);
        });

        return $result;
    }

    public function buildXml(object $receipt): string
    {
        $issuer = $this->decode($receipt->issuer_snapshot);
        $employee = $this->decode($receipt->employee_snapshot);
        $contract = $this->decode($receipt->contract_snapshot);
        $totals = $this->decode($receipt->totals_snapshot);
        $metadata = $this->decode($receipt->metadata);

        $lineConcepts = $metadata['line_concepts'] ?? [];

        /*
         * BEXIA_V5836_XML_INCAPACITY_ROWS_FROM_RECEIPT
         *
         * Sólo datos congelados en el recibo/version line.
         */
        $incapacityRows =
            $this->incapacitySnapshotRows(
                $metadata
            );

        $fiscalResult =
            is_array($metadata['fiscal_result'] ?? null)
                ? $metadata['fiscal_result']
                : [];

        $fiscalSnapshot =
            is_array(
                $fiscalResult[
                    'calculation_snapshot'
                ] ?? null
            )
                ? $fiscalResult[
                    'calculation_snapshot'
                ]
                : [];

        $reconciliation =
            is_array(
                $fiscalSnapshot[
                    'subsidy_reconciliation'
                ] ?? null
            )
                ? $fiscalSnapshot[
                    'subsidy_reconciliation'
                ]
                : [];

        $perceptions = [];
        $deductions = [];

        foreach ($lineConcepts as $concept) {
            $type = (string) ($concept['type'] ?? '');

            if ($type === 'deduction') {
                $deductions[] = $concept;
                continue;
            }

            if ($type === 'perception') {
                $perceptions[] = $concept;
            }
        }

        $gross = round(
            (float) ($totals['gross_amount'] ?? 0),
            2
        );

        $net = round(
            (float) ($totals['net_amount'] ?? 0),
            2
        );

        if ($gross <= 0 && $net <= 0) {
            throw new RuntimeException(
                'Totales de recibo invalidos para XML.'
            );
        }

        if ($perceptions === []) {
            $perceptions = [[
                'sat_key' => '001',
                'code' => 'SUELDO_BASE',
                'name' => 'Sueldo base',
                'amount' => $gross,
                'taxable_amount' => $gross,
                'exempt_amount' => 0,
            ]];
        }

        /*
         * Ajuste mensual de subsidio:
         * Deducción 107 + OtroPago 007.
         *
         * Ambos importes se compensan en el CFDI.
         * El ISR 002 de la línea ya contiene el ISR
         * adicional recuperado.
         */
        $adjustment107 = round(
            (float) (
                $reconciliation[
                    'cfdi_deduction_107'
                ] ?? 0
            ),
            2
        );

        $otherPayment007 = round(
            (float) (
                $reconciliation[
                    'cfdi_other_payment_007'
                ] ?? 0
            ),
            2
        );

        if ($adjustment107 > 0) {
            $deductions[] = [
                'sat_key' => '107',
                'code' => 'AJUSTE_SUBSIDIO_CAUSADO',
                'name' => 'Ajuste al Subsidio Causado',
                'amount' => $adjustment107,
            ];
        }

        $otherPayments = [];

        /*
         * Subsidio causado del periodo.
         * SAT: TipoOtroPago 002, Importe 0,
         * SubsidioAlEmpleo/SubsidioCausado.
         */
        /*
         * BEXIA_V5836_XML_SUBSIDY_CAUSED_NOT_APPLIED
         *
         * SAT requiere SubsidioCausado = importe calculado
         * conforme al decreto, no necesariamente el importe
         * utilizado para disminuir ISR.
         */
        $subsidyApplied = round(
            (float) (
                $fiscalResult[
                    'subsidy_applied'
                ] ?? 0
            ),
            2
        );

        $subsidyCausedRaw =
            $fiscalSnapshot[
                'subsidy_caused'
            ] ?? null;

        /*
         * Un resultado fiscal antiguo que tenga subsidio aplicado
         * pero no haya congelado subsidio causado no puede
         * convertirse silenciosamente en CFDI.
         */
        if (
            $subsidyCausedRaw === null
            && $subsidyApplied > 0
        ) {
            throw new RuntimeException(
                'El resultado fiscal tiene subsidio aplicado '
                . 'pero no contiene SubsidioCausado en su snapshot. '
                . 'Debe recalcularse con la versión fiscal actual.'
            );
        }

        $subsidyCaused = round(
            max(
                0,
                (float) (
                    $subsidyCausedRaw
                    ?? 0
                )
            ),
            2
        );

        if ($subsidyCaused > 0) {
            $otherPayments[] = [
                'sat_key' => '002',
                'code' => 'SUBSIDIO_EMPLEO',
                'name' => 'Subsidio para el empleo',
                'amount' => 0.0,
                'subsidy_caused' => $subsidyCaused,
            ];
        }

        if ($otherPayment007 > 0) {
            $otherPayments[] = [
                'sat_key' => '007',
                'code' => 'ISR_AJUSTADO_SUBSIDIO',
                'name' => 'ISR ajustado por subsidio',
                'amount' => $otherPayment007,
            ];
        }

        $perceptionTotal = round(
            array_sum(
                array_map(
                    fn (array $concept): float =>
                        max(
                            0,
                            (float) (
                                $concept['amount']
                                ?? 0
                            )
                        ),
                    $perceptions
                )
            ),
            2
        );

        if ($perceptionTotal <= 0) {
            $perceptionTotal = $gross;
        }

        $totalGravado = round(
            array_sum(
                array_map(
                    fn (array $concept): float =>
                        max(
                            0,
                            (float) (
                                $concept[
                                    'taxable_amount'
                                ]
                                ?? $concept['amount']
                                ?? 0
                            )
                        ),
                    $perceptions
                )
            ),
            2
        );

        $totalExento = round(
            array_sum(
                array_map(
                    fn (array $concept): float =>
                        max(
                            0,
                            (float) (
                                $concept[
                                    'exempt_amount'
                                ]
                                ?? 0
                            )
                        ),
                    $perceptions
                )
            ),
            2
        );

        $totalImpuestosRetenidos = 0.0;
        $totalOtrasDeducciones = 0.0;

        foreach ($deductions as $concept) {
            $amount = round(
                max(
                    0,
                    (float) (
                        $concept['amount']
                        ?? 0
                    )
                ),
                2
            );

            if ($amount <= 0) {
                continue;
            }

            if (
                (string) (
                    $concept['sat_key']
                    ?? ''
                ) === '002'
            ) {
                $totalImpuestosRetenidos +=
                    $amount;
            } else {
                $totalOtrasDeducciones +=
                    $amount;
            }
        }

        $totalImpuestosRetenidos = round(
            $totalImpuestosRetenidos,
            2
        );

        $totalOtrasDeducciones = round(
            $totalOtrasDeducciones,
            2
        );

        $totalDeducciones = round(
            $totalImpuestosRetenidos
            + $totalOtrasDeducciones,
            2
        );

        $totalOtrosPagos = round(
            array_sum(
                array_map(
                    fn (array $payment): float =>
                        max(
                            0,
                            (float) (
                                $payment['amount']
                                ?? 0
                            )
                        ),
                    $otherPayments
                )
            ),
            2
        );

        /*
         * En CFDI nómina:
         *
         * SubTotal =
         *   TotalPercepciones + TotalOtrosPagos
         *
         * Descuento =
         *   TotalDeducciones
         *
         * Total =
         *   SubTotal - Descuento
         */
        $cfdiSubtotal = round(
            $perceptionTotal
            + $totalOtrosPagos,
            2
        );

        $cfdiTotal = round(
            $cfdiSubtotal
            - $totalDeducciones,
            2
        );

        /*
         * Guardia fundamental: la representación CFDI
         * debe conservar exactamente el neto del recibo.
         */
        if (
            abs(
                $cfdiTotal
                - $net
            ) > 0.01
        ) {
            throw new RuntimeException(
                'El total CFDI calculado '
                . number_format(
                    $cfdiTotal,
                    2,
                    '.',
                    ''
                )
                . ' no coincide con el neto de nómina '
                . number_format(
                    $net,
                    2,
                    '.',
                    ''
                )
                . '.'
            );
        }

        /*
         * BEXIA_V5836_STRICT_PERIODICITY_XML
         *
         * PeriodicidadPago debe provenir exclusivamente
         * del código SAT congelado en el recibo.
         *
         * No inferirla desde period_type.
         */
        $periodicity = trim(
            (string) (
                $totals[
                    'payroll_periodicity_sat_code'
                ] ?? ''
            )
        );

        if ($periodicity === '') {
            throw new RuntimeException(
                'El recibo no contiene '
                . 'PeriodicidadPago SAT congelada.'
            );
        }

        /*
         * BEXIA_V5836_STRICT_TIPO_NOMINA
         *
         * TipoNomina no puede deducirse por ausencia del
         * snapshot. Debe existir run_type explícito.
         */
        $runType = trim(
            (string) (
                $totals['run_type']
                ?? ''
            )
        );

        if (
            ! in_array(
                $runType,
                [
                    'ordinary',
                    'extraordinary',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'El recibo no contiene un tipo de nómina '
                . 'válido (ordinary/extraordinary).'
            );
        }

        $tipoNomina =
            $runType === 'extraordinary'
                ? 'E'
                : 'O';

        /*
         * BEXIA_PHASE3D_VALID_POSTAL_CODES
         *
         * Nunca generar un CFDI con CP fiscal ficticio.
         */
        $issuerPostalCode = $this->digits(
            (string) (
                $issuer['fiscal_postal_code']
                ?? ''
            )
        );

        if (
            ! preg_match(
                '/^[0-9]{5}$/',
                $issuerPostalCode
            )
        ) {
            throw new RuntimeException(
                'El emisor no tiene un código postal fiscal '
                . 'válido de 5 dígitos.'
            );
        }

        $receiverPostalCode = $this->digits(
            (string) (
                $employee['fiscal_postal_code']
                ?? ''
            )
        );

        if (
            ! preg_match(
                '/^[0-9]{5}$/',
                $receiverPostalCode
            )
        ) {
            throw new RuntimeException(
                'El trabajador no tiene un código postal fiscal '
                . 'válido de 5 dígitos.'
            );
        }

        $doc = new DOMDocument(
            '1.0',
            'UTF-8'
        );

        $doc->formatOutput = true;

        $comprobante = $doc->createElementNS(
            'http://www.sat.gob.mx/cfd/4',
            'cfdi:Comprobante'
        );

        $comprobante->setAttribute(
            'xmlns:nomina12',
            'http://www.sat.gob.mx/nomina12'
        );

        /*
         * BEXIA_PHASE3D_REV_E_SCHEMA
         *
         * CFDI 4.0 + Complemento Nómina 1.2.
         */
        $comprobante->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );

        $comprobante->setAttributeNS(
            'http://www.w3.org/2001/XMLSchema-instance',
            'xsi:schemaLocation',
            'http://www.sat.gob.mx/cfd/4 '
            . 'http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd '
            . 'http://www.sat.gob.mx/nomina12 '
            . 'http://www.sat.gob.mx/sitio_internet/cfd/nomina/nomina12.xsd'
        );

        $comprobante->setAttribute(
            'Version',
            '4.0'
        );

        $comprobante->setAttribute(
            'Serie',
            (string) (
                $receipt->series
                ?? 'NOM'
            )
        );

        $comprobante->setAttribute(
            'Folio',
            (string) (
                $receipt->folio
                ?? ('REC-' . $receipt->id)
            )
        );

        $comprobante->setAttribute(
            'Fecha',
            now()->format('Y-m-d\TH:i:s')
        );

        $comprobante->setAttribute(
            'SubTotal',
            $this->money(
                $cfdiSubtotal
            )
        );

        if ($totalDeducciones > 0) {
            $comprobante->setAttribute(
                'Descuento',
                $this->money(
                    $totalDeducciones
                )
            );
        }

        $comprobante->setAttribute(
            'Moneda',
            'MXN'
        );

        $comprobante->setAttribute(
            'Total',
            $this->money(
                $cfdiTotal
            )
        );

        $comprobante->setAttribute(
            'TipoDeComprobante',
            'N'
        );

        $comprobante->setAttribute(
            'Exportacion',
            '01'
        );

        $comprobante->setAttribute(
            'MetodoPago',
            'PUE'
        );

        $comprobante->setAttribute(
            'LugarExpedicion',
            $issuerPostalCode
        );

        $doc->appendChild(
            $comprobante
        );

        $emisor = $doc->createElement(
            'cfdi:Emisor'
        );

        $emisor->setAttribute(
            'Rfc',
            strtoupper(
                (string) (
                    $issuer['tax_id']
                    ?? ''
                )
            )
        );

        $emisor->setAttribute(
            'Nombre',
            strtoupper(
                (string) (
                    (
                        $issuer[
                            'business_name'
                        ] ?? null
                    )
                    ?: (
                        $issuer['name']
                        ?? ''
                    )
                )
            )
        );

        $emisor->setAttribute(
            'RegimenFiscal',
            (string) (
                $issuer['tax_regime']
                ?? ''
            )
        );

        $comprobante->appendChild(
            $emisor
        );

        $receptor = $doc->createElement(
            'cfdi:Receptor'
        );

        /*
         * BEXIA_V5836_PAYROLL_NO_FISCAL_DEFAULTS
         *
         * CFDI Nómina sólo puede construirse con datos reales
         * congelados en el recibo. No usar valores fiscales
         * inventados como fallback.
         */
        $receiverTaxRegime = trim(
            (string) (
                $employee['sat_tax_regime_code']
                ?? ''
            )
        );

        if ($receiverTaxRegime === '') {
            throw new RuntimeException(
                'El trabajador no tiene régimen fiscal SAT '
                . 'en el snapshot del recibo.'
            );
        }

        $paymentDate = trim(
            (string) (
                $totals['payment_date']
                ?? ''
            )
        );

        $periodStart = trim(
            (string) (
                $totals['period_start']
                ?? ''
            )
        );

        $periodEnd = trim(
            (string) (
                $totals['period_end']
                ?? ''
            )
        );

        foreach (
            [
                'FechaPago' => $paymentDate,
                'FechaInicialPago' => $periodStart,
                'FechaFinalPago' => $periodEnd,
            ] as $label => $value
        ) {
            if (
                $value === ''
                || ! preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $value
                )
            ) {
                throw new RuntimeException(
                    "El recibo no contiene {$label} válida."
                );
            }
        }

        try {
            $paymentDateObj =
                \Carbon\CarbonImmutable::parse(
                    $paymentDate
                );

            $periodStartObj =
                \Carbon\CarbonImmutable::parse(
                    $periodStart
                );

            $periodEndObj =
                \Carbon\CarbonImmutable::parse(
                    $periodEnd
                );
        } catch (Throwable) {
            throw new RuntimeException(
                'Las fechas fiscales del recibo no son válidas.'
            );
        }

        if ($periodEndObj->lessThan($periodStartObj)) {
            throw new RuntimeException(
                'FechaFinalPago no puede ser anterior '
                . 'a FechaInicialPago.'
            );
        }

        $payableDays = (float) (
            $totals['payable_days']
            ?? $totals['period_days']
            ?? 0
        );

        if ($payableDays <= 0) {
            throw new RuntimeException(
                'El recibo no contiene NumDiasPagados válido.'
            );
        }

        $employmentStart = trim(
            (string) (
                $contract['start_date']
                ?? ''
            )
        );

        if (
            $employmentStart === ''
            || ! preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $employmentStart
            )
        ) {
            throw new RuntimeException(
                'El contrato no contiene FechaInicioRelLaboral válida.'
            );
        }

        $satContractType = trim(
            (string) (
                $contract['sat_contract_type_code']
                ?? ''
            )
        );

        if ($satContractType === '') {
            throw new RuntimeException(
                'El contrato no contiene TipoContrato SAT.'
            );
        }

        /*
         * BEXIA_V5836_XML_FROZEN_EMPLOYER_REGISTRATION
         *
         * SAT exige RegistroPatronal para TipoContrato 01-08.
         */
        $requiresEmployerRegistration = in_array(
            $satContractType,
            [
                '01',
                '02',
                '03',
                '04',
                '05',
                '06',
                '07',
                '08',
            ],
            true
        );

        $employerRegistrationNumber = trim(
            (string) (
                $contract[
                    'employer_registration_number'
                ] ?? ''
            )
        );

        if (
            $requiresEmployerRegistration
            && $employerRegistrationNumber === ''
        ) {
            throw new RuntimeException(
                'El contrato requiere RegistroPatronal SAT '
                . 'pero el snapshot del recibo no contiene '
                . 'el número de registro patronal.'
            );
        }

        $satWorkdayType = trim(
            (string) (
                $contract['sat_workday_type_code']
                ?? ''
            )
        );

        if ($satWorkdayType === '') {
            throw new RuntimeException(
                'El contrato no contiene TipoJornada SAT.'
            );
        }

        $satRegimeType = trim(
            (string) (
                $contract['sat_regime_type_code']
                ?? ''
            )
        );

        if ($satRegimeType === '') {
            throw new RuntimeException(
                'El contrato no contiene TipoRegimen SAT.'
            );
        }

        $departmentName = trim(
            (string) (
                $contract['department']
                ?? ''
            )
        );

        $positionName = trim(
            (string) (
                $contract['position']
                ?? ''
            )
        );

        $riskPositionCode = trim(
            (string) (
                $contract['sat_risk_position_code']
                ?? ''
            )
        );

        $dailySalary = (float) (
            $contract['daily_salary']
            ?? 0
        );

        if ($dailySalary <= 0) {
            throw new RuntimeException(
                'El contrato no contiene salario diario válido.'
            );
        }

        /*
         * BEXIA_V5836_XML_EXPLICIT_SBC
         *
         * SalarioBaseCotApor debe provenir de un SBC
         * contractual explícito. No usar daily_salary
         * ni integrated_daily_salary como fallback.
         */
        $socialSecurityBaseSalary = (float) (
            $contract[
                'social_security_base_salary'
            ] ?? 0
        );

        if ($socialSecurityBaseSalary <= 0) {
            throw new RuntimeException(
                'El contrato no contiene '
                . 'Salario Base de Cotización (SBC) válido.'
            );
        }

        $integratedDailySalary = (float) (
            $contract['integrated_daily_salary']
            ?? 0
        );

        if ($integratedDailySalary <= 0) {
            throw new RuntimeException(
                'El contrato no contiene SalarioDiarioIntegrado válido.'
            );
        }

        $receptor->setAttribute(
            'Rfc',
            strtoupper(
                (string) (
                    $employee['rfc']
                    ?? ''
                )
            )
        );

        $receptor->setAttribute(
            'Nombre',
            strtoupper(
                (string) (
                    (
                        $employee[
                            'fiscal_name'
                        ] ?? null
                    )
                    ?: (
                        $employee['name']
                        ?? ''
                    )
                )
            )
        );

        $receptor->setAttribute(
            'DomicilioFiscalReceptor',
            $receiverPostalCode
        );

        $receptor->setAttribute(
            'RegimenFiscalReceptor',
            $receiverTaxRegime
        );

        $receptor->setAttribute(
            'UsoCFDI',
            'CN01'
        );

        $comprobante->appendChild(
            $receptor
        );

        $conceptos = $doc->createElement(
            'cfdi:Conceptos'
        );

        $concepto = $doc->createElement(
            'cfdi:Concepto'
        );

        $concepto->setAttribute(
            'ClaveProdServ',
            '84111505'
        );

        $concepto->setAttribute(
            'Cantidad',
            '1'
        );

        $concepto->setAttribute(
            'ClaveUnidad',
            'ACT'
        );

        $concepto->setAttribute(
            'Descripcion',
            'Pago de nomina'
        );

        $concepto->setAttribute(
            'ValorUnitario',
            $this->money(
                $cfdiSubtotal
            )
        );

        $concepto->setAttribute(
            'Importe',
            $this->money(
                $cfdiSubtotal
            )
        );

        if ($totalDeducciones > 0) {
            $concepto->setAttribute(
                'Descuento',
                $this->money(
                    $totalDeducciones
                )
            );
        }

        $concepto->setAttribute(
            'ObjetoImp',
            '01'
        );

        $conceptos->appendChild(
            $concepto
        );

        $comprobante->appendChild(
            $conceptos
        );

        $complemento = $doc->createElement(
            'cfdi:Complemento'
        );

        $nomina = $doc->createElement(
            'nomina12:Nomina'
        );

        $nomina->setAttribute(
            'Version',
            '1.2'
        );

        $nomina->setAttribute(
            'TipoNomina',
            $tipoNomina
        );

        $nomina->setAttribute(
            'FechaPago',
            $paymentDate
        );

        $nomina->setAttribute(
            'FechaInicialPago',
            $periodStart
        );

        $nomina->setAttribute(
            'FechaFinalPago',
            $periodEnd
        );

        $nomina->setAttribute(
            'NumDiasPagados',
            $this->decimal(
                $payableDays
            )
        );

        $nomina->setAttribute(
            'TotalPercepciones',
            $this->money(
                $perceptionTotal
            )
        );

        if ($totalDeducciones > 0) {
            $nomina->setAttribute(
                'TotalDeducciones',
                $this->money(
                    $totalDeducciones
                )
            );
        }

        if ($otherPayments !== []) {
            $nomina->setAttribute(
                'TotalOtrosPagos',
                $this->money(
                    $totalOtrosPagos
                )
            );
        }

        $emisorNomina = $doc->createElement(
            'nomina12:Emisor'
        );

        /*
         * El valor viene exclusivamente del snapshot.
         * No volver a consultar payroll_employer_registrations.
         */
        if ($employerRegistrationNumber !== '') {
            $emisorNomina->setAttribute(
                'RegistroPatronal',
                $employerRegistrationNumber
            );
        }

        $nomina->appendChild(
            $emisorNomina
        );

        $receptorNomina = $doc->createElement(
            'nomina12:Receptor'
        );

        $receptorNomina->setAttribute(
            'Curp',
            strtoupper(
                (string) (
                    $employee['curp']
                    ?? ''
                )
            )
        );

        $receptorNomina->setAttribute(
            'NumSeguridadSocial',
            (string) (
                $employee[
                    'social_security_number'
                ] ?? ''
            )
        );

        $receptorNomina->setAttribute(
            'FechaInicioRelLaboral',
            $employmentStart
        );

        /*
         * employmentStart y periodEnd ya fueron validados
         * al inicio de la construcción fiscal.
         */

        if (
            $employmentStart === ''
            || $periodEnd === ''
        ) {
            throw new RuntimeException(
                'No se puede calcular Antigüedad SAT: '
                . 'faltan FechaInicioRelLaboral '
                . 'o FechaFinalPago.'
            );
        }

        $seniorityStart =
            \Carbon\CarbonImmutable::parse(
                $employmentStart
            );

        $seniorityEndInclusive =
            \Carbon\CarbonImmutable::parse(
                $periodEnd
            )->addDay();

        if (
            $seniorityEndInclusive
                ->lessThanOrEqualTo(
                    $seniorityStart
                )
        ) {
            throw new RuntimeException(
                'Fechas inválidas para '
                . 'Antigüedad SAT.'
            );
        }

        $seniorityDiff =
            $seniorityStart->diff(
                $seniorityEndInclusive
            );

        $seniority =
            'P'
            .(
                $seniorityDiff->y > 0
                    ? $seniorityDiff->y.'Y'
                    : ''
            )
            .(
                $seniorityDiff->m > 0
                    ? $seniorityDiff->m.'M'
                    : ''
            )
            .$seniorityDiff->d
            .'D';

        $receptorNomina->setAttribute(
            'Antigüedad',
            $seniority
        );

        $receptorNomina->setAttribute(
            'TipoContrato',
            $satContractType
        );

        $receptorNomina->setAttribute(
            'Sindicalizado',
            (
                (bool) (
                    $contract[
                        'is_unionized'
                    ] ?? false
                )
            )
                ? 'Sí'
                : 'No'
        );

        $receptorNomina->setAttribute(
            'TipoJornada',
            $satWorkdayType
        );

        $receptorNomina->setAttribute(
            'TipoRegimen',
            $satRegimeType
        );

        $receptorNomina->setAttribute(
            'NumEmpleado',
            (string) (
                $employee[
                    'employee_id'
                ] ?? $receipt->employee_id
            )
        );

        if ($departmentName !== '') {
            $receptorNomina->setAttribute(
                'Departamento',
                $departmentName
            );
        }

        if ($positionName !== '') {
            $receptorNomina->setAttribute(
                'Puesto',
                $positionName
            );
        }

        /*
         * RiesgoPuesto no se inventa.
         * Si existe valor SAT real en contrato se emite;
         * de lo contrario se omite.
         */
        if ($riskPositionCode !== '') {
            $receptorNomina->setAttribute(
                'RiesgoPuesto',
                $riskPositionCode
            );
        }

        $receptorNomina->setAttribute(
            'PeriodicidadPago',
            $periodicity
        );

        $receptorNomina->setAttribute(
            'SalarioBaseCotApor',
            $this->money(
                $socialSecurityBaseSalary
            )
        );

        $receptorNomina->setAttribute(
            'SalarioDiarioIntegrado',
            $this->money(
                $integratedDailySalary
            )
        );

        $workStateSatCode = trim(
            (string) (
                $totals[
                    'work_state_sat_code'
                ] ?? ''
            )
        );

        if ($workStateSatCode === '') {
            throw new RuntimeException(
                'El recibo no contiene '
                . 'Entidad federativa donde labora SAT.'
            );
        }

        $receptorNomina->setAttribute(
            'ClaveEntFed',
            $workStateSatCode
        );

        $nomina->appendChild(
            $receptorNomina
        );

        $percepcionesNode = $doc->createElement(
            'nomina12:Percepciones'
        );

        $percepcionesNode->setAttribute(
            'TotalSueldos',
            $this->money(
                $perceptionTotal
            )
        );

        $percepcionesNode->setAttribute(
            'TotalGravado',
            $this->money(
                $totalGravado
            )
        );

        $percepcionesNode->setAttribute(
            'TotalExento',
            $this->money(
                $totalExento
            )
        );

        foreach ($perceptions as $concept) {
            $amount = (float) (
                $concept['amount']
                ?? 0
            );

            if ($amount <= 0) {
                continue;
            }

            /*
             * BEXIA_PHASE3D_REV_E_PERCEPTION_GUARDS
             *
             * Revisión E 2026:
             * - Gravado y exento no pueden ser ambos cero.
             * - TipoPercepcion 038 debe ser 100% gravado.
             */
            $satPerceptionKey = trim(
                (string) (
                    $concept['sat_key']
                    ?? ''
                )
            );

            if ($satPerceptionKey === '') {
                throw new RuntimeException(
                    'Percepción sin clave SAT: '
                    . (
                        $concept['code']
                        ?? 'N/D'
                    )
                );
            }

            $taxableAmount = round(
                max(
                    0,
                    (float) (
                        $concept[
                            'taxable_amount'
                        ]
                        ?? $amount
                    )
                ),
                2
            );

            $exemptAmount = round(
                max(
                    0,
                    (float) (
                        $concept[
                            'exempt_amount'
                        ]
                        ?? 0
                    )
                ),
                2
            );

            if (
                $taxableAmount <= 0
                && $exemptAmount <= 0
            ) {
                throw new RuntimeException(
                    'Percepción '
                    . (
                        $concept['code']
                        ?? $satPerceptionKey
                    )
                    . ': ImporteGravado e ImporteExento '
                    . 'no pueden ser ambos cero.'
                );
            }

            if (
                $satPerceptionKey === '038'
                && (
                    $exemptAmount > 0
                    || abs(
                        $taxableAmount
                        - round($amount, 2)
                    ) > 0.01
                )
            ) {
                throw new RuntimeException(
                    'La percepción SAT 038 debe registrarse '
                    . '100% como gravada en revisión E.'
                );
            }

            $percepcion = $doc->createElement(
                'nomina12:Percepcion'
            );

            $percepcion->setAttribute(
                'TipoPercepcion',
                $satPerceptionKey
            );

            $percepcion->setAttribute(
                'Clave',
                $this->cfdiConceptKey(
                    $concept,
                    'PER'
                )
            );

            $percepcion->setAttribute(
                'Concepto',
                (string) (
                    $concept['name']
                    ?? 'Percepcion'
                )
            );

            $percepcion->setAttribute(
                'ImporteGravado',
                $this->money(
                    $taxableAmount
                )
            );

            $percepcion->setAttribute(
                'ImporteExento',
                $this->money(
                    $exemptAmount
                )
            );

            $percepcionesNode->appendChild(
                $percepcion
            );
        }

        $nomina->appendChild(
            $percepcionesNode
        );

        if ($totalDeducciones > 0) {
            $deduccionesNode = $doc->createElement(
                'nomina12:Deducciones'
            );

            if ($totalImpuestosRetenidos > 0) {
                $deduccionesNode->setAttribute(
                    'TotalImpuestosRetenidos',
                    $this->money(
                        $totalImpuestosRetenidos
                    )
                );
            }

            if ($totalOtrasDeducciones > 0) {
                $deduccionesNode->setAttribute(
                    'TotalOtrasDeducciones',
                    $this->money(
                        $totalOtrasDeducciones
                    )
                );
            }

            foreach ($deductions as $concept) {
                $amount = (float) (
                    $concept['amount']
                    ?? 0
                );

                if ($amount <= 0) {
                    continue;
                }

                $satKey = trim(
                    (string) (
                        $concept['sat_key']
                        ?? ''
                    )
                );

                if ($satKey === '') {
                    throw new RuntimeException(
                        'Deducción sin clave SAT: '
                        . (
                            $concept['code']
                            ?? 'N/D'
                        )
                    );
                }

                $deduccion = $doc->createElement(
                    'nomina12:Deduccion'
                );

                $deduccion->setAttribute(
                    'TipoDeduccion',
                    $satKey
                );

                $deduccion->setAttribute(
                    'Clave',
                    $this->cfdiConceptKey(
                        $concept,
                        'DED'
                    )
                );

                $deduccion->setAttribute(
                    'Concepto',
                    (string) (
                        $concept['name']
                        ?? 'Deduccion'
                    )
                );

                $deduccion->setAttribute(
                    'Importe',
                    $this->money(
                        $amount
                    )
                );

                $deduccionesNode->appendChild(
                    $deduccion
                );
            }

            $nomina->appendChild(
                $deduccionesNode
            );
        }

        if ($otherPayments !== []) {
            $otrosPagosNode = $doc->createElement(
                'nomina12:OtrosPagos'
            );

            foreach ($otherPayments as $payment) {
                $otroPago = $doc->createElement(
                    'nomina12:OtroPago'
                );

                $otroPago->setAttribute(
                    'TipoOtroPago',
                    (string) $payment[
                        'sat_key'
                    ]
                );

                $otroPago->setAttribute(
                    'Clave',
                    (string) $payment[
                        'code'
                    ]
                );

                $otroPago->setAttribute(
                    'Concepto',
                    (string) $payment[
                        'name'
                    ]
                );

                $otroPago->setAttribute(
                    'Importe',
                    $this->money(
                        $payment[
                            'amount'
                        ] ?? 0
                    )
                );

                if (
                    (string) $payment[
                        'sat_key'
                    ] === '002'
                ) {
                    $subsidio = $doc->createElement(
                        'nomina12:SubsidioAlEmpleo'
                    );

                    $subsidio->setAttribute(
                        'SubsidioCausado',
                        $this->money(
                            $payment[
                                'subsidy_caused'
                            ] ?? 0
                        )
                    );

                    $otroPago->appendChild(
                        $subsidio
                    );
                }

                $otrosPagosNode->appendChild(
                    $otroPago
                );
            }

            $nomina->appendChild(
                $otrosPagosNode
            );
        }

        /*
         * BEXIA_V5836_XML_NOMINA_INCAPACIDADES
         *
         * En Nomina 1.2 Incapacidades va después de
         * OtrosPagos dentro de la secuencia del complemento.
         */
        if ($incapacityRows !== []) {
            $incapacidadesNode =
                $doc->createElement(
                    'nomina12:Incapacidades'
                );

            foreach ($incapacityRows as $row) {
                $incapacidadNode =
                    $doc->createElement(
                        'nomina12:Incapacidad'
                    );

                $incapacidadNode->setAttribute(
                    'DiasIncapacidad',
                    (string) (
                        (int) $row['days']
                    )
                );

                $incapacidadNode->setAttribute(
                    'TipoIncapacidad',
                    (string) $row[
                        'sat_type_code'
                    ]
                );

                if (
                    $row[
                        'monetary_amount'
                    ] !== null
                ) {
                    $incapacidadNode->setAttribute(
                        'ImporteMonetario',
                        $this->money(
                            $row[
                                'monetary_amount'
                            ]
                        )
                    );
                }

                $incapacidadesNode
                    ->appendChild(
                        $incapacidadNode
                    );
            }

            $nomina->appendChild(
                $incapacidadesNode
            );
        }

        $complemento->appendChild(
            $nomina
        );

        $comprobante->appendChild(
            $complemento
        );

        return $doc->saveXML()
            ?: throw new RuntimeException(
                'No se pudo construir XML.'
            );
    }

    /**
     * BEXIA_V5836_XML_INCAPACITY_SNAPSHOT
     *
     * Construye exclusivamente desde metadata.line_details
     * congelado en PayrollRunVersionLine.
     *
     * Nunca consulta employee_incidents ni payroll_run_lines.
     */
    protected function incapacitySnapshotRows(
        array $metadata
    ): array {
        $lineDetails =
            $metadata['line_details']
            ?? [];

        if (! is_array($lineDetails)) {
            throw new RuntimeException(
                'El recibo no contiene line_details válido.'
            );
        }

        $economic =
            $lineDetails[
                'incapacity_economic_allocations'
            ] ?? [];

        if ($economic === [] || $economic === null) {
            return [];
        }

        if (! is_array($economic)) {
            throw new RuntimeException(
                'El snapshot de incapacidades '
                . 'no tiene estructura válida.'
            );
        }

        $items =
            $economic['items']
            ?? [];

        if (! is_array($items)) {
            throw new RuntimeException(
                'El snapshot de incapacidades '
                . 'no contiene items válidos.'
            );
        }

        $rows = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new RuntimeException(
                    'Existe una incapacidad inválida '
                    . 'en el snapshot.'
                );
            }

            $incidentId =
                $item['incident_id']
                ?? 'N/D';

            $days = (int) (
                $item['days_in_period']
                ?? 0
            );

            if ($days < 1) {
                throw new RuntimeException(
                    'La incapacidad '
                    .$incidentId
                    .' no tiene DiasIncapacidad válido '
                    .'para el periodo.'
                );
            }

            $type = trim(
                (string) (
                    $item['sat_type_code']
                    ?? ''
                )
            );

            if (
                ! in_array(
                    $type,
                    ['01', '02', '03', '04'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'La incapacidad '
                    .$incidentId
                    .' no tiene TipoIncapacidad SAT válido.'
                );
            }

            $effect = trim(
                (string) (
                    $item['payroll_effect']
                    ?? ''
                )
            );

            if (
                ! in_array(
                    $effect,
                    [
                        'none',
                        'perception',
                        'deduction',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'La incapacidad '
                    .$incidentId
                    .' no tiene tratamiento económico válido.'
                );
            }

            $amountRaw =
                $item[
                    'period_monetary_amount'
                ] ?? null;

            $amount =
                $amountRaw !== null
                    ? round(
                        (float) $amountRaw,
                        2
                    )
                    : null;

            if (
                in_array(
                    $effect,
                    [
                        'perception',
                        'deduction',
                    ],
                    true
                )
                && (
                    $amount === null
                    || $amount <= 0
                )
            ) {
                throw new RuntimeException(
                    'La incapacidad '
                    .$incidentId
                    .' requiere ImporteMonetario '
                    .'del periodo mayor a cero.'
                );
            }

            if (
                $effect === 'none'
                && $amount !== null
                && abs($amount) > 0.000001
            ) {
                throw new RuntimeException(
                    'La incapacidad '
                    .$incidentId
                    .' sin efecto económico no debe '
                    .'contener ImporteMonetario.'
                );
            }

            $rows[] = [
                'incident_id' =>
                    $incidentId,

                'days' =>
                    $days,

                'sat_type_code' =>
                    $type,

                'payroll_effect' =>
                    $effect,

                'monetary_amount' =>
                    in_array(
                        $effect,
                        [
                            'perception',
                            'deduction',
                        ],
                        true
                    )
                        ? $amount
                        : null,
            ];
        }

        return $rows;
    }


    protected function assertSchema(): void
    {
        foreach (['payroll_cfdi_receipts', 'payroll_cfdi_audits', 'payroll_runs'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("No existe la tabla requerida: {$table}");
            }
        }
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

    protected function mergeMetadata(object $receipt, array $extra): array
    {
        $metadata = $this->decode($receipt->metadata ?? null);

        return array_merge($metadata, $extra);
    }

    /**
     * BEXIA_V5836_XML_CFDI_CONCEPT_KEY
     *
     * SAT Nómina 1.2:
     * Clave debe tener de 3 a 15 caracteres
     * y no puede contener "|".
     *
     * No truncar automáticamente:
     * dos códigos internos distintos podrían colisionar.
     */
    protected function cfdiConceptKey(
        array $concept,
        string $fallback
    ): string {
        $metadata =
            is_array(
                $concept['metadata']
                ?? null
            )
                ? $concept['metadata']
                : [];

        $key = trim(
            (string) (
                $metadata['cfdi_key']
                ?? (
                    $concept['code']
                    ?? $fallback
                )
            )
        );

        if (
            strlen($key) < 3
            || strlen($key) > 15
            || str_contains($key, '|')
        ) {
            throw new RuntimeException(
                'Concepto '
                .(
                    $concept['code']
                    ?? $fallback
                )
                .': Clave CFDI inválida "'
                .$key
                .'". Debe tener entre 3 y 15 caracteres '
                .'y no contener "|".'
            );
        }

        return $key;
    }


    protected function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    protected function decimal(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.') ?: '0';
    }

    protected function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    protected function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
