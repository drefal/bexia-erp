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


    /**
     * @param array<int, array{0: float|int|string, 1: float|int|string}> $polygon
     */
    public static function pointInPolygon(float $latitude, float $longitude, array $polygon): bool
    {
        $points = array_values(array_filter($polygon, fn ($point) => is_array($point) && count($point) >= 2));

        if (count($points) < 3) {
            return false;
        }

        $inside = false;
        $j = count($points) - 1;

        for ($i = 0, $count = count($points); $i < $count; $i++) {
            $latI = (float) $points[$i][0];
            $lngI = (float) $points[$i][1];
            $latJ = (float) $points[$j][0];
            $lngJ = (float) $points[$j][1];

            $intersects = (($lngI > $longitude) !== ($lngJ > $longitude))
                && ($latitude < ($latJ - $latI) * ($longitude - $lngI) / (($lngJ - $lngI) ?: 0.0000000001) + $latI);

            if ($intersects) {
                $inside = ! $inside;
            }

            $j = $i;
        }

        return $inside;
    }

    public static function polygonStatus(float $latitude, float $longitude, array $polygon, ?int $accuracyMeters = null, ?int $accuracyRequiredMeters = null): string
    {
        if ($accuracyRequiredMeters !== null && $accuracyMeters !== null && $accuracyMeters > $accuracyRequiredMeters) {
            return 'poor_accuracy';
        }

        return static::pointInPolygon($latitude, $longitude, $polygon) ? 'inside' : 'outside';
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
