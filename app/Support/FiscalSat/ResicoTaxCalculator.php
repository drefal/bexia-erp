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
            'issued_egress_count' => 0,
            'received_income_count' => 0,
            'received_egress_count' => 0,

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
            'issued_ppd_count' => 0,
            'received_ppd_count' => 0,

            'received_pue_g01_g03_banked_count' => 0,
            'received_pue_g01_g03_banked_subtotal' => 0.0,
            'received_pue_g01_g03_banked_iva' => 0.0,

            'received_excluded_from_accreditable_count' => 0,
            'received_excluded_from_accreditable_subtotal' => 0.0,
            'received_excluded_from_accreditable_iva' => 0.0,

            'received_payment_cfdi_count' => 0,
            'received_payment_cfdi_iva_detected' => 0.0,
        ];

        $details = [];

        foreach ($documents as $document) {
            $summary['docs_total_xml']++;

            if ($this->isCancelled($document->status ?? null)) {
                $summary['docs_cancelados_excluidos']++;
                continue;
            }

            $summary['docs_vigentes']++;

            $direction = $this->normalize($document->direction ?? '');
            $type = $this->normalize($document->cfdi_type ?? '');
            $paymentMethod = $this->normalize($document->payment_method ?? '');
            $paymentForm = $this->normalize($document->payment_form ?? '');
            $usageCfdi = $this->normalize($document->usage_cfdi ?? '');

            $subtotal = $this->amount($document->subtotal ?? 0);
            $discount = $this->amount($document->discount ?? 0);
            $subtotalLessDiscount = max(0.0, $subtotal - $discount);
            $total = $this->amount($document->total ?? 0);

            $ivaTransferred = 0.0;
            $ivaWithheld = 0.0;
            $isrWithheld = 0.0;

            foreach ($taxes->get($document->id, collect()) as $tax) {
                $taxAmount = $this->amount($tax->amount ?? 0);
                $taxName = $tax->tax ?? null;
                $taxDirection = $tax->tax_direction ?? null;

                if ($this->isIvaTax($taxName) && $this->isTransferred($taxDirection)) {
                    $ivaTransferred += $taxAmount;
                }

                if ($this->isIvaTax($taxName) && $this->isWithheld($taxDirection)) {
                    $ivaWithheld += $taxAmount;
                }

                if ($this->isIsrTax($taxName) && $this->isWithheld($taxDirection)) {
                    $isrWithheld += $taxAmount;
                }
            }

            $ivaAccreditableLike = false;
            $ivaExclusionReason = null;

            if ($direction === 'ISSUED') {
                if ($this->isIncomeDoc($type)) {
                    $summary['issued_income_count']++;
                    $summary['issued_income_subtotal'] += $subtotal;
                    $summary['issued_iva_transferred'] += $ivaTransferred;

                    if ($paymentMethod === 'PPD') {
                        $summary['issued_ppd_subtotal'] += $subtotal;
                        $summary['issued_ppd_count']++;
                    } else {
                        $summary['issued_pue_subtotal'] += $subtotal;
                    }
                }

                if ($this->isEgressDoc($type)) {
                    $summary['issued_egress_count']++;
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
                        $summary['received_ppd_count']++;
                    } else {
                        $summary['received_pue_subtotal'] += $subtotal;
                    }

                    $ivaAccreditableLike = $paymentMethod === 'PUE'
                        && $this->isIvaAllowedUsage($usageCfdi)
                        && $this->isBankedPaymentForm($paymentForm);

                    if ($ivaAccreditableLike) {
                        $summary['received_pue_g01_g03_banked_count']++;
                        $summary['received_pue_g01_g03_banked_subtotal'] += $subtotalLessDiscount;
                        $summary['received_pue_g01_g03_banked_iva'] += $ivaTransferred;
                    } else {
                        $summary['received_excluded_from_accreditable_count']++;
                        $summary['received_excluded_from_accreditable_subtotal'] += $subtotalLessDiscount;
                        $summary['received_excluded_from_accreditable_iva'] += $ivaTransferred;
                        $ivaExclusionReason = $this->ivaExclusionReason($paymentMethod, $paymentForm, $usageCfdi);
                    }
                }

                if ($this->isPaymentDoc($type)) {
                    $summary['received_payment_cfdi_count']++;
                    $summary['received_payment_cfdi_iva_detected'] += $ivaTransferred;
                    $ivaExclusionReason = 'CFDI tipo pago: falta leer complemento pago20';
                }

                if ($this->isEgressDoc($type)) {
                    $summary['received_egress_count']++;
                    $summary['received_egress_subtotal'] += $subtotal;
                    $summary['received_iva_transferred_egress'] += $ivaTransferred;
                }

                $summary['received_iva_withheld'] += $ivaWithheld;
                $summary['received_isr_withheld'] += $isrWithheld;
            }

            $details[] = [
                'id' => $document->id,
                'uuid' => $document->uuid,
                'direction' => $document->direction,
                'direction_label' => $this->directionLabel($document->direction ?? null),
                'cfdi_type' => $document->cfdi_type,
                'cfdi_type_label' => $this->cfdiTypeLabel($document->cfdi_type ?? null),
                'status' => $document->status,
                'payment_method' => $document->payment_method,
                'payment_method_label' => $this->paymentMethodLabel($document->payment_method ?? null),
                'payment_form' => $document->payment_form,
                'usage_cfdi' => $document->usage_cfdi,
                'issued_at' => $document->issued_at,
                'issuer_rfc' => $document->issuer_rfc,
                'issuer_name' => $document->issuer_name,
                'receiver_rfc' => $document->receiver_rfc,
                'receiver_name' => $document->receiver_name,
                'subtotal' => round($subtotal, 2),
                'discount' => round($discount, 2),
                'subtotal_less_discount' => round($subtotalLessDiscount, 2),
                'iva_transferred' => round($ivaTransferred, 2),
                'iva_withheld' => round($ivaWithheld, 2),
                'isr_withheld' => round($isrWithheld, 2),
                'total' => round($total, 2),
                'iva_acreditable_estimado' => $ivaAccreditableLike,
                'iva_exclusion_reason' => $ivaExclusionReason,
            ];
        }

        foreach ($summary as $key => $value) {
            if (is_float($value)) {
                $summary[$key] = round($value, 2);
            }
        }

        $baseIsrResico = max(0.0, $summary['issued_income_subtotal'] - $summary['issued_egress_subtotal']);
        $resicoRate = $this->resicoRate($baseIsrResico);
        $isrCausado = $resicoRate === null ? null : round($baseIsrResico * $resicoRate, 2);
        $isrRetenido = round($summary['issued_isr_withheld'], 2);

        $ivaTrasladadoNeto = round($summary['issued_iva_transferred'] - $summary['issued_iva_transferred_egress'], 2);
        $ivaTotalRecibidoXml = round($summary['received_iva_transferred'] - $summary['received_iva_transferred_egress'], 2);
        $ivaDeclaracionLike = round(
            $summary['received_pue_g01_g03_banked_iva'] + $summary['received_payment_cfdi_iva_detected'],
            2
        );
        $ivaNoAcreditablePreliminar = round(max(0.0, $ivaTotalRecibidoXml - $ivaDeclaracionLike), 2);
        $ivaRetenidoPorClientes = round($summary['issued_iva_withheld'], 2);
        $ivaAmplioPagar = round($ivaTrasladadoNeto - $ivaTotalRecibidoXml - $ivaRetenidoPorClientes, 2);
        $ivaDeclaracionLikePagar = round($ivaTrasladadoNeto - $ivaDeclaracionLike - $ivaRetenidoPorClientes, 2);

        return [
            'summary' => $summary,
            'calculation' => [
                'resico' => [
                    'base_isr_resico_estimado' => round($baseIsrResico, 2),
                    'tasa_resico_mensual' => $resicoRate,
                    'isr_causado_estimado' => $isrCausado,
                    'isr_retenido_detectado_emitidos' => $isrRetenido,
                    'isr_estimado_a_pagar' => $isrCausado === null ? null : round(max(0.0, $isrCausado - $isrRetenido), 2),
                    'isr_saldo_favor_por_retenciones' => $isrCausado === null ? null : round(max(0.0, $isrRetenido - $isrCausado), 2),
                ],
                'iva' => [
                    'iva_trasladado_emitido_neto' => $ivaTrasladadoNeto,
                    'iva_acreditable_recibido_neto' => $ivaTotalRecibidoXml,
                    'iva_acreditable_estimado_declaracion' => $ivaDeclaracionLike,
                    'iva_acreditable_pue_g01_g03_bancarizada' => round($summary['received_pue_g01_g03_banked_iva'], 2),
                    'iva_complementos_pago_detectado' => round($summary['received_payment_cfdi_iva_detected'], 2),
                    'iva_complementos_pago_pendiente' => $summary['received_payment_cfdi_count'],
                    'iva_no_acreditable_preliminar' => $ivaNoAcreditablePreliminar,
                    'iva_diferencia_antes_retenciones' => round($ivaTrasladadoNeto - $ivaTotalRecibidoXml, 2),
                    'iva_retenido_por_clientes_detectado_emitidos' => $ivaRetenidoPorClientes,
                    'iva_estimado_a_pagar' => $ivaAmplioPagar,
                    'iva_estimado_a_pagar_declaracion_like' => $ivaDeclaracionLikePagar,
                    'iva_retenido_a_proveedores_detectado_recibidos' => round($summary['received_iva_withheld'], 2),
                ],
            ],
            'details' => $details,
            'ppd_details' => array_values(array_filter($details, fn (array $row): bool => ($row['payment_method'] ?? null) === 'PPD')),
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

        return str_contains($status, 'CANCEL') || $status === 'C' || $status === '0';
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

    private function isPaymentDoc(mixed $type): bool
    {
        $type = $this->normalize($type);

        return $type === 'P' || str_contains($type, 'PAGO');
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

    private function isBankedPaymentForm(mixed $paymentForm): bool
    {
        return in_array($this->normalize($paymentForm), ['02', '03', '04', '05', '06', '28', '29'], true);
    }

    private function isIvaAllowedUsage(mixed $usageCfdi): bool
    {
        return in_array($this->normalize($usageCfdi), ['G01', 'G03'], true);
    }

    private function ivaExclusionReason(mixed $paymentMethod, mixed $paymentForm, mixed $usageCfdi): string
    {
        $reasons = [];

        if ($this->normalize($paymentMethod) !== 'PUE') {
            $reasons[] = 'No es PUE';
        }

        if (! $this->isIvaAllowedUsage($usageCfdi)) {
            $reasons[] = 'Uso CFDI no G01/G03';
        }

        if (! $this->isBankedPaymentForm($paymentForm)) {
            $reasons[] = 'Forma de pago no bancarizada';
        }

        return implode('; ', $reasons) ?: 'Incluible para IVA acreditable';
    }

    private function directionLabel(mixed $direction): string
    {
        return match ($this->normalize($direction)) {
            'ISSUED' => 'Emitido',
            'RECEIVED' => 'Recibido',
            default => (string) ($direction ?: 'Sin flujo'),
        };
    }

    private function cfdiTypeLabel(mixed $type): string
    {
        return match ($this->normalize($type)) {
            'I' => 'Ingreso',
            'E' => 'Egreso',
            'P' => 'Pago',
            'N' => 'Nómina',
            'T' => 'Traslado',
            default => (string) ($type ?: 'Sin tipo'),
        };
    }

    private function paymentMethodLabel(mixed $method): string
    {
        return match ($this->normalize($method)) {
            'PUE' => 'Pago en una sola exhibición',
            'PPD' => 'Pago en parcialidades o diferido',
            default => (string) ($method ?: 'Sin método'),
        };
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
            'IVA acreditable tipo declaración usa PUE + uso CFDI G01/G03 + forma de pago bancarizada.',
        ];

        if (($summary['received_excluded_from_accreditable_iva'] ?? 0) > 0) {
            $warnings[] = 'Hay IVA recibido que se excluye preliminarmente por método, forma de pago o uso CFDI.';
        }

        if (($summary['received_ppd_subtotal'] ?? 0) > 0) {
            $warnings[] = 'Hay CFDI recibidos PPD; falta enlazar complementos de pago para acreditar IVA.';
        }

        if (($summary['received_payment_cfdi_count'] ?? 0) > 0) {
            $warnings[] = 'Hay CFDI tipo pago recibidos, pero falta leer el complemento pago20 para sumar el IVA pagado relacionado.';
        }

        return $warnings;
    }
}
