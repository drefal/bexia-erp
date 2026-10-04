<?php

namespace App\Support\ComputerRental;

use App\Models\ComputerRentalRate;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class ComputerRentalCalculator
{
    public function calculate(
        ComputerRentalRate $rate,
        CarbonInterface $startedAt,
        CarbonInterface $endedAt
    ): array {
        if ($endedAt->lt($startedAt)) {
            throw new InvalidArgumentException(
                'La fecha de fin no puede ser anterior al inicio.'
            );
        }

        $durationSeconds = max(
            0,
            $startedAt->diffInSeconds($endedAt)
        );

        $realMinutes = (int) ceil($durationSeconds / 60);

        if ($rate->billing_mode === 'prepaid') {
            $billableMinutes = max(
                1,
                (int) ($rate->prepaid_minutes ?? 0)
            );

            $amount = round(
                (float) ($rate->prepaid_price ?? 0),
                4
            );

            return [
                'duration_seconds' => $durationSeconds,
                'real_minutes' => $realMinutes,
                'billable_minutes' => $billableMinutes,
                'amount' => $amount,
            ];
        }

        $minimumMinutes = max(
            1,
            (int) ($rate->minimum_minutes ?? 1)
        );

        $increment = max(
            1,
            (int) ($rate->billing_increment_minutes ?? 1)
        );

        $baseMinutes = max(
            $minimumMinutes,
            $realMinutes
        );

        $billableMinutes = (int) (
            ceil($baseMinutes / $increment) * $increment
        );

        $hourlyRate = max(
            0,
            (float) ($rate->hourly_rate ?? 0)
        );

        $amount = round(
            ($billableMinutes / 60) * $hourlyRate,
            4
        );

        return [
            'duration_seconds' => $durationSeconds,
            'real_minutes' => $realMinutes,
            'billable_minutes' => $billableMinutes,
            'amount' => $amount,
        ];
    }
}
