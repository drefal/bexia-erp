<?php

namespace App\Support\PayrollTax;

use App\Models\PayrollIsrTariff;
use Carbon\CarbonInterface;

class PayrollIsrCalculator
{
    public static function calculate(
        float $taxableIncome,
        string $periodType,
        CarbonInterface|string $paymentDate
    ): array {
        $taxableIncome = round(
            max(0, $taxableIncome),
            2
        );

        if ($taxableIncome <= 0) {
            return [
                'taxable_income' => 0.0,
                'isr_before_subsidy' => 0.0,
                'tariff_code' => null,
                'lower_limit' => null,
                'upper_limit' => null,
                'fixed_fee' => 0.0,
                'marginal_rate' => 0.0,
                'excess' => 0.0,
                'marginal_tax' => 0.0,
            ];
        }

        $date = $paymentDate instanceof CarbonInterface
            ? $paymentDate
            : \Carbon\CarbonImmutable::parse($paymentDate);

        $tariffs = PayrollIsrTariff::query()
            ->where('fiscal_year', (int) $date->year)
            ->where('period_type', $periodType)
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $date->toDateString())
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('valid_to')
                    ->orWhereDate(
                        'valid_to',
                        '>=',
                        $date->toDateString()
                    );
            })
            ->with('brackets')
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get();

        if ($tariffs->isEmpty()) {
            throw new \RuntimeException(
                "No existe tarifa ISR para {$periodType} "
                . "en {$date->year}."
            );
        }

        if ($tariffs->count() !== 1) {
            throw new \RuntimeException(
                'Existen múltiples tarifas ISR activas '
                . "para {$periodType} en "
                . $date->toDateString()
                . '. Corrige los periodos de vigencia.'
            );
        }

        $tariff = $tariffs->first();

        $bracket = $tariff->brackets
            ->first(function ($row) use ($taxableIncome): bool {
                $lower = (float) $row->lower_limit;

                $upper = $row->upper_limit !== null
                    ? (float) $row->upper_limit
                    : null;

                return $taxableIncome >= $lower
                    && (
                        $upper === null
                        || $taxableIncome <= $upper
                    );
            });

        if (! $bracket) {
            throw new \RuntimeException(
                'No se encontró rango ISR para ingreso gravable '
                . number_format($taxableIncome, 2)
            );
        }

        $lower = (float) $bracket->lower_limit;
        $upper = $bracket->upper_limit !== null
            ? (float) $bracket->upper_limit
            : null;

        $fixedFee = (float) $bracket->fixed_fee;
        $rate = (float) $bracket->marginal_rate;

        $excess = max(
            0,
            round($taxableIncome - $lower, 2)
        );

        $marginalTax = round(
            $excess * ($rate / 100),
            2
        );

        $isr = round(
            $fixedFee + $marginalTax,
            2
        );

        return [
            'taxable_income' => $taxableIncome,
            'isr_before_subsidy' => $isr,
            'tariff_code' => $tariff->code,
            'lower_limit' => $lower,
            'upper_limit' => $upper,
            'fixed_fee' => round($fixedFee, 2),
            'marginal_rate' => $rate,
            'excess' => $excess,
            'marginal_tax' => $marginalTax,
        ];
    }
}
