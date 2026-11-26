<?php

declare(strict_types=1);

namespace App\Support\Reports;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class ReportClock
{
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    public static function sql(
        DateTimeInterface $value
    ): string {

        return CarbonImmutable::instance($value)
            ->utc()
            ->format('Y-m-d H:i:s.uP');
    }

    public static function display(
        ?DateTimeInterface $value
    ): ?string {

        if ($value === null) {
            return null;
        }

        return CarbonImmutable::instance($value)
            ->setTimezone(
                config(
                    'app.timezone',
                    'America/Mexico_City'
                )
            )
            ->format('d/m/Y H:i');
    }
}
