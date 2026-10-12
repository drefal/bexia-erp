<?php

namespace App\Support\PayrollTax;

use App\Models\PayrollRun;
use Carbon\CarbonImmutable;

class PayrollEmploymentSubsidyReconciliationService
{
    public static function calculate(
        PayrollRun $run,
        array $preview
    ): array {
        $paymentDate = CarbonImmutable::parse(
            $run->payment_date
        );

        $periodDays = (int) (
            $preview['period_days'] ?? 0
        );

        if ($periodDays <= 0) {
            throw new \RuntimeException(
                'No se pudieron determinar los días '
                . 'del periodo fiscal.'
            );
        }

        /*
         * El ajuste se realiza en el último pago del mes.
         *
         * Para semanal/quincenal:
         * si el siguiente periodo de pago ya cae en otro mes,
         * éste es el último pago del mes.
         *
         * Para mensual siempre es cierre mensual.
         */
        $isLastPaymentOfMonth =
            (string) $run->period_type === 'mensual'
            || $paymentDate
                ->addDays($periodDays)
                ->month !== $paymentDate->month;

        $monthTaxable = round(
            (float) (
                $preview[
                    'month_taxable_including_current'
                ] ?? 0
            ),
            2
        );

        /*
         * BEXIA_V5836_SUBSIDY_ADJUSTMENT_DIVERGENCE_GUARD
         *
         * Mantener por separado causado y aplicado.
         * Bajo el decreto vigente pueden ser diferentes.
         */
        $priorSubsidyCaused = round(
            (float) (
                $preview[
                    'prior_month_subsidy_caused'
                ] ?? 0
            ),
            2
        );

        $priorSubsidy = round(
            (float) (
                $preview[
                    'prior_month_subsidy_applied'
                ] ?? 0
            ),
            2
        );

        $currentSubsidyCaused = round(
            (float) (
                $preview[
                    'subsidy_caused'
                ] ?? 0
            ),
            2
        );

        $currentSubsidy = round(
            (float) (
                $preview[
                    'subsidy_applied'
                ] ?? 0
            ),
            2
        );

        $eligible = (bool) (
            $preview['subsidy_eligible']
            ?? false
        );

        $totalSubsidyCaused = round(
            $priorSubsidyCaused
            + $currentSubsidyCaused,
            2
        );

        $totalSubsidyApplied = round(
            $priorSubsidy
            + $currentSubsidy,
            2
        );

        $subsidyNotApplied = round(
            max(
                0,
                $totalSubsidyCaused
                - $totalSubsidyApplied
            ),
            2
        );

        /*
         * GUARD FISCAL:
         *
         * Cuando al cierre mensual deja de existir derecho
         * al subsidio y causado != aplicado, no existe todavía
         * en Bexia una representación CFDI certificada para la
         * parte causada que nunca disminuyó ISR ni fue pagada
         * en efectivo.
         *
         * No inventamos 071/008.
         * No convertimos automáticamente todo causado en 107.
         */
        if (
            $isLastPaymentOfMonth
            && ! $eligible
            && abs(
                $totalSubsidyCaused
                - $totalSubsidyApplied
            ) > 0.01
        ) {
            throw new \RuntimeException(
                'Ajuste mensual de subsidio bloqueado: '
                . 'el subsidio causado acumulado ('
                . number_format(
                    $totalSubsidyCaused,
                    2,
                    '.',
                    ''
                )
                . ') es distinto del subsidio aplicado contra ISR ('
                . number_format(
                    $totalSubsidyApplied,
                    2,
                    '.',
                    ''
                )
                . '). La diferencia no fue entregada en efectivo '
                . 'y requiere una regla CFDI expresamente validada '
                . 'antes de generar 107/007/071/008.'
            );
        }

        /*
         * Si al cierre mensual ya no existe derecho al subsidio,
         * debe revertirse el subsidio causado previamente.
         *
         * En nuestro motor el subsidio nunca se entrega en efectivo:
         * solamente disminuye ISR.
         *
         * Por lo tanto, el monto a recuperar como ISR es igual
         * al subsidio previamente aplicado contra ISR.
         */
        /*
         * A partir de aquí sólo puede llegar un ajuste donde
         * causado y aplicado coinciden.
         */
        $subsidyCausedAdjustment =
            $isLastPaymentOfMonth
            && ! $eligible
                ? $totalSubsidyApplied
                : 0.0;

        $isrAdjustment =
            $subsidyCausedAdjustment;

        /*
         * Representación CFDI futura:
         *
         * Deducción 107 = Ajuste al Subsidio Causado
         * OtroPago 007  = ISR ajustado por subsidio
         * Deducción 002 = ISR adicional recuperado
         *
         * 107 y 007 se compensan para efectos del neto.
         * Sólo ISR_ADJUSTMENT afecta el neto interno de Bexia.
         *
         * 071 / 008 NO aplican porque actualmente Bexia
         * no entrega subsidio en efectivo al trabajador.
         */
        return [
            'is_last_payment_of_month' =>
                $isLastPaymentOfMonth,

            'month_taxable_income' =>
                $monthTaxable,

            'final_month_subsidy_eligible' =>
                $eligible,

            'prior_subsidy_caused' =>
                $priorSubsidyCaused,

            'prior_subsidy_applied' =>
                $priorSubsidy,

            'current_subsidy_caused' =>
                $currentSubsidyCaused,

            'current_subsidy_applied' =>
                $currentSubsidy,

            'total_subsidy_caused' =>
                $totalSubsidyCaused,

            'total_subsidy_applied' =>
                $totalSubsidyApplied,

            'subsidy_not_applied' =>
                $subsidyNotApplied,

            'subsidy_caused_adjustment' =>
                $subsidyCausedAdjustment,

            'isr_adjustment' =>
                $isrAdjustment,

            'cfdi_deduction_107' =>
                $subsidyCausedAdjustment,

            'cfdi_other_payment_007' =>
                $isrAdjustment,

            'cfdi_cash_subsidy_adjustment_071' =>
                0.0,

            'cfdi_other_payment_008' =>
                0.0,
        ];
    }
}
