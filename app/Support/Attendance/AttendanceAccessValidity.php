<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class AttendanceAccessValidity
{
    public const TIMEZONE = 'America/Mexico_City';

    public static function validate(
        string $validFrom,
        string $validUntil,
        string $reason,
        DateTimeImmutable $now
    ): array {
        $from = self::parse($validFrom);
        $until = self::parse($validUntil);

        $reason = trim($reason);

        if ($until <= $from) {
            throw new InvalidArgumentException(
                'INVALID_VALIDITY_INTERVAL'
            );
        }

        if ($from < $now) {
            throw new InvalidArgumentException(
                'RETROACTIVE_AUTHORIZATION_FORBIDDEN'
            );
        }

        if (
            mb_strlen($reason) < 5
            || mb_strlen($reason) > 1000
        ) {
            throw new InvalidArgumentException(
                'INVALID_REASON_LENGTH'
            );
        }

        return [
            'valid_from' => $from,
            'valid_until' => $until,
            'reason' => $reason,
        ];
    }

    public static function parse(
        string $value
    ): DateTimeImmutable {
        $format = 'Y-m-d H:i:s';

        $date = DateTimeImmutable::createFromFormat(
            '!' . $format,
            $value,
            new DateTimeZone(self::TIMEZONE)
        );

        if (
            !$date
            || $date->format($format) !== $value
        ) {
            throw new InvalidArgumentException(
                'INVALID_DATETIME'
            );
        }

        return $date;
    }

    public static function overlaps(
        DateTimeImmutable $fromA,
        DateTimeImmutable $untilA,
        DateTimeImmutable $fromB,
        DateTimeImmutable $untilB
    ): bool {
        return $fromA <= $untilB
            && $fromB <= $untilA;
    }

    public static function databaseValue(
        DateTimeImmutable $date
    ): string {
        return $date->format('Y-m-d H:i:sP');
    }
}
