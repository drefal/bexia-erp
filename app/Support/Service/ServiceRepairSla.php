<?php

namespace App\Support\Service;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class ServiceRepairSla
{
    public const WARNING_HOURS = 8.0;

    public static function key(mixed $repair, ?CarbonInterface $now = null): string
    {
        $now ??= now();

        $dueAt = self::dateValue($repair, ['promised_at']);

        if (! $dueAt) {
            return 'sin_fecha';
        }

        $isClosed = self::isClosed($repair);
        $endAt = self::dateValue($repair, [
            'delivered_at',
            'finished_at',
            'repair_finished_at',
            'closed_at',
            'updated_at',
        ]) ?? $now;

        if ($isClosed) {
            return $endAt->greaterThan($dueAt)
                ? 'cerrada_vencida'
                : 'cerrada_en_tiempo';
        }

        if ($now->greaterThan($dueAt)) {
            return 'vencida';
        }

        $hoursToDue = self::hoursBetween($now, $dueAt);

        if ($hoursToDue !== null && $hoursToDue <= self::WARNING_HOURS) {
            return 'por_vencer';
        }

        return 'en_tiempo';
    }

    public static function label(mixed $repair): string
    {
        return match (self::key($repair)) {
            'en_tiempo' => 'En tiempo',
            'por_vencer' => 'Por vencer',
            'vencida' => 'Vencida',
            'cerrada_en_tiempo' => 'Cerrada en tiempo',
            'cerrada_vencida' => 'Cerrada vencida',
            default => 'Sin fecha',
        };
    }

    public static function color(mixed $repair): string
    {
        return match (self::key($repair)) {
            'en_tiempo', 'cerrada_en_tiempo' => 'success',
            'por_vencer' => 'warning',
            'vencida', 'cerrada_vencida' => 'danger',
            default => 'gray',
        };
    }

    public static function description(mixed $repair): string
    {
        $now = now();
        $dueAt = self::dateValue($repair, ['promised_at']);
        $key = self::key($repair, $now);

        if (! $dueAt) {
            return 'Sin fecha prometida';
        }

        $dueText = $dueAt->format('d/m/Y H:i');

        if ($key === 'vencida') {
            $hours = abs(self::hoursBetween($now, $dueAt) ?? 0);

            return 'Vencida por ' . self::formatHours($hours);
        }

        if ($key === 'por_vencer' || $key === 'en_tiempo') {
            $hours = max(0, self::hoursBetween($now, $dueAt) ?? 0);

            return 'Vence ' . $dueText . ' · faltan ' . self::formatHours($hours);
        }

        if ($key === 'cerrada_vencida' || $key === 'cerrada_en_tiempo') {
            $endAt = self::dateValue($repair, [
                'delivered_at',
                'finished_at',
                'repair_finished_at',
                'closed_at',
                'updated_at',
            ]);

            return 'Prometida ' . $dueText . ($endAt ? ' · cerrada ' . $endAt->format('d/m/Y H:i') : '');
        }

        return 'Prometida ' . $dueText;
    }

    public static function summary(mixed $repair): string
    {
        $label = self::label($repair);
        $description = self::description($repair);

        return trim($label . ' · ' . $description);
    }

    public static function hoursToDue(mixed $repair): ?float
    {
        $dueAt = self::dateValue($repair, ['promised_at']);

        if (! $dueAt) {
            return null;
        }

        return self::hoursBetween(now(), $dueAt);
    }

    protected static function isClosed(mixed $repair): bool
    {
        $status = strtolower((string) self::value($repair, 'status'));
        $stage = strtolower((string) self::value($repair, 'workflow_stage'));

        $closedValues = [
            'delivered',
            'entregado',
            'closed',
            'cerrado',
            'cancelled',
            'cancelado',
            'finished',
            'finalizado',
        ];

        return in_array($status, $closedValues, true)
            || in_array($stage, $closedValues, true);
    }

    protected static function value(mixed $record, string $field): mixed
    {
        if (is_array($record)) {
            return $record[$field] ?? null;
        }

        if (is_object($record)) {
            return $record->{$field} ?? null;
        }

        return null;
    }

    protected static function dateValue(mixed $record, array $fields): ?Carbon
    {
        foreach ($fields as $field) {
            $value = self::value($record, $field);

            if (! filled($value)) {
                continue;
            }

            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    protected static function hoursBetween(CarbonInterface $from, CarbonInterface $to): ?float
    {
        return round($from->diffInMinutes($to, false) / 60, 1);
    }

    protected static function formatHours(float $hours): string
    {
        if ($hours < 1) {
            return max(0, (int) round($hours * 60)) . ' min';
        }

        return number_format($hours, 1) . ' h';
    }
}
