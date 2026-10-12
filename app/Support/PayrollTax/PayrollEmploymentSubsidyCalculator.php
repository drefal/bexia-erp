<?php

namespace App\Support\PayrollTax;

use App\Models\PayrollEmploymentSubsidyRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PayrollEmploymentSubsidyCalculator
{
    public static function calculate(
        float $isrBeforeSubsidy,
        float $monthlyTaxableIncome,
        int $paymentPeriodDays,
        CarbonInterface|string $paymentDate,
        float $priorMonthSubsidyApplied = 0.0
    ): array {
        $date = $paymentDate instanceof CarbonInterface
            ? CarbonImmutable::instance($paymentDate)
            : CarbonImmutable::parse($paymentDate);

        $isrBeforeSubsidy = round(
            max(0, $isrBeforeSubsidy),
            2
        );

        $monthlyTaxableIncome = round(
            max(0, $monthlyTaxableIncome),
            2
        );

        if ($paymentPeriodDays <= 0) {
            throw new \InvalidArgumentException(
                'Los días del periodo deben ser mayores a cero.'
            );
        }

        $rules = PayrollEmploymentSubsidyRule::query()
            ->where('fiscal_year', (int) $date->year)
            ->where('is_active', true)
            ->whereDate(
                'valid_from',
                '<=',
                $date->toDateString()
            )
            ->whereDate(
                'valid_to',
                '>=',
                $date->toDateString()
            )
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get();

        if ($rules->isEmpty()) {
            throw new \RuntimeException(
                'No existe regla de subsidio para '
                . $date->toDateString()
            );
        }

        if ($rules->count() !== 1) {
            throw new \RuntimeException(
                'Existen múltiples reglas de subsidio '
                . 'activas para '
                . $date->toDateString()
                . '. Corrige los periodos de vigencia.'
            );
        }

        $rule = $rules->first();

        $monthlyLimit =
            (float) $rule->monthly_income_limit;

        $monthlyMaximumRaw =
            (float) $rule->uma_monthly
            * (
                (float) $rule->subsidy_percentage
                / 100
            );

        $monthlyMaximum = round(
            $monthlyMaximumRaw,
            2
        );

        $eligible =
            $monthlyTaxableIncome <= $monthlyLimit;

        $periodSubsidyRaw = $eligible
            ? (
                $monthlyMaximumRaw
                / (float) $rule->month_days_factor
                * $paymentPeriodDays
            )
            : 0.0;

        /*
         * El decreto establece que nunca puede excederse
         * el máximo mensual.
         */
        $periodMaximum = round(
            min(
                $periodSubsidyRaw,
                $monthlyMaximumRaw
            ),
            2
        );

        /*
         * Control acumulado del mes:
         * las corridas previas pueden haber utilizado
         * parte del máximo mensual.
         */
        $priorMonthSubsidyApplied = round(
            max(0, $priorMonthSubsidyApplied),
            2
        );

        $monthlyRemaining = round(
            max(
                0,
                $monthlyMaximum
                - $priorMonthSubsidyApplied
            ),
            2
        );

        $availableForCurrentPayment = round(
            min(
                $periodMaximum,
                $monthlyRemaining
            ),
            2
        );

        /*
         * El subsidio disminuye ISR hasta cero.
         * No se entrega diferencia en efectivo.
         */
        $appliedSubsidy = round(
            min(
                $isrBeforeSubsidy,
                $availableForCurrentPayment
            ),
            2
        );

        $isrAfterSubsidy = round(
            max(
                0,
                $isrBeforeSubsidy - $appliedSubsidy
            ),
            2
        );

        return [
            'rule_code' => $rule->code,

            'monthly_taxable_income' =>
                $monthlyTaxableIncome,

            'monthly_income_limit' =>
                round($monthlyLimit, 2),

            'eligible' => $eligible,

            'uma_monthly' =>
                round((float) $rule->uma_monthly, 2),

            'subsidy_percentage' =>
                (float) $rule->subsidy_percentage,

            'monthly_subsidy_maximum' =>
                $monthlyMaximum,

            'payment_period_days' =>
                $paymentPeriodDays,

            'period_subsidy_maximum' =>
                $periodMaximum,

            'prior_month_subsidy_applied' =>
                $priorMonthSubsidyApplied,

            'monthly_subsidy_remaining' =>
                $monthlyRemaining,

            'available_for_current_payment' =>
                $availableForCurrentPayment,

            /*
             * BEXIA_V5836_SUBSIDY_CAUSED_SEPARATE
             *
             * Subsidio causado:
             * importe determinado para el periodo conforme
             * a la regla/decreto.
             *
             * Subsidio aplicado:
             * parte del causado que efectivamente disminuye ISR.
             *
             * No son necesariamente iguales.
             */
            'subsidy_caused' =>
                $periodMaximum,

            'isr_before_subsidy' =>
                $isrBeforeSubsidy,

            'subsidy_applied' =>
                $appliedSubsidy,

            'isr_after_subsidy' =>
                $isrAfterSubsidy,
        ];
    }
}
