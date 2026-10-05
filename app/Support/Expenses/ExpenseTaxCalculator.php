<?php

namespace App\Support\Expenses;

class ExpenseTaxCalculator
{
    public const MODE_16 = 'iva_16';
    public const MODE_8 = 'iva_8';
    public const MODE_0 = 'iva_0';
    public const MODE_EXEMPT = 'exempt';
    public const MODE_MANUAL = 'manual';

    public static function options(): array
    {
        return [
            self::MODE_16 => 'IVA 16%',
            self::MODE_8 => 'IVA 8%',
            self::MODE_0 => 'IVA 0%',
            self::MODE_EXEMPT => 'Exento de IVA',
            self::MODE_MANUAL => 'Captura manual',
        ];
    }

    public static function rate(?string $mode): ?float
    {
        return match ($mode) {
            self::MODE_16 => 0.16,
            self::MODE_8 => 0.08,
            self::MODE_0,
            self::MODE_EXEMPT => 0.0,
            default => null,
        };
    }

    public static function isAutomatic(?string $mode): bool
    {
        return static::rate($mode) !== null;
    }

    public static function fromSubtotal(
        mixed $subtotal,
        ?string $mode
    ): array {
        $subtotal = static::money($subtotal);
        $rate = static::rate($mode);

        if ($rate === null) {
            return [
                'subtotal' => $subtotal,
                'tax_amount' => null,
                'total_amount' => null,
            ];
        }

        $tax = static::money($subtotal * $rate);
        $total = static::money($subtotal + $tax);

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $total,
        ];
    }

    public static function fromTotal(
        mixed $total,
        ?string $mode
    ): array {
        $total = static::money($total);
        $rate = static::rate($mode);

        if ($rate === null) {
            return [
                'subtotal' => null,
                'tax_amount' => null,
                'total_amount' => $total,
            ];
        }

        if ($rate == 0.0) {
            return [
                'subtotal' => $total,
                'tax_amount' => 0.0,
                'total_amount' => $total,
            ];
        }

        $subtotal = static::money(
            $total / (1 + $rate)
        );

        $tax = static::money(
            $total - $subtotal
        );

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $total,
        ];
    }

    public static function manualTotal(
        mixed $subtotal,
        mixed $tax
    ): float {
        return static::money(
            static::money($subtotal)
            + static::money($tax)
        );
    }

    public static function percentage(?string $mode): ?float
    {
        $rate = static::rate($mode);

        return $rate === null
            ? null
            : static::money($rate * 100);
    }

    protected static function money(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_string($value)) {
            $value = str_replace(
                [',', '$', ' '],
                '',
                $value
            );
        }

        return round((float) $value, 2);
    }
}
