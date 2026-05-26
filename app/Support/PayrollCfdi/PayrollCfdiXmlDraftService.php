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
        $perceptions = [];
        $deductions = [];

        foreach ($lineConcepts as $concept) {
            $type = (string) ($concept['type'] ?? '');
            if ($type === 'deduction') {
                $deductions[] = $concept;
            } else {
                $perceptions[] = $concept;
            }
        }

        $gross = (float) ($totals['gross_amount'] ?? 0);
        $deductionTotal = (float) ($totals['deductions_amount'] ?? 0);
        $net = (float) ($totals['net_amount'] ?? 0);

        if ($gross <= 0 && $net <= 0) {
            throw new RuntimeException('Totales de recibo invalidos para XML.');
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $comprobante = $doc->createElementNS('http://www.sat.gob.mx/cfd/4', 'cfdi:Comprobante');
        $comprobante->setAttribute('xmlns:nomina12', 'http://www.sat.gob.mx/nomina12');
        $comprobante->setAttribute('Version', '4.0');
        $comprobante->setAttribute('Serie', (string) ($receipt->series ?? 'NOM'));
        $comprobante->setAttribute('Folio', (string) ($receipt->folio ?? ('REC-' . $receipt->id)));
        $comprobante->setAttribute('Fecha', now()->format('Y-m-d\TH:i:s'));
        $comprobante->setAttribute('SubTotal', $this->money($gross));
        $comprobante->setAttribute('Descuento', $this->money($deductionTotal));
        $comprobante->setAttribute('Moneda', 'MXN');
        $comprobante->setAttribute('Total', $this->money($net));
        $comprobante->setAttribute('TipoDeComprobante', 'N');
        $comprobante->setAttribute('Exportacion', '01');
        $comprobante->setAttribute('MetodoPago', 'PUE');
        $comprobante->setAttribute('LugarExpedicion', $this->digits((string) ($issuer['fiscal_postal_code'] ?? '')) ?: '00000');
        $doc->appendChild($comprobante);

        $emisor = $doc->createElement('cfdi:Emisor');
        $emisor->setAttribute('Rfc', strtoupper((string) ($issuer['tax_id'] ?? '')));
        $emisor->setAttribute('Nombre', strtoupper((string) (($issuer['business_name'] ?? null) ?: ($issuer['name'] ?? ''))));
        $emisor->setAttribute('RegimenFiscal', (string) ($issuer['tax_regime'] ?? ''));
        $comprobante->appendChild($emisor);

        $receptor = $doc->createElement('cfdi:Receptor');
        $receptor->setAttribute('Rfc', strtoupper((string) ($employee['rfc'] ?? '')));
        $receptor->setAttribute('Nombre', strtoupper((string) (($employee['fiscal_name'] ?? null) ?: ($employee['name'] ?? ''))));
        $receptor->setAttribute('DomicilioFiscalReceptor', $this->digits((string) ($employee['fiscal_postal_code'] ?? '')) ?: '00000');
        $receptor->setAttribute('RegimenFiscalReceptor', (string) ($employee['sat_tax_regime_code'] ?? '605'));
        $receptor->setAttribute('UsoCFDI', 'CN01');
        $comprobante->appendChild($receptor);

        $conceptos = $doc->createElement('cfdi:Conceptos');
        $concepto = $doc->createElement('cfdi:Concepto');
        $concepto->setAttribute('ClaveProdServ', '84111505');
        $concepto->setAttribute('Cantidad', '1');
        $concepto->setAttribute('ClaveUnidad', 'ACT');
        $concepto->setAttribute('Descripcion', 'Pago de nomina');
        $concepto->setAttribute('ValorUnitario', $this->money($gross));
        $concepto->setAttribute('Importe', $this->money($gross));
        $concepto->setAttribute('Descuento', $this->money($deductionTotal));
        $concepto->setAttribute('ObjetoImp', '01');
        $conceptos->appendChild($concepto);
        $comprobante->appendChild($conceptos);

        $complemento = $doc->createElement('cfdi:Complemento');
        $nomina = $doc->createElement('nomina12:Nomina');
        $nomina->setAttribute('Version', '1.2');
        $nomina->setAttribute('TipoNomina', 'O');
        $nomina->setAttribute('FechaPago', (string) ($totals['payment_date'] ?? now()->toDateString()));
        $nomina->setAttribute('FechaInicialPago', (string) ($totals['period_start'] ?? now()->toDateString()));
        $nomina->setAttribute('FechaFinalPago', (string) ($totals['period_end'] ?? now()->toDateString()));
        $nomina->setAttribute('NumDiasPagados', $this->decimal($totals['payable_days'] ?? $totals['period_days'] ?? 1));
        $nomina->setAttribute('TotalPercepciones', $this->money($gross));

        if ($deductionTotal > 0) {
            $nomina->setAttribute('TotalDeducciones', $this->money($deductionTotal));
        }

        $emisorNomina = $doc->createElement('nomina12:Emisor');
        if (! empty($contract['payroll_employer_registration_id'])) {
            $emisorNomina->setAttribute('RegistroPatronal', (string) $contract['payroll_employer_registration_id']);
        }
        $nomina->appendChild($emisorNomina);

        $receptorNomina = $doc->createElement('nomina12:Receptor');
        $receptorNomina->setAttribute('Curp', strtoupper((string) ($employee['curp'] ?? '')));
        $receptorNomina->setAttribute('NumSeguridadSocial', (string) ($employee['social_security_number'] ?? ''));
        $receptorNomina->setAttribute('FechaInicioRelLaboral', (string) (($contract['start_date'] ?? null) ?: now()->startOfYear()->toDateString()));
        $receptorNomina->setAttribute('Antigüedad', 'P1W');
        $receptorNomina->setAttribute('TipoContrato', (string) ($contract['sat_contract_type_code'] ?? '01'));
        $receptorNomina->setAttribute('Sindicalizado', ((bool) ($contract['is_unionized'] ?? false)) ? 'Sí' : 'No');
        $receptorNomina->setAttribute('TipoJornada', (string) ($contract['sat_workday_type_code'] ?? '01'));
        $receptorNomina->setAttribute('TipoRegimen', (string) ($contract['sat_regime_type_code'] ?? '02'));
        $receptorNomina->setAttribute('NumEmpleado', (string) ($employee['employee_id'] ?? $receipt->employee_id));
        $receptorNomina->setAttribute('Departamento', (string) (($contract['department'] ?? null) ?: 'GENERAL'));
        $receptorNomina->setAttribute('Puesto', (string) (($contract['position'] ?? null) ?: 'GENERAL'));
        $receptorNomina->setAttribute('RiesgoPuesto', (string) ($contract['sat_risk_position_code'] ?? '1'));
        $receptorNomina->setAttribute('PeriodicidadPago', '04');
        $receptorNomina->setAttribute('SalarioBaseCotApor', $this->money($contract['daily_salary'] ?? 0));
        $receptorNomina->setAttribute('SalarioDiarioIntegrado', $this->money($contract['integrated_daily_salary'] ?? 0));
        $receptorNomina->setAttribute('ClaveEntFed', 'CMX');
        $nomina->appendChild($receptorNomina);

        $percepcionesNode = $doc->createElement('nomina12:Percepciones');
        $percepcionesNode->setAttribute('TotalSueldos', $this->money($gross));
        $percepcionesNode->setAttribute('TotalGravado', $this->money($gross));
        $percepcionesNode->setAttribute('TotalExento', '0.00');

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

        foreach ($perceptions as $concept) {
            $amount = (float) ($concept['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $percepcion = $doc->createElement('nomina12:Percepcion');
            $percepcion->setAttribute('TipoPercepcion', (string) ($concept['sat_key'] ?? '001'));
            $percepcion->setAttribute('Clave', (string) ($concept['code'] ?? 'PER'));
            $percepcion->setAttribute('Concepto', (string) ($concept['name'] ?? 'Percepcion'));
            $percepcion->setAttribute('ImporteGravado', $this->money($concept['taxable_amount'] ?? $amount));
            $percepcion->setAttribute('ImporteExento', $this->money($concept['exempt_amount'] ?? 0));
            $percepcionesNode->appendChild($percepcion);
        }

        $nomina->appendChild($percepcionesNode);

        if ($deductionTotal > 0 || $deductions !== []) {
            $deduccionesNode = $doc->createElement('nomina12:Deducciones');
            $deduccionesNode->setAttribute('TotalOtrasDeducciones', $this->money($deductionTotal));

            foreach ($deductions as $concept) {
                $amount = (float) ($concept['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                $deduccion = $doc->createElement('nomina12:Deduccion');
                $deduccion->setAttribute('TipoDeduccion', (string) ($concept['sat_key'] ?? '004'));
                $deduccion->setAttribute('Clave', (string) ($concept['code'] ?? 'DED'));
                $deduccion->setAttribute('Concepto', (string) ($concept['name'] ?? 'Deduccion'));
                $deduccion->setAttribute('Importe', $this->money($amount));
                $deduccionesNode->appendChild($deduccion);
            }

            $nomina->appendChild($deduccionesNode);
        }

        $complemento->appendChild($nomina);
        $comprobante->appendChild($complemento);

        return $doc->saveXML() ?: throw new RuntimeException('No se pudo construir XML.');
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
