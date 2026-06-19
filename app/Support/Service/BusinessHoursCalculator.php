<?php

namespace App\Support\Service;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class BusinessHoursCalculator
{
    /**
     * Calcula horas hábiles entre dos fechas.
     *
     * Regla actual de Bexia Servicio:
     * - Lunes a viernes: 09:00 a 17:00 = 8 horas.
     * - Sábado: 09:00 a 14:00 = 5 horas.
     * - Domingo: 0 horas.
     */
    public static function between(
        CarbonInterface|string|null $start,
        CarbonInterface|string|null $end,
    ): float {
        if (! $start || ! $end) {
            return 0.0;
        }

        $startAt = $start instanceof CarbonInterface ? Carbon::instance($start) : Carbon::parse($start);
        $endAt = $end instanceof CarbonInterface ? Carbon::instance($end) : Carbon::parse($end);

        if ($endAt->lessThanOrEqualTo($startAt)) {
            return 0.0;
        }

        $totalMinutes = 0;
        $cursor = $startAt->copy()->startOfDay();
        $lastDay = $endAt->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($lastDay)) {
            $window = self::workWindowForDay($cursor);

            if ($window !== null) {
                [$dayStart, $dayEnd] = $window;

                $effectiveStart = $startAt->greaterThan($dayStart) ? $startAt->copy() : $dayStart;
                $effectiveEnd = $endAt->lessThan($dayEnd) ? $endAt->copy() : $dayEnd;

                if ($effectiveEnd->greaterThan($effectiveStart)) {
                    $totalMinutes += $effectiveStart->diffInMinutes($effectiveEnd);
                }
            }

            $cursor->addDay();
        }

        return round($totalMinutes / 60, 2);
    }

    protected static function workWindowForDay(CarbonInterface $day): ?array
    {
        if ($day->isSunday()) {
            return null;
        }

        if ($day->isSaturday()) {
            return [
                Carbon::instance($day)->copy()->setTime(9, 0, 0),
                Carbon::instance($day)->copy()->setTime(14, 0, 0),
            ];
        }

        return [
            Carbon::instance($day)->copy()->setTime(9, 0, 0),
            Carbon::instance($day)->copy()->setTime(17, 0, 0),
        ];
    }
}
