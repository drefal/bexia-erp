<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use InvalidArgumentException;

final class AttendanceAccessUserChannel
{
    public static function channel(
        int $companyId,
        int $userId
    ): string {

        if ($companyId <= 0 || $userId <= 0) {

            throw new InvalidArgumentException(
                'INVALID_PRIVATE_CHANNEL_SCOPE'
            );
        }

        return sprintf(
            'private-attendance.approvals.%d.%d',
            $companyId,
            $userId
        );
    }

    public static function signature(
        string $socketId,
        int $companyId,
        int $userId,
        string $secret
    ): string {

        if (
            strlen($socketId) > 64
            || !preg_match(
                '/^\d+\.\d+$/D',
                $socketId
            )
            || $secret === ''
        ) {
            throw new InvalidArgumentException(
                'INVALID_CHANNEL_SIGNATURE_REQUEST'
            );
        }

        return hash_hmac(
            'sha256',
            $socketId . ':' . self::channel(
                $companyId,
                $userId
            ),
            $secret
        );
    }
}
