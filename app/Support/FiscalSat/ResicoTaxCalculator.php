<?php

namespace App\Support\FiscalSat;

use Illuminate\Support\Facades\DB;

class ResicoTaxCalculator
{
    public function defaultCompanyId(): ?int
    {
        return DB::table('sat_company_credentials')
            ->where('is_enabled', true)
            ->orderByDesc('id')
            ->value('company_id');
    }

    public function availablePeriods(int $companyId): array
    {
        return DB::table('sat_cfdi_documents')
            ->where('company_id', $companyId)
            ->whereNotNull('xml_path')
            ->whereNotNull('issued_at')
            ->selectRaw("to_char(issued_at, 'YYYY-MM') as period")
            ->groupBy('period')
            ->orderByDesc('period')
            ->pluck('period', 'period')
            ->all();
    }

    public function calculate(int $companyId, string $period): array
    {
        [$from, $to] = $this->periodRange($period);

        $documents = DB::table('sat_cfdi_documents')
            ->where('company_id', $companyId)
            ->whereBetween('issued_at', [$from, $to])
            ->whereNotNull('xml_path')
            ->orderBy('direction')
            ->orderBy('issued_at')
            ->get();

        $documentIds = $documents->pluck('id')->all();

        $taxes = $documentIds === []
            ? collect()
            : DB::table('sat_cfdi_taxes')
                ->whereIn('sat_cfdi_document_id', $documentIds)
                ->get()
                ->groupBy('sat_cfdi_document_id');

        $summary = [
            'company_id' => $companyId,
            'period' => $period,
            'period_from' => $from,
            'period_to' => $to,
            'docs_total_xml' => 0,
            'docs_vigentes' => 0,
            'docs_cancelados_excluidos' => 0,
            'issued_income_count' => 0,
            'received_income_count' => 0,
            'issued_income_subtotal' => 0.0,
            'issued_egress_subtotal' => 0.0,
            'received_income_subtotal' => 0.0,
            'received_egress_subtotal' => 0.0,
            'issued_iva_transferred' => 0.0,
            'issued_iva_transferred_egress' => 0.0,
            'received_iva_transferred' => 0.0,
            'received_iva_transferred_egress' => 0.0,
            'issued_iva_withheld' => 0.0,
            'received_iva_withheld' => 0.0,
            'issued_isr_withheld' => 0.0,
            'received_isr_withheld' => 0.0,
            'issued_pue_subtotal' => 0.0,
            'issued_ppd_subtotal' => 0.0,
            'received_pue_subtotal' => 0.0,
            'received_ppd_subtotal' => 0.0,
        ];

        $details = [];

        foreach ($documents as $doc) {
            $summary['docs_total_xml']++;

            if ($this->isCancelled($doc->status ?? null)) {
                $summary['docs_cancelados_excluidos']++;
                continue;
            }

            $summary['docs_vigentes']++;

            $direction = $this->normalize($doc->direction ?? '');
            $type = $this->normalize($doc->cfdi_type ?? '');
            $paymentMethod = $this->normalize($doc->payment_method ?? '');
            $subtotal = $this->amount($doc->subtotal ?? 0);
            $total = $this->amount($doc->total ?? 0);

            $ivaTransferred = 0.0;
            $ivaWithheld = 0.0;
            $isrWithheld = 0.0;

            foreach ($taxes->get($doc->id, collect()) as $tax) {
                $taxAmount = $this->amount($tax->amount ?? 0);

                if ($this->isIvaTax($tax->tax ?? null) && $this->isTransferred($tax->tax_direction ?? null)) {
                    $ivaTransferred += $taxAmount;
                }

                if ($this->isIvaTax($tax->tax ?? null) && $this->isWithheld($tax->tax_direction ?? null)) {
                    $ivaWithheld += $taxAmount;
                }

                if ($this->isIsrTax($tax->tax ?? null) && $this->isWithheld($tax->tax_direction ?? null)) {
                    $isrWithheld += $taxAmount;
                }
            }

            if ($direction === 'ISSUED') {
                if ($this->isIncomeDoc($type)) {
                    $summary['issued_income_count']++;
                    $summary['issued_income_subtotal'] += $subtotal;
                    $summary['issued_iva_transferred'] += $ivaTransferred;

                    if ($paymentMethod === 'PPD') {
                        $summary['issued_ppd_subtotal'] += $subtotal;
                    } else {
                        $summary['issued_pue_subtotal'] += $subtotal;
                    }
                }

                if ($this->isEgressDoc($type)) {
                    $summary['issued_egress_subtotal'] += $subtotal;
                    $summary['issued_iva_transferred_egress'] += $ivaTransferred;
                }

                $summary['issued_iva_withheld'] += $ivaWithheld;
                $summary['issued_isr_withheld'] += $isrWithheld;
            }

            if ($direction === 'RECEIVED') {
                if ($this->isIncomeDoc($type)) {
                    $summary['received_income_count']++;
                    $summary['received_income_subtotal'] += $subtotal;
                    $summary['received_iva_transferred'] += $ivaTransferred;

                    if ($paymentMethod === 'PPD') {
                        $summary['received_ppd_subtotal'] += $subtotal;
                    } else {
                        $summary['received_pue_subtotal'] += $subtotal;
                    }
                }

                if ($this->isEgressDoc($type)) {
                    $summary['received_egress_subtotal'] += $subtotal;
                    $summary['received_iva_transferred_egress'] += $ivaTransferred;
                }

                $summary['received_iva_withheld'] += $ivaWithheld;
                $summary['received_isr_withheld'] += $isrWithheld;
            }

            $details[] = [
                'id' => $doc->id,
                'uuid' => $doc->uuid,
                'direction' => $doc->direction,
                'cfdi_type' => $doc->cfdi_type,
                'status' => $doc->status,
                'payment_method' => $doc->payment_method,
                'issued_at' => $doc->issued_at,
                'issuer_rfc' => $doc->issuer_rfc,
                'issuer_name' => $doc->issuer_name,
                'receiver_rfc' => $doc->receiver_rfc,
                'receiver_name' => $doc->receiver_name,
                'subtotal' => round($subtotal, 2),
                'iva_transferred' => round($ivaTransferred, 2),
                'iva_withheld' => round($ivaWithheld, 2),
                'isr_withheld' => round($isrWithheld, 2),
                'total' => round($total, 2),
            ];
        }

        foreach ($summary as $key => $value) {
            if (is_float($value)) {
                $summary[$key] = round($value, 2);
            }
        }

        $baseResico = max(0.0, $summary['issued_income_subtotal'] - $summary['issued_egress_subtotal']);
        $rate = $this->resicoRate($baseResico);
        $isrCausado = $rate === null ? null : round($baseResico * $rate, 2);
        $isrRetenido = round($summary['issued_isr_withheld'], 2);

        $ivaTrasladado = round($summary['issued_iva_transferred'] - $summary['issued_iva_transferred_egress'], 2);
        $ivaAcreditable = round($summary['received_iva_transferred'] - $summary['received_iva_transferred_egress'], 2);
        $ivaRetenidoClientes = round($summary['issued_iva_withheld'], 2);
        $ivaDiferencia = round($ivaTrasladado - $ivaAcreditable, 2);

        return [
            'summary' => $summary,
            'calculation' => [
                'resico' => [
                    'base_isr_resico_estimado' => round($baseResico, 2),
                    'tasa_resico_mensual' => $rate,
                    'isr_causado_estimado' => $isrCausado,
                    'isr_retenido_detectado_emitidos' => $isrRetenido,
                    'isr_estimado_a_pagar' => $isrCausado === null ? null : round(max(0.0, $isrCausado - $isrRetenido), 2),
                    'isr_saldo_favor_por_retenciones' => $isrCausado === null ? null : round(max(0.0, $isrRetenido - $isrCausado), 2),
                ],
                'iva' => [
                    'iva_trasladado_emitido_neto' => $ivaTrasladado,
                    'iva_acreditable_recibido_neto' => $ivaAcreditable,
                    'iva_diferencia_antes_retenciones' => $ivaDiferencia,
                    'iva_retenido_por_clientes_detectado_emitidos' => $ivaRetenidoClientes,
                    'iva_estimado_a_pagar' => round($ivaDiferencia - $ivaRetenidoClientes, 2),
                    'iva_retenido_a_proveedores_detectado_recibidos' => round($summary['received_iva_withheld'], 2),
                ],
            ],
            'details' => $details,
            'warnings' => $this->warnings($summary),
        ];
    }

    private function periodRange(string $period): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = now()->format('Y-m');
        }

        $from = $period . '-01 00:00:00';
        $to = date('Y-m-t 23:59:59', strtotime($from));

        return [$from, $to];
    }

    private function amount(mixed $value): float
    {
        return $value === null || $value === '' ? 0.0 : round((float) $value, 6);
    }

    private function normalize(mixed $value): string
    {
        return strtr(strtoupper(trim((string) $value)), [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ]);
    }

    private function isCancelled(mixed $status): bool
    {
        $status = $this->normalize($status);

        return str_contains($status, 'CANCEL') || $status === 'C';
    }

    private function isIncomeDoc(mixed $type): bool
    {
        $type = $this->normalize($type);

        return $type === 'I' || str_contains($type, 'INGRES');
    }

    private function isEgressDoc(mixed $type): bool
    {
        $type = $this->normalize($type);

        return $type === 'E' || str_contains($type, 'EGRES');
    }

    private function isIvaTax(mixed $tax): bool
    {
        $tax = $this->normalize($tax);

        return $tax === '002' || $tax === 'IVA' || str_contains($tax, 'VALOR AGREGADO');
    }

    private function isIsrTax(mixed $tax): bool
    {
        $tax = $this->normalize($tax);

        return $tax === '001' || $tax === 'ISR' || str_contains($tax, 'RENTA');
    }

    private function isTransferred(mixed $direction): bool
    {
        $direction = $this->normalize($direction);

        return str_contains($direction, 'TRAS') || str_contains($direction, 'TRANSF') || $direction === 'T';
    }

    private function isWithheld(mixed $direction): bool
    {
        $direction = $this->normalize($direction);

        return str_contains($direction, 'RET') || str_contains($direction, 'WITH') || $direction === 'W';
    }

    private function resicoRate(float $base): ?float
    {
        return match (true) {
            $base <= 0 => 0.0,
            $base <= 25000.00 => 0.0100,
            $base <= 50000.00 => 0.0110,
            $base <= 83333.33 => 0.0150,
            $base <= 208333.33 => 0.0200,
            $base <= 3500000.00 => 0.0250,
            default => null,
        };
    }

    private function warnings(array $summary): array
    {
        $warnings = [
            'Cálculo preliminar interno. No sustituye declaración SAT ni revisión del contador.',
            'RESICO se calcula con XML emitidos vigentes como aproximación de ingresos cobrados.',
            'IVA acreditable requiere validar pago efectivo, deducibilidad y criterio contable.',
        ];

        if (($summary['issued_ppd_subtotal'] ?? 0) > 0) {
            $warnings[] = 'Hay CFDI emitidos PPD; falta enlazar complementos de pago.';
        }

        if (($summary['received_ppd_subtotal'] ?? 0) > 0) {
            $warnings[] = 'Hay CFDI recibidos PPD; falta enlazar complementos de pago para acreditar IVA.';
        }

        return $warnings;
    }
}
