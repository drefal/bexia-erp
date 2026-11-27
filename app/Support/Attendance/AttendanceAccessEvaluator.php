<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use DateTimeImmutable;

final class AttendanceAccessEvaluator
{
    public const AUTHORIZED = 'authorized';
    public const HOLD = 'hold';
    public const DENIED = 'denied';
    public const REVIEW = 'review';
    public const NOT_EVALUATED = 'not_evaluated';

    public static function evaluate(array $context): array
    {
        if (!($context['employee_active'] ?? false)) {
            return self::result(
                self::DENIED,
                'employee_inactive'
            );
        }

        if ($context['hard_restriction'] ?? false) {
            return self::result(
                self::DENIED,
                'explicit_restriction'
            );
        }

        if (!($context['enabled'] ?? false)) {
            return self::result(
                self::NOT_EVALUATED,
                'access_control_disabled'
            );
        }

        if ($context['temporary_restriction'] ?? false) {
            return self::result(
                self::HOLD,
                'temporary_restriction',
                true
            );
        }

        $at = $context['entry_at'] ?? null;

        if (!$at instanceof DateTimeImmutable) {
            return self::result(
                self::REVIEW,
                'invalid_entry_time',
                true
            );
        }

        $authorization =
            $context['prior_authorization'] ?? null;

        if (is_array($authorization)) {

            $from = $authorization['valid_from'] ?? null;
            $until = $authorization['valid_until'] ?? null;

            if (
                ($authorization['approved'] ?? false)
                && !($authorization['revoked'] ?? false)
                && $from instanceof DateTimeImmutable
                && $until instanceof DateTimeImmutable
                && $from <= $until
                && $at >= $from
                && $at <= $until
            ) {
                return self::result(
                    self::AUTHORIZED,
                    'prior_authorization'
                );
            }
        }

        if (
            ($context['approved_absence'] ?? false)
            && ($context['hold_on_approved_absence'] ?? true)
        ) {
            return self::result(
                self::HOLD,
                'approved_absence',
                true
            );
        }

        $schedule = $context['schedule'] ?? null;

        if (!is_array($schedule)) {
            return self::result(
                self::REVIEW,
                'missing_schedule',
                true
            );
        }

        if (
            !array_key_exists('is_working_day', $schedule)
            || !is_bool($schedule['is_working_day'])
        ) {
            return self::result(
                self::REVIEW,
                'invalid_working_day_configuration',
                true
            );
        }

        if (!$schedule['is_working_day']) {

            if (!($context['hold_on_rest_day'] ?? true)) {
                return self::result(
                    self::AUTHORIZED,
                    'rest_day_without_access_restriction'
                );
            }

            return self::result(
                self::HOLD,
                'non_working_day',
                true
            );
        }

        $type = $schedule['schedule_type'] ?? 'fixed';

        if ($type === 'flexible') {
            return self::result(
                self::AUTHORIZED,
                'flexible_schedule'
            );
        }

        if (!in_array($type, ['fixed', 'open'], true)) {
            return self::result(
                self::REVIEW,
                'unknown_schedule_type',
                true
            );
        }

        $start = $schedule['start_at'] ?? null;
        $end = $schedule['end_at'] ?? null;

        if (!$start instanceof DateTimeImmutable) {
            return self::result(
                self::REVIEW,
                'missing_start_time',
                true
            );
        }

        if (
            $type === 'fixed'
            && (
                !$end instanceof DateTimeImmutable
                || $end <= $start
            )
        ) {
            return self::result(
                self::REVIEW,
                'invalid_schedule_interval',
                true
            );
        }

        if (
            $type === 'fixed'
            && $end instanceof DateTimeImmutable
            && $at >= $end
        ) {
            if (!($context['hold_on_late'] ?? false)) {

                return self::result(
                    self::AUTHORIZED,
                    'late_without_access_restriction'
                );
            }

            return self::result(
                self::HOLD,
                'outside_working_hours',
                true
            );
        }

        $earlyLimit =
            $context['early_arrival_minutes'] ?? null;

        if ($earlyLimit !== null) {

            $earlyLimit = max(
                0,
                (int) $earlyLimit
            );

            $earliest = $start->modify(
                "-{$earlyLimit} minutes"
            );

            if ($at < $earliest) {
                return self::result(
                    self::HOLD,
                    'early_arrival_outside_limit',
                    true
                );
            }
        }

        $tolerance = max(
            0,
            (int) (
                $context['access_tolerance_minutes']
                ?? $schedule['tolerance_late_minutes']
                ?? 0
            )
        );

        $limit = $start->modify(
            "+{$tolerance} minutes"
        );

        if ($at <= $start) {
            return self::result(
                self::AUTHORIZED,
                'on_time'
            );
        }

        if ($at <= $limit) {
            return self::result(
                self::AUTHORIZED,
                'within_tolerance'
            );
        }

        if (!($context['hold_on_late'] ?? false)) {
            return self::result(
                self::AUTHORIZED,
                'late_without_access_restriction'
            );
        }

        return self::result(
            self::HOLD,
            'late_requires_authorization',
            true
        );
    }

    private static function result(
        string $status,
        string $reason,
        bool $requiresAuthorization = false
    ): array {
        return [
            'status' => $status,
            'reason_code' => $reason,
            'requires_authorization' =>
                $requiresAuthorization,
        ];
    }
}
