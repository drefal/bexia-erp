<?php

namespace App\Support\Attendance;

final class GeofenceDistance
{
    public static function meters(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): int
    {
        $earthRadiusMeters = 6371000;

        $fromLat = deg2rad($fromLatitude);
        $toLat = deg2rad($toLatitude);
        $deltaLat = deg2rad($toLatitude - $fromLatitude);
        $deltaLon = deg2rad($toLongitude - $fromLongitude);

        $a = sin($deltaLat / 2) ** 2
            + cos($fromLat) * cos($toLat) * (sin($deltaLon / 2) ** 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadiusMeters * $c);
    }

    public static function status(int $distanceMeters, int $radiusMeters, ?int $accuracyMeters = null, ?int $accuracyRequiredMeters = null): string
    {
        if ($accuracyRequiredMeters !== null && $accuracyMeters !== null && $accuracyMeters > $accuracyRequiredMeters) {
            return 'poor_accuracy';
        }

        return $distanceMeters <= $radiusMeters ? 'inside' : 'outside';
    }

    public static function label(?string $status): string
    {
        return match ($status) {
            'inside' => 'Dentro de geocerca',
            'outside' => 'Fuera de geocerca',
            'poor_accuracy' => 'Precisión GPS baja',
            'manual' => 'Manual',
            default => 'Sin validar',
        };
    }
}
